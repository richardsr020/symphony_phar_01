<?php

use App\Core\MigrationContext;

return [
    'description' => 'Add downloaded_at column on invoices table.',
    'up' => static function (MigrationContext $context): void {
        $definition = $context->driver() === 'sqlite' ? 'TEXT NULL' : 'TIMESTAMP NULL';
        $context->addColumnIfNotExists('invoices', 'downloaded_at', $definition);
    },
];
