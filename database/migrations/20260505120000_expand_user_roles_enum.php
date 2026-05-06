<?php

use App\Core\MigrationContext;

return [
    'description' => 'Ensure users.role supports admin/caissier/magasinier (legacy user -> caissier).',
    'up' => static function (MigrationContext $context): void {
        if ($context->driver() !== 'mysql') {
            return;
        }

        if (!$context->tableExists('users') || !$context->columnExists('users', 'role')) {
            return;
        }

        $row = $context->fetchOne(
            'SELECT COLUMN_TYPE AS column_type
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = :schema
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name
             LIMIT 1',
            [
                'schema' => \Config::DB_NAME,
                'table_name' => 'users',
                'column_name' => 'role',
            ]
        );

        $columnType = strtolower((string) ($row['column_type'] ?? ''));
        if ($columnType === '' || strpos($columnType, 'enum(') === false) {
            return;
        }

        $hasCashier = strpos($columnType, "'caissier'") !== false;
        $hasStorekeeper = strpos($columnType, "'magasinier'") !== false;
        if ($hasCashier && $hasStorekeeper) {
            return;
        }

        $context->execute("UPDATE users SET role = 'caissier' WHERE role IS NULL OR role = '' OR role = 'user'");
        $context->execute("ALTER TABLE users MODIFY role ENUM('admin','caissier','magasinier') DEFAULT 'caissier'");
    },
];
