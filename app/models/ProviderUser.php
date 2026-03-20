<?php

namespace App\Models;

class ProviderUser extends Model
{
    public function findByEmail(string $email): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, email, password_hash, full_name, is_active, last_login
             FROM provider_users
             WHERE email = :email
             LIMIT 1',
            ['email' => $email]
        );
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, email, full_name, is_active, last_login, created_at
             FROM provider_users
             WHERE id = :id
             LIMIT 1',
            ['id' => $id]
        );
    }

    public function updateLastLogin(int $id): void
    {
        $this->db->execute(
            'UPDATE provider_users
             SET last_login = CURRENT_TIMESTAMP
             WHERE id = :id',
            ['id' => $id]
        );
    }
}
