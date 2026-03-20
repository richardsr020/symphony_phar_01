<?php

namespace App\Models;

class Account extends Model
{
    public function getByCompany(int $companyId): array
    {
        return $this->db->fetchAll(
            'SELECT id, code, name, type
             FROM accounts
             WHERE company_id = :company_id
             ORDER BY code ASC',
            ['company_id' => $companyId]
        );
    }

    public function findById(int $companyId, int $accountId): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, code, name, type
             FROM accounts
             WHERE company_id = :company_id
               AND id = :id
             LIMIT 1',
            [
                'company_id' => $companyId,
                'id' => $accountId,
            ]
        );
    }

    public function getOrCreateSystemAccount(int $companyId, string $type): int
    {
        $normalizedType = in_array($type, ['asset', 'liability', 'equity', 'revenue', 'expense'], true)
            ? $type
            : 'expense';

        $codeByType = [
            'asset' => 'SYS-ASS',
            'liability' => 'SYS-LIA',
            'equity' => 'SYS-EQU',
            'revenue' => 'SYS-REV',
            'expense' => 'SYS-EXP',
        ];

        $baseCode = $codeByType[$normalizedType];
        $account = $this->db->fetchOne(
            'SELECT id
             FROM accounts
             WHERE company_id = :company_id
               AND code = :code
             LIMIT 1',
            [
                'company_id' => $companyId,
                'code' => $baseCode,
            ]
        );

        if ($account !== null) {
            return (int) $account['id'];
        }

        $this->db->execute(
            'INSERT INTO accounts (company_id, code, name, type, category, is_active)
             VALUES (:company_id, :code, :name, :type, :category, :is_active)',
            [
                'company_id' => $companyId,
                'code' => $baseCode,
                'name' => 'Compte système ' . strtoupper($normalizedType),
                'type' => $normalizedType,
                'category' => 'system',
                'is_active' => 1,
            ]
        );

        return $this->db->lastInsertId();
    }
}
