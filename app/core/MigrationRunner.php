<?php

namespace App\Core;

class MigrationRunner
{
    private Database $database;
    private MigrationContext $context;
    private string $driver;
    private string $migrationsPath;
    private string $lockFile;

    public function __construct(?Database $database = null, ?string $migrationsPath = null, ?string $lockFile = null)
    {
        $this->database = $database ?? Database::getInstance();
        $this->driver = strtolower((string) \Config::DB_DRIVER);
        $rootPath = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
        $defaultMigrationsPath = defined('Config::DB_MIGRATIONS_DIR')
            ? (string) \Config::DB_MIGRATIONS_DIR
            : $rootPath . '/database/migrations';
        $defaultLockFile = defined('Config::DB_MIGRATION_LOCK_FILE')
            ? (string) \Config::DB_MIGRATION_LOCK_FILE
            : $rootPath . '/storage/cache/migrations.lock';

        $this->migrationsPath = $migrationsPath ?? $defaultMigrationsPath;
        $this->lockFile = $lockFile ?? $defaultLockFile;
        $this->context = new MigrationContext($this->database, $this->driver);
    }

    public function runPending(): void
    {
        if (!is_dir($this->migrationsPath)) {
            return;
        }

        $lockHandle = $this->acquireLock();
        try {
            $this->ensureMigrationsTable();
            $migrationFiles = $this->discoverMigrationFiles();
            if ($migrationFiles === []) {
                return;
            }

            $appliedMigrations = $this->loadAppliedMigrations();
            $batch = $this->nextBatchNumber();

            foreach ($migrationFiles as $migrationFile) {
                $migrationName = pathinfo($migrationFile, PATHINFO_FILENAME);
                $checksums = $this->computeChecksums($migrationFile, $migrationName);
                $rawChecksum = $checksums['raw'];
                $normalizedChecksum = $checksums['normalized'];

                if (isset($appliedMigrations[$migrationName])) {
                    $storedChecksum = $appliedMigrations[$migrationName];
                    if ($storedChecksum !== $rawChecksum && $storedChecksum !== $normalizedChecksum) {
                        throw new \RuntimeException(
                            'Migration deja appliquee avec contenu different: ' . $migrationName
                        );
                    }
                    continue;
                }

                $definition = $this->loadMigrationDefinition($migrationFile, $migrationName);
                $this->applyMigration(
                    $migrationName,
                    $normalizedChecksum,
                    (string) ($definition['description'] ?? $migrationName),
                    $definition['up'],
                    $batch
                );
            }
        } finally {
            $this->releaseLock($lockHandle);
        }
    }

    private function ensureMigrationsTable(): void
    {
        if ($this->driver === 'sqlite') {
            $this->database->execute(
                'CREATE TABLE IF NOT EXISTS schema_migrations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration TEXT NOT NULL UNIQUE,
                    checksum TEXT NOT NULL,
                    description TEXT NULL,
                    batch INTEGER NOT NULL DEFAULT 1,
                    executed_at TEXT DEFAULT CURRENT_TIMESTAMP
                )'
            );
            $this->database->execute('CREATE INDEX IF NOT EXISTS idx_schema_migrations_batch ON schema_migrations(batch, id)');
            return;
        }

