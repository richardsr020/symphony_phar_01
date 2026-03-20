<?php

use App\Core\MigrationContext;

return [
    'description' => 'Add customer_phone to invoices and create invoice payment allocations table.',
    'up' => static function (MigrationContext $context): void {
        $phoneDefinition = $context->driver() === 'sqlite' ? 'TEXT NULL' : 'VARCHAR(60) NULL';
        $context->addColumnIfNotExists('invoices', 'customer_phone', $phoneDefinition);

        if ($context->tableExists('invoice_payment_allocations')) {
            return;
        }

        if ($context->driver() === 'sqlite') {
            $context->execute(
                'CREATE TABLE invoice_payment_allocations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    company_id INTEGER NOT NULL,
                    transaction_id INTEGER NOT NULL,
                    invoice_id INTEGER NOT NULL,
                    payment_group_ref TEXT NOT NULL,
                    receipt_number TEXT NOT NULL,
                    client_name TEXT NULL,
                    client_phone TEXT NULL,
                    amount NUMERIC NOT NULL DEFAULT 0,
                    created_by INTEGER NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
                    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                )'
            );
            $context->execute('CREATE INDEX idx_invoice_payment_allocations_transaction ON invoice_payment_allocations(transaction_id)');
            $context->execute('CREATE INDEX idx_invoice_payment_allocations_invoice ON invoice_payment_allocations(invoice_id)');
            $context->execute('CREATE INDEX idx_invoice_payment_allocations_group_ref ON invoice_payment_allocations(payment_group_ref)');
            return;
        }

        $context->execute(
            'CREATE TABLE invoice_payment_allocations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                company_id INT NOT NULL,
                transaction_id INT NOT NULL,
                invoice_id INT NOT NULL,
                payment_group_ref VARCHAR(80) NOT NULL,
                receipt_number VARCHAR(80) NOT NULL,
                client_name VARCHAR(255) NULL,
                client_phone VARCHAR(60) NULL,
                amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
                FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_invoice_payment_allocations_transaction (transaction_id),
                INDEX idx_invoice_payment_allocations_invoice (invoice_id),
                INDEX idx_invoice_payment_allocations_group_ref (payment_group_ref)
            )'
        );
    },
];
