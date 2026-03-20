<?php

use App\Core\MigrationContext;

return [
    'description' => 'Allow debt_payment transaction type.',
    'up' => static function (MigrationContext $context): void {
        if ($context->driver() !== 'mysql') {
            return;
        }

        $context->execute(
            "ALTER TABLE transactions MODIFY type ENUM('income','expense','transfer','journal','debt_payment') NOT NULL"
        );
    },
];
