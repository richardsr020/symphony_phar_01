<?php

use App\Core\MigrationContext;

return [
    'description' => 'Add customer_description column to invoices.',
    'up' => static function (MigrationContext $context): void {
        $definition = $context->driver() === 'sqlite' ? 'TEXT NULL' : 'TEXT NULL';
        $context->addColumnIfNotExists('invoices', 'customer_description', $definition);
    },
];

