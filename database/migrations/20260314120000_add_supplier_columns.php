<?php

use App\Core\MigrationContext;

return [
    'description' => 'Add supplier column on products and stock_lots tables.',
    'up' => static function (MigrationContext $context): void {
        $definition = $context->driver() === 'sqlite' ? 'TEXT NULL' : 'VARCHAR(160) NULL';
        $context->addColumnIfNotExists('products', 'supplier', $definition);
        $context->addColumnIfNotExists('stock_lots', 'supplier', $definition);
    },
];
