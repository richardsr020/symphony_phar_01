<?php

namespace App\Models;

use App\Core\RolePermissions;
use RuntimeException;

class User extends Model
{
    public function hasUsers(): bool
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) AS total FROM users');
        return ((int) ($row['total'] ?? 0)) > 0;
    }

    public function hasAdmin(): bool
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM users WHERE role = :role',
            ['role' => 'admin']
        );

        return ((int) ($row['total'] ?? 0)) > 0;
    }

    public function countActiveAdminsByCompany(int $companyId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total
             FROM users
             WHERE company_id = :company_id
               AND role = :role
               AND is_active = 1',
            [
                'company_id' => $companyId,
                'role' => 'admin',
            ]
        );

        return (int) ($row['total'] ?? 0);
    }

    public function findByMatricule(string $matricule): ?array
    {
        return $this->db->fetchOne(
            'SELECT u.*, u.email AS matricule, c.name AS company_name
             FROM users u
             INNER JOIN companies c ON c.id = u.company_id
             WHERE u.email = :matricule
             LIMIT 1',
            ['matricule' => $matricule]
        );
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT u.*, u.email AS matricule, c.name AS company_name
             FROM users u
             INNER JOIN companies c ON c.id = u.company_id
             WHERE u.id = :id
             LIMIT 1',
            ['id' => $id]
        );
    }

    public function getUsersByCompany(int $companyId): array
    {
        return $this->db->fetchAll(
            'SELECT id, first_name, last_name, email AS matricule, role, is_active, created_at, last_login
             FROM users
             WHERE company_id = :company_id
             ORDER BY created_at ASC',
            ['company_id' => $companyId]
        );
    }

    public function createInitialAdmin(array $payload): int
    {
        $passwordHash = password_hash(
            (string) $payload['password'],
            PASSWORD_BCRYPT,
            ['cost' => \Config::PASSWORD_COST]
        );

        if ($passwordHash === false) {
            throw new RuntimeException('Impossible de générer le hash du mot de passe.');
        }

        $companyEmail = trim((string) ($payload['company_email'] ?? ''));
        $this->db->beginTransaction();

        try {
            $this->db->execute(
                'INSERT INTO companies (name, legal_name, email, phone, country, currency)
                 VALUES (:name, :legal_name, :email, :phone, :country, :currency)',
                [
                    'name' => $payload['company_name'],
                    'legal_name' => $payload['company_name'],
                    'email' => $companyEmail !== '' ? $companyEmail : null,
                    'phone' => $payload['phone'] ?: null,
                    'country' => 'RDC',
                    'currency' => \Config::CURRENCY,
                ]
            );

            $companyId = $this->db->lastInsertId();

            $this->db->execute(
                'INSERT INTO users (company_id, email, password_hash, first_name, last_name, role, language, theme, is_active)
                 VALUES (:company_id, :email, :password_hash, :first_name, :last_name, :role, :language, :theme, :is_active)',
                [
                    'company_id' => $companyId,
                    'email' => $payload['matricule'],
                    'password_hash' => $passwordHash,
                    'first_name' => $payload['first_name'],
                    'last_name' => $payload['last_name'],
                    'role' => 'admin',
                    'language' => 'fr',
                    'theme' => 'light',
                    'is_active' => 1,
                ]
            );

            $userId = $this->db->lastInsertId();
            $this->db->commit();

            return $userId;
        } catch (\Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function createUserForCompany(int $companyId, array $payload): int
    {
        $passwordHash = password_hash(
            (string) $payload['password'],
            PASSWORD_BCRYPT,
            ['cost' => \Config::PASSWORD_COST]
        );

        if ($passwordHash === false) {
            throw new RuntimeException('Impossible de générer le hash du mot de passe.');
        }

        $role = RolePermissions::normalizeRole((string) ($payload['role'] ?? RolePermissions::defaultRole()));

        $this->db->execute(
            'INSERT INTO users (company_id, email, password_hash, first_name, last_name, role, language, theme, is_active)
             VALUES (:company_id, :email, :password_hash, :first_name, :last_name, :role, :language, :theme, :is_active)',
            [
                'company_id' => $companyId,
                'email' => $payload['matricule'],
                'password_hash' => $passwordHash,
                'first_name' => $payload['first_name'],
                'last_name' => $payload['last_name'],
                'role' => $role,
                'language' => 'fr',
                'theme' => 'light',
                'is_active' => 1,
            ]
        );

        return $this->db->lastInsertId();
    }

    public function updateLastLogin(int $userId): void
    {
        $this->db->execute(
            'UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id',
            ['id' => $userId]
        );
    }

    public function updateProfile(int $userId, array $payload): bool
    {
        $firstName = trim((string) ($payload['first_name'] ?? ''));
        $lastName = trim((string) ($payload['last_name'] ?? ''));
        $matricule = trim((string) ($payload['matricule'] ?? ''));
        $language = trim((string) ($payload['language'] ?? 'fr'));
        $theme = trim((string) ($payload['theme'] ?? 'light'));
        $avatarProvided = array_key_exists('avatar', $payload);
        $avatar = trim((string) ($payload['avatar'] ?? ''));

        if ($firstName === '' || $lastName === '' || $matricule === '') {
            throw new \InvalidArgumentException('Champs profil obligatoires manquants.');
        }

        if (!in_array($language, ['fr', 'en'], true)) {
            $language = 'fr';
        }

        if (!in_array($theme, ['light', 'dark'], true)) {
            $theme = 'light';
        }

        $existing = $this->db->fetchOne(
            'SELECT id
             FROM users
             WHERE email = :matricule
               AND id <> :id
             LIMIT 1',
            [
                'matricule' => $matricule,
                'id' => $userId,
            ]
        );

        if ($existing !== null) {
            throw new \InvalidArgumentException('Matricule déjà utilisé.');
        }

        if (!$avatarProvided) {
            $currentUser = $this->db->fetchOne(
                'SELECT avatar
                 FROM users
                 WHERE id = :id
                 LIMIT 1',
                ['id' => $userId]
            );
            $avatar = trim((string) ($currentUser['avatar'] ?? ''));
        }

        $this->db->execute(
            'UPDATE users
             SET first_name = :first_name,
                 last_name = :last_name,
                 email = :matricule,
                 language = :language,
                 theme = :theme,
                 avatar = :avatar
             WHERE id = :id',
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'matricule' => $matricule,
                'language' => $language,
                'theme' => $theme,
                'avatar' => $avatar !== '' ? $avatar : null,
                'id' => $userId,
            ]
        );

        return true;
    }

    public function updateRoleForCompany(int $companyId, int $userId, string $role): bool
    {
        $role = RolePermissions::normalizeRole($role);
        $this->db->execute(
            'UPDATE users
             SET role = :role
             WHERE id = :id
               AND company_id = :company_id',
            [
                'role' => $role,
                'id' => $userId,
                'company_id' => $companyId,
            ]
        );

        return true;
    }

    public function updateStatusForCompany(int $companyId, int $userId, bool $isActive): bool
    {
        $this->db->execute(
            'UPDATE users
             SET is_active = :is_active
             WHERE id = :id
               AND company_id = :company_id',
            [
                'is_active' => $isActive ? 1 : 0,
                'id' => $userId,
                'company_id' => $companyId,
            ]
        );

        return true;
    }

    public function resetPasswordForCompany(int $companyId, int $userId, string $newPassword): bool
    {
        $passwordHash = password_hash(
            $newPassword,
            PASSWORD_BCRYPT,
            ['cost' => \Config::PASSWORD_COST]
        );

        if ($passwordHash === false) {
            throw new RuntimeException('Impossible de générer le hash du mot de passe.');
        }

        $this->db->execute(
            'UPDATE users
             SET password_hash = :password_hash
             WHERE id = :id
               AND company_id = :company_id',
            [
                'password_hash' => $passwordHash,
                'id' => $userId,
                'company_id' => $companyId,
            ]
        );

        return true;
    }

    public function deleteForCompany(int $companyId, int $userId): bool
    {
        $pdo = $this->db->getConnection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $tables = [
                'transactions',
                'invoices',
                'products',
                'stock_movements',
                'stock_lots',
                'purchase_orders',
            ];

            foreach ($tables as $table) {
                $this->db->execute(
                    'UPDATE ' . $table . '
                     SET created_by = NULL
                     WHERE company_id = :company_id
                       AND created_by = :user_id',
                    [
                        'company_id' => $companyId,
                        'user_id' => $userId,
                    ]
                );
            }

            $this->db->execute(
                'DELETE FROM users
                 WHERE id = :id
                   AND company_id = :company_id',
                [
                    'id' => $userId,
                    'company_id' => $companyId,
                ]
            );

            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $this->db->rollback();
            }
            throw $exception;
        }

        return true;
    }
}
