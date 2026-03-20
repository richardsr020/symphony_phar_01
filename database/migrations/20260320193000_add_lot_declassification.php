<?php

use App\Core\MigrationContext;

return [
    'description' => 'Add declassification fields to stock_lots table.',
    'up' => static function (MigrationContext $context): void {
        $flagDefinition = $context->driver() === 'sqlite'
            ? 'INTEGER NOT NULL DEFAULT 0'
            : 'TINYINT(1) NOT NULL DEFAULT 0';
        $dateDefinition = $context->driver() === 'sqlite'
            ? 'TEXT NULL'
            : 'DATETIME NULL';

        $context->addColumnIfNotExists('stock_lots', 'is_declassified', $flagDefinition);
        $context->addColumnIfNotExists('stock_lots', 'declassified_at', $dateDefinition);
    },
];
