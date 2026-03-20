-- database/schema.sql
-- Conçu pour être compatible avec tout type de business

-- Entreprises (multi-companies)
CREATE TABLE companies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    legal_name VARCHAR(255),
    tax_id VARCHAR(100),
    email VARCHAR(255),
    phone VARCHAR(50),
    address TEXT,
    city VARCHAR(100),
    country VARCHAR(100) DEFAULT 'RDC',
    currency VARCHAR(3) DEFAULT 'USD',
    fiscal_year_start DATE,
    subscription_status ENUM('active', 'past_due', 'suspended') DEFAULT 'active',
    subscription_ends_at DATE,
    app_locked BOOLEAN DEFAULT FALSE,
    lock_reason TEXT,
    locked_at TIMESTAMP NULL,
    last_reminder_at TIMESTAMP NULL,
    provider_callback_url VARCHAR(500),
    reminder_interval_days INT DEFAULT 1,
    auto_subscription_enabled BOOLEAN DEFAULT TRUE,
    fiscal_period_duration_months INT DEFAULT 12,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Utilisateurs
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    role ENUM('admin', 'caissier', 'magasinier') DEFAULT 'caissier',
    avatar VARCHAR(255),
    language VARCHAR(5) DEFAULT 'fr',
    theme VARCHAR(10) DEFAULT 'light',
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- Plan comptable universel
CREATE TABLE accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('asset', 'liability', 'equity', 'revenue', 'expense') NOT NULL,
    category VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_code_per_company (company_id, code),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- Périodes d'exercice comptable
CREATE TABLE fiscal_periods (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    label VARCHAR(120) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_closed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_fiscal_period_company_dates (company_id, start_date, end_date)
);

-- Transactions
CREATE TABLE transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    transaction_date DATE NOT NULL,
    description TEXT,
    reference VARCHAR(100),
    type ENUM('income', 'expense', 'transfer', 'journal') NOT NULL,
    expense_subcategory VARCHAR(60) NULL,
    expense_fiscal_subcategory VARCHAR(60) NULL,
    expense_subcategory_other VARCHAR(255) NULL,
    status ENUM('draft', 'posted', 'void') DEFAULT 'draft',
    fiscal_period_id INT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (fiscal_period_id) REFERENCES fiscal_periods(id) ON DELETE SET NULL,
    INDEX idx_date (transaction_date)
);

-- Lignes d'écritures (double partie)
CREATE TABLE journal_entries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id INT NOT NULL,
    account_id INT NOT NULL,
    debit DECIMAL(15,2) DEFAULT 0,
    credit DECIMAL(15,2) DEFAULT 0,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id),
    CHECK (debit * credit = 0 AND (debit > 0 OR credit > 0))
);

-- Factures
CREATE TABLE invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    invoice_number VARCHAR(50) NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    customer_name VARCHAR(255),
    customer_tax_id VARCHAR(100),
    customer_address TEXT,
    subtotal DECIMAL(15,2) NOT NULL,
    tax_rate DECIMAL(5,2) DEFAULT 16,
    tax_amount DECIMAL(15,2),
    total DECIMAL(15,2) NOT NULL,
    paid_amount DECIMAL(15,2) DEFAULT 0,
    status ENUM('draft', 'sent', 'paid', 'overdue', 'cancelled') DEFAULT 'draft',
    fiscal_period_id INT NULL,
    paid_date DATE,
    notes TEXT,
    downloaded_at TIMESTAMP NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_invoice_per_company (company_id, invoice_number),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (fiscal_period_id) REFERENCES fiscal_periods(id) ON DELETE SET NULL
);


-- Lignes de facture
CREATE TABLE invoice_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
    tax_rate DECIMAL(6,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    total DECIMAL(15,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    INDEX idx_invoice_items_invoice_id (invoice_id)
);

-- Historique des factures (audit)
CREATE TABLE invoice_history (
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
);

-- Catégories personnalisables
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('income', 'expense', 'both') DEFAULT 'both',
    color VARCHAR(7) DEFAULT '#4F46E5',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- Clients (referentiel manuel)
CREATE TABLE clients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    name VARCHAR(180),
    phone VARCHAR(40),
    email VARCHAR(180),
    address TEXT,
    client_identity VARCHAR(260),
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY idx_clients_company_phone (company_id, phone),
    INDEX idx_clients_company (company_id),
    INDEX idx_clients_identity (company_id, client_identity)
);

-- Mémoire IA
CREATE TABLE ai_memory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    context_summary TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Alertes
CREATE TABLE alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    severity ENUM('info', 'warning', 'critical') DEFAULT 'info',
    title VARCHAR(255) NOT NULL,
    message TEXT,
    data JSON,
    is_resolved BOOLEAN DEFAULT FALSE,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- Logs IA
CREATE TABLE ai_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    message TEXT,
    tool_called VARCHAR(100),
    result TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Logs d'audit
CREATE TABLE audit_logs (
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
    INDEX idx_action (action),
    INDEX idx_created (created_at)
);

-- Utilisateurs fournisseur (NestCorporation)
CREATE TABLE provider_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Clés API fournisseur (automatisation NestCorporation)
CREATE TABLE provider_api_keys (
    id INT PRIMARY KEY AUTO_INCREMENT,
    key_name VARCHAR(120) NOT NULL,
    key_hash VARCHAR(128) UNIQUE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Messages de relance envoyés par le fournisseur
CREATE TABLE provider_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    provider_user_id INT NULL,
    channel ENUM('dashboard', 'api', 'system') DEFAULT 'dashboard',
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_user_id) REFERENCES provider_users(id) ON DELETE SET NULL
);

-- Journal des actions fournisseur (lock / unlock / reminder)
CREATE TABLE provider_actions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    provider_user_id INT NULL,
    action ENUM('reminder_sent', 'locked', 'unlocked') NOT NULL,
    details TEXT,
    source ENUM('dashboard', 'api') DEFAULT 'dashboard',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_user_id) REFERENCES provider_users(id) ON DELETE SET NULL
);

-- Clés de licence d'abonnement (anti double usage + historique)
CREATE TABLE subscription_licenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    period_end_date DATE NOT NULL,
    license_hash VARCHAR(128) UNIQUE NOT NULL,
    status ENUM('generated', 'sent', 'activated', 'expired') DEFAULT 'generated',
    webhook_url VARCHAR(500),
    webhook_response TEXT,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    activated_at TIMESTAMP NULL,
    expires_at DATE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- Historique technique du cycle de réabonnement
CREATE TABLE subscription_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    details TEXT,
    source ENUM('system', 'dashboard', 'api') DEFAULT 'system',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_subscription_event_type (event_type),
    INDEX idx_subscription_event_created (created_at)
);
