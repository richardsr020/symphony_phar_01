<?php

use App\Core\MigrationContext;

return [
    'description' => 'Add per-company product creation form settings (base units, forms, field toggles).',
    'up' => static function (MigrationContext $context): void {
        if ($context->tableExists('company_product_form_settings')) {
            return;
        }

        if ($context->driver() === 'sqlite') {
            $context->execute(
                'CREATE TABLE company_product_form_settings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    company_id INTEGER NOT NULL,
                    config_json TEXT NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(company_id),
                    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
                )'
            );
            $context->execute('CREATE INDEX IF NOT EXISTS idx_company_product_form_settings_company ON company_product_form_settings(company_id)');
            return;
        }

        $context->execute(
            'CREATE TABLE company_product_form_settings (
                id INT PRIMARY KEY AUTO_INCREMENT,
                company_id INT NOT NULL,
                config_json LONGTEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_company_product_form_settings_company (company_id),
                INDEX idx_company_product_form_settings_company (company_id),
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            )'
        );
    },
];

