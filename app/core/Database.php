<?php

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

class Database
{
    private static ?self $instance = null;
    private static bool $schemaInitialized = false;

    private PDO $pdo;

    private function __construct()
    {
        $this->pdo = $this->createConnection();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function initializeSchema(): void
    {
        if (self::$schemaInitialized) {
            return;
        }

        $database = self::getInstance();

        if ($database->schemaExists()) {
            $database->ensureCoreInfrastructure();
            self::$schemaInitialized = true;
            return;
        }

        $database->runSchemaScript();
        $database->ensureCoreInfrastructure();
        self::$schemaInitialized = true;
    }

    public static function bootstrapProviderAccess(): void
    {
        if (!defined('Config::PROVIDER_BOOTSTRAP_ENABLED') || \Config::PROVIDER_BOOTSTRAP_ENABLED !== true) {
            return;
        }

        $database = self::getInstance();
        $database->ensureSubscriptionInfrastructure();
        $database->ensureProviderDefaults();
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();

        return is_array($result) ? $result : null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function execute(string $sql, array $params = []): bool
    {
        return $this->query($sql, $params) !== false;
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    private function createConnection(): PDO
    {
        $driver = strtolower((string) \Config::DB_DRIVER);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if ($driver === 'sqlite') {
            $databasePath = (string) \Config::DB_PATH;
            $directory = dirname($databasePath);

            if (!is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }

            $pdo = new PDO('sqlite:' . $databasePath, null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON;');

            return $pdo;
        }

        if ($driver === 'mysql') {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                \Config::DB_HOST,
                (int) \Config::DB_PORT,
                \Config::DB_NAME,
                \Config::DB_CHARSET
            );

            return new PDO($dsn, \Config::DB_USER, \Config::DB_PASS, $options);
        }

        throw new RuntimeException('Driver de base de données non supporté: ' . $driver);
    }

    private function schemaExists(): bool
    {
        return $this->tableExists('users');
    }

    private function runSchemaScript(): void
    {
        $schemaPath = (string) \Config::DB_SCHEMA_FILE;

        if (!is_file($schemaPath) || !is_readable($schemaPath)) {
            throw new RuntimeException('Fichier de schéma introuvable: ' . $schemaPath);
        }

        $sql = (string) file_get_contents($schemaPath);
        if ($sql === '') {
            throw new RuntimeException('Le fichier de schéma SQL est vide: ' . $schemaPath);
        }

        $driver = strtolower((string) \Config::DB_DRIVER);
        if ($driver === 'sqlite') {
            $sql = $this->convertMysqlSchemaToSqlite($sql);
        }

        $statements = $this->splitSqlStatements($sql);
        if ($statements === []) {
            throw new RuntimeException('Aucune instruction SQL valide à exécuter.');
        }

        try {
            $this->beginTransaction();
            foreach ($statements as $statement) {
                $trimmed = trim($statement);
                if ($trimmed === '') {
                    continue;
                }
                $this->pdo->exec($trimmed);
            }
            $this->commit();
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->rollback();
            }
            throw new RuntimeException('Échec de l\'initialisation de la base: ' . $exception->getMessage());
        }
    }

    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $previous = $i > 0 ? $sql[$i - 1] : '';

            if ($char === "'" && !$inDoubleQuote && $previous !== '\\') {
                $inSingleQuote = !$inSingleQuote;
            } elseif ($char === '"' && !$inSingleQuote && $previous !== '\\') {
                $inDoubleQuote = !$inDoubleQuote;
            }

            if ($char === ';' && !$inSingleQuote && !$inDoubleQuote) {
                $statements[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }

        return $statements;
    }

    private function convertMysqlSchemaToSqlite(string $sql): string
    {
        $lines = preg_split('/\R/', $sql) ?: [];
        $converted = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || strpos($trimmed, '--') === 0) {
                continue;
            }

            if (stripos($trimmed, 'INDEX ') === 0) {
                continue;
            }

            $line = preg_replace('/\bINT\s+PRIMARY\s+KEY\s+AUTO_INCREMENT\b/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $line);
            $line = preg_replace('/\bBIGINT\s+PRIMARY\s+KEY\s+AUTO_INCREMENT\b/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $line);
            $line = preg_replace('/\bENUM\s*\([^)]+\)/i', 'TEXT', $line);
            $line = preg_replace('/\bDECIMAL\s*\(\s*\d+\s*,\s*\d+\s*\)/i', 'NUMERIC', $line);
            $line = preg_replace('/\bBOOLEAN\b/i', 'INTEGER', $line);
            $line = preg_replace('/\bJSON\b/i', 'TEXT', $line);
            $line = preg_replace('/\bTIMESTAMP\b/i', 'TEXT', $line);
            $line = preg_replace('/\bINT\b/i', 'INTEGER', $line);
            $line = preg_replace('/\bON UPDATE CURRENT_TIMESTAMP\b/i', '', $line);
            $line = preg_replace('/UNIQUE KEY\s+\w+\s*\(([^)]+)\)/i', 'UNIQUE ($1)', $line);
            $line = preg_replace('/\s+/', ' ', $line);

            $converted[] = $line;
        }

        $convertedSql = implode("\n", $converted);
        $convertedSql = preg_replace('/,\s*\)/', "\n)", $convertedSql);

        return (string) $convertedSql;
    }

    private function tableExists(string $table): bool
    {
        $driver = strtolower((string) \Config::DB_DRIVER);

        if ($driver === 'sqlite') {
            $row = $this->fetchOne(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name LIMIT 1",
                ['name' => $table]
            );
            return $row !== null;
        }

        $row = $this->fetchOne('SHOW TABLES LIKE :table_name', ['table_name' => $table]);
        return $row !== null;
    }

    private function ensureProviderDefaults(): void
    {
        if (!$this->tableExists('provider_users') || !$this->tableExists('provider_api_keys')) {
            return;
        }

        $userCountRow = $this->fetchOne('SELECT COUNT(*) AS total FROM provider_users');
        $keyCountRow = $this->fetchOne('SELECT COUNT(*) AS total FROM provider_api_keys');

        $userCount = (int) ($userCountRow['total'] ?? 0);
        $keyCount = (int) ($keyCountRow['total'] ?? 0);

        if ($userCount === 0) {
            $passwordHash = password_hash(
                (string) \Config::PROVIDER_ADMIN_PASSWORD,
                PASSWORD_BCRYPT,
                ['cost' => \Config::PASSWORD_COST]
            );

            if ($passwordHash === false) {
                throw new RuntimeException('Impossible de créer le hash du compte fournisseur.');
            }

            $this->execute(
                'INSERT INTO provider_users (email, password_hash, full_name, is_active)
                 VALUES (:email, :password_hash, :full_name, :is_active)',
                [
                    'email' => \Config::PROVIDER_ADMIN_EMAIL,
                    'password_hash' => $passwordHash,
                    'full_name' => \Config::PROVIDER_ADMIN_NAME,
                    'is_active' => 1,
                ]
            );
        }

        if ($keyCount === 0) {
            $this->execute(
                'INSERT INTO provider_api_keys (key_name, key_hash, is_active)
                 VALUES (:key_name, :key_hash, :is_active)',
                [
                    'key_name' => \Config::PROVIDER_API_KEY_NAME,
                    'key_hash' => hash('sha256', (string) \Config::PROVIDER_API_KEY),
                    'is_active' => 1,
                ]
            );
        }
    }

    private function ensureSubscriptionInfrastructure(): void
    {
        $this->ensureCompanyColumn('provider_callback_url', 'VARCHAR(500)');
        $this->ensureCompanyColumn(
            'reminder_interval_days',
            strtolower((string) \Config::DB_DRIVER) === 'sqlite' ? 'INTEGER DEFAULT 1' : 'INT DEFAULT 1'
        );
        $this->ensureCompanyColumn('auto_subscription_enabled', 'BOOLEAN DEFAULT TRUE');

        if (!$this->tableExists('subscription_licenses')) {
            if (strtolower((string) \Config::DB_DRIVER) === 'sqlite') {
                $this->execute(
                    'CREATE TABLE subscription_licenses (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        company_id INTEGER NOT NULL,
                        period_end_date TEXT NOT NULL,
                        license_hash TEXT UNIQUE NOT NULL,
                        status TEXT DEFAULT "generated",
                        webhook_url TEXT,
                        webhook_response TEXT,
                        generated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                        sent_at TEXT NULL,
                        activated_at TEXT NULL,
                        expires_at TEXT,
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
                    )'
                );
            } else {
                $this->execute(
                    'CREATE TABLE subscription_licenses (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        company_id INT NOT NULL,
                        period_end_date DATE NOT NULL,
                        license_hash VARCHAR(128) UNIQUE NOT NULL,
                        status ENUM("generated", "sent", "activated", "expired") DEFAULT "generated",
                        webhook_url VARCHAR(500),
                        webhook_response TEXT,
                        generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        sent_at TIMESTAMP NULL,
                        activated_at TIMESTAMP NULL,
                        expires_at DATE,
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
                    )'
                );
            }
        }

        if (!$this->tableExists('subscription_events')) {
            if (strtolower((string) \Config::DB_DRIVER) === 'sqlite') {
                $this->execute(
                    'CREATE TABLE subscription_events (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        company_id INTEGER NOT NULL,
                        event_type TEXT NOT NULL,
                        details TEXT,
                        source TEXT DEFAULT "system",
                        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
                    )'
                );
                $this->execute('CREATE INDEX idx_subscription_event_type ON subscription_events(event_type)');
                $this->execute('CREATE INDEX idx_subscription_event_created ON subscription_events(created_at)');
            } else {
                $this->execute(
                    'CREATE TABLE subscription_events (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        company_id INT NOT NULL,
                        event_type VARCHAR(60) NOT NULL,
                        details TEXT,
                        source ENUM("system", "dashboard", "api") DEFAULT "system",
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                        INDEX idx_subscription_event_type (event_type),
                        INDEX idx_subscription_event_created (created_at)
                    )'
                );
            }
        }
    }

    private function ensureCoreInfrastructure(): void
    {
        $this->ensureSubscriptionInfrastructure();
        $this->ensureFiscalInfrastructure();
        $this->ensureTransactionInfrastructure();
        $this->ensureInvoiceItemsInfrastructure();
        $this->ensureInvoiceHistoryInfrastructure();
        $this->ensureStockInfrastructure();
        $this->ensureClientsInfrastructure();
        $this->ensureAiInfrastructure();
        $this->ensureAiResourcesInfrastructure();
        $this->ensureAuditLogsInfrastructure();
        $isSqlite = strtolower((string) \Config::DB_DRIVER) === 'sqlite';
        $this->ensureCompanyColumn('invoice_logo_url', $isSqlite ? 'TEXT NULL' : 'VARCHAR(500) NULL');
        $this->ensureCompanyColumn('invoice_brand_color', $isSqlite ? 'TEXT NULL' : 'VARCHAR(16) NULL');
        $this->ensureCompanyColumn('default_tax_rate', $isSqlite ? 'NUMERIC DEFAULT 0' : 'DECIMAL(6,2) DEFAULT 0');
    }

    private function ensureClientsInfrastructure(): void
    {
        $isSqlite = strtolower((string) \Config::DB_DRIVER) === 'sqlite';

        if (!$this->tableExists('clients')) {
            if ($isSqlite) {
                $this->execute(
                    'CREATE TABLE clients (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        company_id INTEGER NOT NULL,
                        name TEXT NULL,
                        phone TEXT NULL,
                        email TEXT NULL,
                        address TEXT NULL,
                        client_identity TEXT NULL,
                        is_active INTEGER NOT NULL DEFAULT 1,
                        created_by INTEGER NULL,
                        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                        updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                    )'
                );
                $this->execute('CREATE UNIQUE INDEX IF NOT EXISTS idx_clients_company_phone ON clients(company_id, phone)');
                $this->execute('CREATE INDEX IF NOT EXISTS idx_clients_company ON clients(company_id)');
                $this->execute('CREATE INDEX IF NOT EXISTS idx_clients_identity ON clients(company_id, client_identity)');
                return;
            }

            $this->execute(
                'CREATE TABLE clients (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    company_id INT NOT NULL,
                    name VARCHAR(180) NULL,
                    phone VARCHAR(40) NULL,
                    email VARCHAR(180) NULL,
                    address TEXT NULL,
                    client_identity VARCHAR(260) NULL,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_by INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                    UNIQUE KEY idx_clients_company_phone (company_id, phone),
                    INDEX idx_clients_company (company_id),
                    INDEX idx_clients_identity (company_id, client_identity)
                )'
            );
            return;
        }

        $this->ensureTableColumn('clients', 'client_identity', $isSqlite ? 'TEXT NULL' : 'VARCHAR(260) NULL');
        $this->ensureTableColumn('clients', 'is_active', $isSqlite ? 'INTEGER DEFAULT 1' : 'BOOLEAN DEFAULT TRUE');
        $this->ensureTableColumn('clients', 'created_by', $isSqlite ? 'INTEGER NULL' : 'INT NULL');
    }

    private function ensureAuditLogsInfrastructure(): void
    {
        $isSqlite = strtolower((string) \Config::DB_DRIVER) === 'sqlite';
        if ($this->tableExists('audit_logs')) {
            return;
        }

        if ($isSqlite) {
            $this->execute(
                'CREATE TABLE audit_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER,
                    action TEXT NOT NULL,
                    table_name TEXT NULL,
                    record_id INTEGER,
                    old_data TEXT NULL,
                    new_data TEXT NULL,
                    ip_address TEXT NULL,
                    user_agent TEXT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                )'
            );
            $this->execute('CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON audit_logs(action)');
            $this->execute('CREATE INDEX IF NOT EXISTS idx_audit_logs_created ON audit_logs(created_at)');
            return;
        }

        $this->execute(
            'CREATE TABLE audit_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT,
                action VARCHAR(100) NOT NULL,
                table_name VARCHAR(50),
                record_id INT,
                old_data JSON,
                new_data JSON,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_audit_logs_action (action),
                INDEX idx_audit_logs_created (created_at)
            )'
        );
    }

    private function ensureStockInfrastructure(): void
    {
        $isSqlite = strtolower((string) \Config::DB_DRIVER) === 'sqlite';

        if (!$this->tableExists('products')) {
            if ($isSqlite) {
                $this->execute(
                    'CREATE TABLE products (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        company_id INTEGER NOT NULL,
                        name TEXT NOT NULL,
                        sku TEXT,
                        brand TEXT NULL,
                        supplier TEXT NULL,
                        dosage TEXT NULL,
                        forme TEXT NULL,
                        presentation TEXT NULL,
                        color_hex TEXT NULL,
                        unit TEXT NOT NULL DEFAULT "unite",
                        quantity NUMERIC NOT NULL DEFAULT 0,
                        min_stock NUMERIC NOT NULL DEFAULT 0,
                        purchase_price NUMERIC NOT NULL DEFAULT 0,
                        sale_price NUMERIC NOT NULL DEFAULT 0,
                        expiration_date TEXT NULL,
                        is_active INTEGER NOT NULL DEFAULT 1,
                        created_by INTEGER,
                        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                        updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                    )'
                );
                $this->execute('CREATE UNIQUE INDEX idx_products_company_sku ON products(company_id, sku)');
                $this->execute('CREATE INDEX idx_products_company_name ON products(company_id, name)');
            } else {
                $this->execute(
                    'CREATE TABLE products (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        company_id INT NOT NULL,
                        name VARCHAR(160) NOT NULL,
                        sku VARCHAR(80) NULL,
                        brand VARCHAR(120) NULL,
                        supplier VARCHAR(160) NULL,
                        dosage VARCHAR(80) NULL,
                        forme VARCHAR(80) NULL,
                        presentation VARCHAR(160) NULL,
                        color_hex VARCHAR(16) NULL,
                        unit VARCHAR(30) NOT NULL DEFAULT "unite",
                        quantity DECIMAL(15,2) NOT NULL DEFAULT 0,
                        min_stock DECIMAL(15,2) NOT NULL DEFAULT 0,
                        purchase_price DECIMAL(15,2) NOT NULL DEFAULT 0,
                        sale_price DECIMAL(15,2) NOT NULL DEFAULT 0,
                        expiration_date DATE NULL,
                        is_active BOOLEAN NOT NULL DEFAULT TRUE,
                        created_by INT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                        UNIQUE KEY idx_products_company_sku (company_id, sku),
                        INDEX idx_products_company_name (company_id, name)
                    )'
                );
            }
        }
        $this->ensureTableColumn(
            'products',
            'color_hex',
            $isSqlite ? 'TEXT NULL' : 'VARCHAR(16) NULL'
        );
        $this->ensureTableColumn(
            'products',
            'brand',
            $isSqlite ? 'TEXT NULL' : 'VARCHAR(120) NULL'
        );
        $this->ensureTableColumn(
            'products',
            'supplier',
            $isSqlite ? 'TEXT NULL' : 'VARCHAR(160) NULL'
        );
        $this->ensureTableColumn(
            'products',
            'dosage',
            $isSqlite ? 'TEXT NULL' : 'VARCHAR(80) NULL'
        );
        $this->ensureTableColumn(
            'products',
            'forme',
            $isSqlite ? 'TEXT NULL' : 'VARCHAR(80) NULL'
        );
        $this->ensureTableColumn(
            'products',
            'presentation',
            $isSqlite ? 'TEXT NULL' : 'VARCHAR(160) NULL'
        );
        $this->ensureTableColumn(
            'products',
            'expiration_date',
            $isSqlite ? 'TEXT NULL' : 'DATE NULL'
        );

        if (!$this->tableExists('stock_movements')) {
            if ($isSqlite) {
                $this->execute(
                    'CREATE TABLE stock_movements (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        product_id INTEGER NOT NULL,
                        company_id INTEGER NOT NULL,
                        movement_type TEXT NOT NULL,
                        quantity_change NUMERIC NOT NULL,
                        quantity_before NUMERIC NOT NULL,
                        quantity_after NUMERIC NOT NULL,
                        reason TEXT,
                        reference TEXT,
                        created_by INTEGER,
                        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                    )'
                );
                $this->execute('CREATE INDEX idx_stock_movements_company_date ON stock_movements(company_id, created_at)');
                $this->execute('CREATE INDEX idx_stock_movements_product_date ON stock_movements(product_id, created_at)');
            } else {
                $this->execute(
                    'CREATE TABLE stock_movements (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        product_id INT NOT NULL,
                        company_id INT NOT NULL,
                        movement_type ENUM("in", "out", "adjustment") NOT NULL,
                        quantity_change DECIMAL(15,2) NOT NULL,
                        quantity_before DECIMAL(15,2) NOT NULL,
                        quantity_after DECIMAL(15,2) NOT NULL,
                        reason VARCHAR(255),
                        reference VARCHAR(120),
                        created_by INT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                        INDEX idx_stock_movements_company_date (company_id, created_at),
                        INDEX idx_stock_movements_product_date (product_id, created_at)
                    )'
                );
            }
        }

        if (!$this->tableExists('purchase_orders')) {
            if ($isSqlite) {
                $this->execute(
                    'CREATE TABLE purchase_orders (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        company_id INTEGER NOT NULL,
                        order_number TEXT NOT NULL,
                        supplier_name TEXT NOT NULL,
                        status TEXT NOT NULL DEFAULT "draft",
                        expected_date TEXT NULL,
                        total_amount NUMERIC NOT NULL DEFAULT 0,
                        notes TEXT NULL,
                        created_by INTEGER NULL,
                        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                        updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                    )'
                );
                $this->execute('CREATE UNIQUE INDEX idx_purchase_orders_company_number ON purchase_orders(company_id, order_number)');
                $this->execute('CREATE INDEX idx_purchase_orders_company_status ON purchase_orders(company_id, status, created_at)');
            } else {
                $this->execute(
                    'CREATE TABLE purchase_orders (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        company_id INT NOT NULL,
                        order_number VARCHAR(80) NOT NULL,
                        supplier_name VARCHAR(180) NOT NULL,
                        status VARCHAR(30) NOT NULL DEFAULT "draft",
                        expected_date DATE NULL,
                        total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                        notes TEXT NULL,
                        created_by INT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                        UNIQUE KEY idx_purchase_orders_company_number (company_id, order_number),
                        INDEX idx_purchase_orders_company_status (company_id, status, created_at)
                    )'
                );
            }
        }

        if (!$this->tableExists('purchase_order_items')) {
            if ($isSqlite) {
                $this->execute(
                    'CREATE TABLE purchase_order_items (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        purchase_order_id INTEGER NOT NULL,
                        product_id INTEGER NOT NULL,
                        description TEXT NOT NULL,
                        quantity NUMERIC NOT NULL,
                        unit_cost NUMERIC NOT NULL DEFAULT 0,
                        line_total NUMERIC NOT NULL DEFAULT 0,
                        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
                        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
                    )'
                );
                $this->execute('CREATE INDEX idx_purchase_order_items_order ON purchase_order_items(purchase_order_id)');
            } else {
                $this->execute(
                    'CREATE TABLE purchase_order_items (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        purchase_order_id INT NOT NULL,
                        product_id INT NOT NULL,
                        description VARCHAR(255) NOT NULL,
                        quantity DECIMAL(15,2) NOT NULL,
                        unit_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
                        line_total DECIMAL(15,2) NOT NULL DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
                        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                        INDEX idx_purchase_order_items_order (purchase_order_id)
                    )'
                );
            }
        }

        if (!$this->tableExists('product_unit_conversions')) {
            if ($isSqlite) {
                $this->execute(
                    'CREATE TABLE product_unit_conversions (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        product_id INTEGER NOT NULL,
                        company_id INTEGER NOT NULL,
                        unit_code TEXT NOT NULL,
                        unit_label TEXT NOT NULL,
                        factor_to_base NUMERIC NOT NULL DEFAULT 1,
                        is_base INTEGER NOT NULL DEFAULT 0,
                        is_active INTEGER NOT NULL DEFAULT 1,
                        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                        updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
                    )'
                );
                $this->execute('CREATE UNIQUE INDEX IF NOT EXISTS idx_unit_conv_unique ON product_unit_conversions(company_id, product_id, unit_code)');
                $this->execute('CREATE INDEX IF NOT EXISTS idx_unit_conv_product ON product_unit_conversions(product_id, is_active)');
            } else {
                $this->execute(
                    'CREATE TABLE product_unit_conversions (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        product_id INT NOT NULL,
                        company_id INT NOT NULL,
                        unit_code VARCHAR(30) NOT NULL,
                        unit_label VARCHAR(80) NOT NULL,
                        factor_to_base DECIMAL(18,6) NOT NULL DEFAULT 1,
                        is_base BOOLEAN NOT NULL DEFAULT FALSE,
                        is_active BOOLEAN NOT NULL DEFAULT TRUE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                        UNIQUE KEY idx_unit_conv_unique (company_id, product_id, unit_code),
                        INDEX idx_unit_conv_product (product_id, is_active)
                    )'
                );
            }
        }

        if (!$this->tableExists('stock_lots')) {
            if ($isSqlite) {
                $this->execute(
                    'CREATE TABLE stock_lots (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        product_id INTEGER NOT NULL,
                        company_id INTEGER NOT NULL,
                        lot_code TEXT NOT NULL,
                        supplier TEXT NULL,
                        source_type TEXT NOT NULL DEFAULT "manual",
                        source_reference TEXT NULL,
                        quantity_initial_base NUMERIC NOT NULL,
                        quantity_remaining_base NUMERIC NOT NULL,
                        unit_cost_base NUMERIC NOT NULL DEFAULT 0,
                        expiration_date TEXT NULL,
                        opened_at TEXT DEFAULT CURRENT_TIMESTAMP,
                        exhausted_at TEXT NULL,
                        created_by INTEGER NULL,
                        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                        updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                    )'
                );
                $this->execute('CREATE INDEX IF NOT EXISTS idx_stock_lots_product_opened ON stock_lots(product_id, opened_at, id)');
                $this->execute('CREATE INDEX IF NOT EXISTS idx_stock_lots_remaining ON stock_lots(product_id, quantity_remaining_base)');
            } else {
                $this->execute(
                    'CREATE TABLE stock_lots (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        product_id INT NOT NULL,
                        company_id INT NOT NULL,
                        lot_code VARCHAR(120) NOT NULL,
                        supplier VARCHAR(160) NULL,
                        source_type VARCHAR(40) NOT NULL DEFAULT "manual",
                        source_reference VARCHAR(120) NULL,
                        quantity_initial_base DECIMAL(18,6) NOT NULL,
                        quantity_remaining_base DECIMAL(18,6) NOT NULL,
                        unit_cost_base DECIMAL(18,6) NOT NULL DEFAULT 0,
                        expiration_date DATE NULL,
                        opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        exhausted_at TIMESTAMP NULL,
                        created_by INT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                        INDEX idx_stock_lots_product_opened (product_id, opened_at, id),
                        INDEX idx_stock_lots_remaining (product_id, quantity_remaining_base)
                    )'
                );
            }
        }
        $this->ensureTableColumn(
            'stock_lots',
            'unit_cost_base',
            $isSqlite ? 'NUMERIC NOT NULL DEFAULT 0' : 'DECIMAL(18,6) NOT NULL DEFAULT 0'
        );
        $this->ensureTableColumn(
            'stock_lots',
            'expiration_date',
            $isSqlite ? 'TEXT NULL' : 'DATE NULL'
        );
        $this->ensureTableColumn(
            'stock_lots',
            'supplier',
            $isSqlite ? 'TEXT NULL' : 'VARCHAR(160) NULL'
        );

        if (!$this->tableExists('stock_lot_allocations')) {
            if ($isSqlite) {
                $this->execute(
                    'CREATE TABLE stock_lot_allocations (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        stock_movement_id INTEGER NOT NULL,
                        lot_id INTEGER NOT NULL,
                        quantity_base NUMERIC NOT NULL,
                        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (stock_movement_id) REFERENCES stock_movements(id) ON DELETE CASCADE,
                        FOREIGN KEY (lot_id) REFERENCES stock_lots(id) ON DELETE CASCADE
                    )'
                );
                $this->execute('CREATE INDEX IF NOT EXISTS idx_alloc_movement ON stock_lot_allocations(stock_movement_id)');
                $this->execute('CREATE INDEX IF NOT EXISTS idx_alloc_lot ON stock_lot_allocations(lot_id)');
            } else {
                $this->execute(
                    'CREATE TABLE stock_lot_allocations (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        stock_movement_id INT NOT NULL,
                        lot_id INT NOT NULL,
                        quantity_base DECIMAL(18,6) NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (stock_movement_id) REFERENCES stock_movements(id) ON DELETE CASCADE,
                        FOREIGN KEY (lot_id) REFERENCES stock_lots(id) ON DELETE CASCADE,
                        INDEX idx_alloc_movement (stock_movement_id),
                        INDEX idx_alloc_lot (lot_id)
                    )'
                );
            }
        }
    }

    private function ensureAiInfrastructure(): void
    {
        $isSqlite = strtolower((string) \Config::DB_DRIVER) === 'sqlite';

        if (!$this->tableExists('chat_conversations')) {
            if ($isSqlite) {
                $this->execute(
                    'CREATE TABLE chat_conversations (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        company_id INTEGER NOT NULL,
                        user_id INTEGER NOT NULL,
                        provider TEXT NOT NULL DEFAULT "internal",
                        model TEXT NOT NULL DEFAULT "symphony-accountant-v1",
                        title TEXT,
                        memory_summary TEXT,
                        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                        updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                        last_message_at TEXT DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                    )'
                );
                $this->execute('CREATE INDEX idx_chat_conversations_company_user ON chat_conversations(company_id, user_id, last_message_at)');
            } else {
                $this->execute(
                    'CREATE TABLE chat_conversations (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        company_id INT NOT NULL,
                        user_id INT NOT NULL,
                        provider VARCHAR(50) NOT NULL DEFAULT "internal",
                        model VARCHAR(120) NOT NULL DEFAULT "symphony-accountant-v1",
                        title VARCHAR(180) NULL,
                        memory_summary TEXT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        INDEX idx_chat_conversations_company_user (company_id, user_id, last_message_at)
                    )'
                );
            }
        }

        if (!$this->tableExists('chat_messages')) {
            if ($isSqlite) {
                $this->execute(
                    'CREATE TABLE chat_messages (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        conversation_id INTEGER NOT NULL,
                        company_id INTEGER NOT NULL,
                        user_id INTEGER NOT NULL,
                        role TEXT NOT NULL,
                        content_json TEXT NOT NULL,
                        tool_calls_json TEXT NULL,
                        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                    )'
                );
                $this->execute('CREATE INDEX idx_chat_messages_conversation ON chat_messages(conversation_id, created_at)');
                $this->execute('CREATE INDEX idx_chat_messages_company_user ON chat_messages(company_id, user_id, created_at)');
            } else {
                $this->execute(
                    'CREATE TABLE chat_messages (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        conversation_id INT NOT NULL,
                        company_id INT NOT NULL,
                        user_id INT NOT NULL,
                        role VARCHAR(30) NOT NULL,
                        content_json JSON NOT NULL,
                        tool_calls_json JSON NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        INDEX idx_chat_messages_conversation (conversation_id, created_at),
                        INDEX idx_chat_messages_company_user (company_id, user_id, created_at)
                    )'
                );
            }
        }
    }

    private function ensureAiResourcesInfrastructure(): void
    {
        $isSqlite = strtolower((string) \Config::DB_DRIVER) === 'sqlite';
        if ($this->tableExists('ai_resources')) {
            return;
        }

        if ($isSqlite) {
            $this->execute(
                'CREATE TABLE ai_resources (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    company_id INTEGER NOT NULL,
                    resource_key TEXT NOT NULL,
                    resource_type TEXT NOT NULL,
                    title TEXT NULL,
                    content TEXT NOT NULL,
                    is_active INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
                )'
            );
            $this->execute('CREATE UNIQUE INDEX idx_ai_resources_unique ON ai_resources(company_id, resource_type, resource_key)');
            $this->execute('CREATE INDEX idx_ai_resources_company_type ON ai_resources(company_id, resource_type)');
            return;
        }

        $this->execute(
            'CREATE TABLE ai_resources (
                id INT PRIMARY KEY AUTO_INCREMENT,
                company_id INT NOT NULL,
                resource_key VARCHAR(120) NOT NULL,
                resource_type VARCHAR(40) NOT NULL,
                title VARCHAR(180) NULL,
                content LONGTEXT NOT NULL,
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                UNIQUE KEY idx_ai_resources_unique (company_id, resource_type, resource_key),
                INDEX idx_ai_resources_company_type (company_id, resource_type)
            )'
        );
    }

    private function ensureTransactionInfrastructure(): void
    {
        $isSqlite = strtolower((string) \Config::DB_DRIVER) === 'sqlite';

        $this->ensureTableColumn(
            'transactions',
            'expense_subcategory',
            $isSqlite ? 'TEXT NULL' : 'VARCHAR(60) NULL'
        );
        $this->ensureTableColumn(
            'transactions',
            'expense_fiscal_subcategory',
            $isSqlite ? 'TEXT NULL' : 'VARCHAR(60) NULL'
        );
        $this->ensureTableColumn(
            'transactions',
            'expense_subcategory_other',
            $isSqlite ? 'TEXT NULL' : 'VARCHAR(255) NULL'
        );
    }

    private function ensureFiscalInfrastructure(): void
    {
        $this->ensureCompanyColumn(
            'fiscal_period_duration_months',
            strtolower((string) \Config::DB_DRIVER) === 'sqlite' ? 'INTEGER DEFAULT 12' : 'INT DEFAULT 12'
        );

        if (!$this->tableExists('fiscal_periods')) {
            if (strtolower((string) \Config::DB_DRIVER) === 'sqlite') {
                $this->execute(
                    'CREATE TABLE fiscal_periods (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        company_id INTEGER NOT NULL,
                        label TEXT NOT NULL,
                        start_date TEXT NOT NULL,
                        end_date TEXT NOT NULL,
                        is_closed INTEGER DEFAULT 0,
                        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
                    )'
                );
                $this->execute('CREATE INDEX idx_fiscal_period_company_dates ON fiscal_periods(company_id, start_date, end_date)');
            } else {
                $this->execute(
                    'CREATE TABLE fiscal_periods (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        company_id INT NOT NULL,
                        label VARCHAR(120) NOT NULL,
                        start_date DATE NOT NULL,
                        end_date DATE NOT NULL,
                        is_closed BOOLEAN DEFAULT FALSE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                        INDEX idx_fiscal_period_company_dates (company_id, start_date, end_date)
                    )'
                );
            }
        }

        $this->ensureTableColumn(
            'transactions',
            'fiscal_period_id',
            strtolower((string) \Config::DB_DRIVER) === 'sqlite' ? 'INTEGER NULL' : 'INT NULL'
        );
        $this->ensureTableColumn(
            'invoices',
            'fiscal_period_id',
            strtolower((string) \Config::DB_DRIVER) === 'sqlite' ? 'INTEGER NULL' : 'INT NULL'
        );
        $this->ensureTableColumn(
            'invoices',
            'paid_amount',
            strtolower((string) \Config::DB_DRIVER) === 'sqlite' ? 'NUMERIC DEFAULT 0' : 'DECIMAL(15,2) DEFAULT 0'
        );
        $this->ensureTableColumn(
            'invoices',
            'paid_date',
            strtolower((string) \Config::DB_DRIVER) === 'sqlite' ? 'TEXT NULL' : 'DATE NULL'
        );
        $this->ensureTableColumn(
            'invoices',
            'issuer_company_name',
            strtolower((string) \Config::DB_DRIVER) === 'sqlite' ? 'TEXT NULL' : 'VARCHAR(180) NULL'
        );
        $this->ensureTableColumn(
            'invoices',
            'issuer_logo_url',
            strtolower((string) \Config::DB_DRIVER) === 'sqlite' ? 'TEXT NULL' : 'VARCHAR(500) NULL'
        );
        $this->ensureTableColumn(
            'invoices',
            'issuer_brand_color',
            strtolower((string) \Config::DB_DRIVER) === 'sqlite' ? 'TEXT NULL' : 'VARCHAR(16) NULL'
        );
        $this->ensureTableColumn(
            'invoices',
            'downloaded_at',
            strtolower((string) \Config::DB_DRIVER) === 'sqlite' ? 'TEXT NULL' : 'TIMESTAMP NULL'
        );
    }

    private function ensureInvoiceItemsInfrastructure(): void
    {
        if ($this->tableExists('invoice_items')) {
            $isSqlite = strtolower((string) \Config::DB_DRIVER) === 'sqlite';
            $this->ensureTableColumn(
                'invoice_items',
                'product_id',
                $isSqlite ? 'INTEGER NULL' : 'INT NULL'
            );
            $this->ensureTableColumn(
                'invoices',
                'invoice_type',
                $isSqlite ? 'TEXT DEFAULT "product"' : 'VARCHAR(20) DEFAULT "product"'
            );
            $this->ensureTableColumn(
                'invoice_items',
                'unit_code',
                $isSqlite ? 'TEXT NULL' : 'VARCHAR(30) NULL'
            );
            $this->ensureTableColumn(
                'invoice_items',
                'factor_to_base',
                $isSqlite ? 'NUMERIC DEFAULT 1' : 'DECIMAL(18,6) DEFAULT 1'
            );
            $this->ensureTableColumn(
                'invoice_items',
                'quantity_base',
                $isSqlite ? 'NUMERIC NULL' : 'DECIMAL(18,6) NULL'
            );
            $this->ensureTableColumn(
                'invoice_items',
                'stock_movement_id',
                $isSqlite ? 'INTEGER NULL' : 'INT NULL'
            );
            $this->ensureTableColumn(
                'invoice_items',
                'cogs_amount',
                $isSqlite ? 'NUMERIC DEFAULT 0' : 'DECIMAL(15,2) DEFAULT 0'
            );
            $this->ensureTableColumn(
                'invoice_items',
                'margin_amount',
                $isSqlite ? 'NUMERIC DEFAULT 0' : 'DECIMAL(15,2) DEFAULT 0'
            );
            return;
        }

        if (strtolower((string) \Config::DB_DRIVER) === 'sqlite') {
            $this->execute(
                'CREATE TABLE invoice_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    invoice_id INTEGER NOT NULL,
                    description TEXT NOT NULL,
                    quantity NUMERIC NOT NULL DEFAULT 1,
                    unit_price NUMERIC NOT NULL DEFAULT 0,
                    product_id INTEGER NULL,
                    unit_code TEXT NULL,
                    factor_to_base NUMERIC NOT NULL DEFAULT 1,
                    quantity_base NUMERIC NULL,
                    stock_movement_id INTEGER NULL,
                    cogs_amount NUMERIC NOT NULL DEFAULT 0,
                    margin_amount NUMERIC NOT NULL DEFAULT 0,
                    tax_rate NUMERIC NOT NULL DEFAULT 0,
                    subtotal NUMERIC NOT NULL DEFAULT 0,
                    tax_amount NUMERIC NOT NULL DEFAULT 0,
                    total NUMERIC NOT NULL DEFAULT 0,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
                )'
            );
            $this->execute('CREATE INDEX idx_invoice_items_invoice_id ON invoice_items(invoice_id)');
            return;
        }

        $this->execute(
            'CREATE TABLE invoice_items (
                id INT PRIMARY KEY AUTO_INCREMENT,
                invoice_id INT NOT NULL,
                description VARCHAR(255) NOT NULL,
                quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
                unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
                product_id INT NULL,
                unit_code VARCHAR(30) NULL,
                factor_to_base DECIMAL(18,6) NOT NULL DEFAULT 1,
                quantity_base DECIMAL(18,6) NULL,
                stock_movement_id INT NULL,
                cogs_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                margin_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                tax_rate DECIMAL(6,2) NOT NULL DEFAULT 0,
                subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
                tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                total DECIMAL(15,2) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
                INDEX idx_invoice_items_invoice_id (invoice_id)
            )'
        );

        $isSqlite = strtolower((string) \Config::DB_DRIVER) === 'sqlite';
        $this->ensureTableColumn(
            'invoices',
            'invoice_type',
            $isSqlite ? 'TEXT DEFAULT "product"' : 'VARCHAR(20) DEFAULT "product"'
        );
    }

    private function ensureInvoiceHistoryInfrastructure(): void
    {
        if ($this->tableExists('invoice_history')) {
            return;
        }

        if (strtolower((string) \Config::DB_DRIVER) === 'sqlite') {
            $this->execute(
                'CREATE TABLE invoice_history (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    invoice_id INTEGER NOT NULL,
                    company_id INTEGER NOT NULL,
                    event_type TEXT NOT NULL,
                    invoice_number TEXT,
                    status_before TEXT,
                    status_after TEXT,
                    payload_json TEXT,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
                    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
                )'
            );
            $this->execute('CREATE INDEX idx_invoice_history_invoice ON invoice_history(invoice_id, created_at)');
            $this->execute('CREATE INDEX idx_invoice_history_company ON invoice_history(company_id, created_at)');
            return;
        }

        $this->execute(
            'CREATE TABLE invoice_history (
                id INT PRIMARY KEY AUTO_INCREMENT,
                invoice_id INT NOT NULL,
                company_id INT NOT NULL,
                event_type VARCHAR(60) NOT NULL,
                invoice_number VARCHAR(50),
                status_before VARCHAR(30),
                status_after VARCHAR(30),
                payload_json TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                INDEX idx_invoice_history_invoice (invoice_id, created_at),
                INDEX idx_invoice_history_company (company_id, created_at)
            )'
        );
    }

    private function ensureCompanyColumn(string $column, string $definition): void
    {
        if ($this->columnExists('companies', $column)) {
            return;
        }

        $this->execute(sprintf('ALTER TABLE companies ADD COLUMN %s %s', $column, $definition));
    }

    private function ensureTableColumn(string $table, string $column, string $definition): void
    {
        if ($this->columnExists($table, $column)) {
            return;
        }

        $this->execute(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
    }

    private function columnExists(string $table, string $column): bool
    {
        $driver = strtolower((string) \Config::DB_DRIVER);

        if ($driver === 'sqlite') {
            $columns = $this->fetchAll(sprintf('PRAGMA table_info(%s)', $table));
            foreach ($columns as $row) {
                if (($row['name'] ?? '') === $column) {
                    return true;
                }
            }
            return false;
        }

        $row = $this->fetchOne(
            'SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = :schema
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name
             LIMIT 1',
            [
                'schema' => \Config::DB_NAME,
                'table_name' => $table,
                'column_name' => $column,
            ]
        );

        return $row !== null;
    }
}
