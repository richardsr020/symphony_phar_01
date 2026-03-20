<?php

namespace App\Core;

class RolePermissions
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_CASHIER = 'caissier';
    public const ROLE_STOREKEEPER = 'magasinier';
    public const LEGACY_ROLE_USER = 'user';

    public static function normalizeRole(string $role): string
    {
        $normalized = strtolower(trim($role));
        if ($normalized === self::LEGACY_ROLE_USER || $normalized === '') {
            return self::ROLE_CASHIER;
        }

        if (in_array($normalized, self::allowedRoles(), true)) {
            return $normalized;
        }

        return self::ROLE_CASHIER;
    }

    public static function allowedRoles(): array
    {
        return [self::ROLE_ADMIN, self::ROLE_CASHIER, self::ROLE_STOREKEEPER];
    }

    public static function defaultRole(): string
    {
        return self::ROLE_CASHIER;
    }

    public static function label(string $role): string
    {
        $normalized = self::normalizeRole($role);
        if ($normalized === self::ROLE_ADMIN) {
            return 'Administrateur';
        }
        if ($normalized === self::ROLE_STOREKEEPER) {
            return 'Magasinier';
        }
        return 'Caissier';
    }

    public static function shortLabel(string $role): string
    {
        $normalized = self::normalizeRole($role);
        if ($normalized === self::ROLE_ADMIN) {
            return 'Admin';
        }
        if ($normalized === self::ROLE_STOREKEEPER) {
            return 'Magasinier';
        }
        return 'Caissier';
    }

    public static function canAccessDashboard(string $role): bool
    {
        return true;
    }

    public static function canAccessTransactions(string $role): bool
    {
        return self::normalizeRole($role) !== self::ROLE_STOREKEEPER;
    }

    public static function canViewTransactionHistory(string $role): bool
    {
        return self::normalizeRole($role) !== self::ROLE_STOREKEEPER;
    }

    public static function canManageTransactions(string $role): bool
    {
        $normalized = self::normalizeRole($role);
        return $normalized === self::ROLE_ADMIN || $normalized === self::ROLE_CASHIER;
    }

    public static function canAccessInvoices(string $role): bool
    {
        return true;
    }

    public static function canManageInvoices(string $role): bool
    {
        $normalized = self::normalizeRole($role);
        return $normalized === self::ROLE_ADMIN || $normalized === self::ROLE_CASHIER;
    }

    public static function canAccessStock(string $role): bool
    {
        return true;
    }

    public static function canManageStock(string $role): bool
    {
        $normalized = self::normalizeRole($role);
        return $normalized === self::ROLE_ADMIN || $normalized === self::ROLE_STOREKEEPER;
    }

    public static function canAccessReports(string $role): bool
    {
        return true;
    }

    public static function canAccessChat(string $role): bool
    {
        return true;
    }

    public static function canAccessSettings(string $role): bool
    {
        return self::normalizeRole($role) === self::ROLE_ADMIN;
    }

    public static function canUseAiTool(string $role, string $toolName): bool
    {
        $normalizedRole = self::normalizeRole($role);
        if ($normalizedRole === self::ROLE_ADMIN) {
            return true;
        }

        $tool = strtolower(trim($toolName));
        if ($tool === '') {
            return true;
        }

        if ($normalizedRole === self::ROLE_CASHIER) {
            return !in_array($tool, [
                'stock.product.create',
                'stock.adjust',
            ], true);
        }

        if ($normalizedRole === self::ROLE_STOREKEEPER) {
            if ($tool === 'transactions.recent') {
                return false;
            }

            return !in_array($tool, [
                'transactions.create',
                'invoices.register_payment',
            ], true);
        }

        return false;
    }

    public static function aiToolDeniedMessage(string $role, string $toolName): string
    {
        $normalizedRole = self::normalizeRole($role);
        $tool = strtolower(trim($toolName));

        if ($normalizedRole === self::ROLE_CASHIER && str_starts_with($tool, 'stock.')) {
            return 'Action refusee: en tant que caissier, vous avez un acces lecture seule au stock.';
        }

        if ($normalizedRole === self::ROLE_STOREKEEPER && $tool === 'transactions.recent') {
            return 'Action refusee: en tant que magasinier, vous n avez pas acces a l historique des transactions.';
        }

        if ($normalizedRole === self::ROLE_STOREKEEPER) {
            return 'Action refusee: en tant que magasinier, vous ne pouvez pas executer cette operation hors gestion de stock.';
        }

        return 'Action refusee: permission insuffisante pour cette operation.';
    }
}
