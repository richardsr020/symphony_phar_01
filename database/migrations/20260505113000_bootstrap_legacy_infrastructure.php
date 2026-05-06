<?php

use App\Core\MigrationContext;

return [
    'description' => 'Bootstrap legacy infrastructure (ensure missing tables/columns) for older databases.',
    'up' => static function (MigrationContext $context): void {
        // Only "upgrade" existing installations. In production, DB_AUTO_INIT may be disabled.
        // This keeps behavior safe for empty databases unless explicit init is enabled.
        if (!$context->tableExists('users')) {
            return;
        }

        if (class_exists('\\App\\Core\\Database')) {
            \App\Core\Database::initializeSchema();
        }
    },
];