        $this->database->execute(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                migration VARCHAR(190) NOT NULL UNIQUE,
                checksum VARCHAR(64) NOT NULL,
                description VARCHAR(255) NULL,
                batch INT NOT NULL DEFAULT 1,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_schema_migrations_batch (batch, id)
            )'
        );
    }

    private function discoverMigrationFiles(): array
    {
        $files = glob(rtrim($this->migrationsPath, '/\\') . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files, SORT_STRING);
        return $files;
    }

    private function loadAppliedMigrations(): array
    {
        $rows = $this->database->fetchAll(
            'SELECT migration, checksum
             FROM schema_migrations
             ORDER BY id ASC'
        );

        $applied = [];
        foreach ($rows as $row) {
            $name = (string) ($row['migration'] ?? '');
            if ($name === '') {
                continue;
            }

            $applied[$name] = (string) ($row['checksum'] ?? '');
        }

        return $applied;
    }

    private function nextBatchNumber(): int
    {
        $row = $this->database->fetchOne('SELECT MAX(batch) AS max_batch FROM schema_migrations');
        return ((int) ($row['max_batch'] ?? 0)) + 1;
    }

    private function loadMigrationDefinition(string $migrationFile, string $migrationName): array
    {
        $definition = require $migrationFile;

        if (is_callable($definition)) {
            return [
                'description' => $migrationName,
                'up' => $definition,
            ];
        }

        if (!is_array($definition) || !isset($definition['up']) || !is_callable($definition['up'])) {
            throw new \RuntimeException(
                'Migration invalide (attendu callable ou [\'up\' => callable]): ' . $migrationName
            );
        }

        return [
            'description' => (string) ($definition['description'] ?? $migrationName),
            'up' => $definition['up'],
        ];
    }

    private function computeChecksums(string $migrationFile, string $migrationName): array
    {
        $raw = (string) hash_file('sha256', $migrationFile);
        if ($raw === '') {
            throw new \RuntimeException('Impossible de calculer le checksum migration: ' . $migrationName);
        }

        $contents = @file_get_contents($migrationFile);
        if ($contents === false) {
            throw new \RuntimeException('Impossible de lire la migration: ' . $migrationName);
        }

        $normalizedContents = str_replace(["\r\n", "\r"], "\n", $contents);
        $normalized = hash('sha256', $normalizedContents);

        return [
            'raw' => $raw,
            'normalized' => $normalized,
        ];
    }

    private function applyMigration(
        string $migrationName,
        string $checksum,
        string $description,
        callable $up,
        int $batch
    ): void {
        $executedAt = date('Y-m-d H:i:s');
        $this->logInfo('Applying migration', [
            'migration' => $migrationName,
            'batch' => $batch,
        ]);

        $useTransaction = $this->driver === 'sqlite';
        if ($useTransaction) {
            $this->database->beginTransaction();
        }
        try {
            $up($this->context);

            $this->database->execute(
                'INSERT INTO schema_migrations (migration, checksum, description, batch, executed_at)
                 VALUES (:migration, :checksum, :description, :batch, :executed_at)',
                [
                    'migration' => $migrationName,
                    'checksum' => $checksum,
                    'description' => $description !== '' ? $description : null,
                    'batch' => $batch,
                    'executed_at' => $executedAt,
                ]
            );

            if ($useTransaction) {
                $this->database->commit();
            }
        } catch (\Throwable $exception) {
            if ($useTransaction && $this->database->getConnection()->inTransaction()) {
                $this->database->rollback();
            }

            $this->logError('Migration failed', [
                'migration' => $migrationName,
                'message' => $exception->getMessage(),
            ]);

            throw new \RuntimeException(
                'Echec migration ' . $migrationName . ': ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        $this->logInfo('Migration applied', [
            'migration' => $migrationName,
            'batch' => $batch,
        ]);
    }

    private function acquireLock()
    {
        $lockDir = dirname($this->lockFile);
        if (!is_dir($lockDir)) {
            @mkdir($lockDir, 0775, true);
        }

        $handle = @fopen($this->lockFile, 'c+');
        if ($handle === false) {
            throw new \RuntimeException('Impossible de creer le fichier de verrou migration: ' . $this->lockFile);
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new \RuntimeException('Impossible d obtenir le verrou migration.');
        }

        return $handle;
    }

    private function releaseLock($handle): void
    {
        if (!is_resource($handle)) {
            return;
        }

        @flock($handle, LOCK_UN);
        @fclose($handle);
    }

    private function logInfo(string $message, array $context = []): void
    {
        if (class_exists('\\App\\Core\\AppLogger')) {
            AppLogger::info($message, $context);
        }
    }

    private function logError(string $message, array $context = []): void
    {
        if (class_exists('\\App\\Core\\AppLogger')) {
            AppLogger::error($message, $context);
        }
    }
}
