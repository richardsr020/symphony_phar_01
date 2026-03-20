<?php

namespace App\Models;

class ProviderApiKey extends Model
{
    public function findActiveByRawKey(string $rawKey): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, key_name, is_active
             FROM provider_api_keys
             WHERE key_hash = :key_hash
               AND is_active = :is_active
             LIMIT 1',
            [
                'key_hash' => hash('sha256', $rawKey),
                'is_active' => 1,
            ]
        );
    }

    public function touchLastUsed(int $id): void
    {
        $this->db->execute(
            'UPDATE provider_api_keys
             SET last_used_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            ['id' => $id]
        );
    }
}
