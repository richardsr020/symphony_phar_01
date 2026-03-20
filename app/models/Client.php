<?php

namespace App\Models;

class Client extends Model
{
    public function createFromPayload(int $companyId, int $userId, array $payload): int
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $phoneRaw = trim((string) ($payload['phone'] ?? ''));
        $phone = $this->normalizePhone($phoneRaw);
        $email = trim((string) ($payload['email'] ?? ''));
        $address = trim((string) ($payload['address'] ?? ''));

        if ($name === '' && $phone === '') {
            throw new \InvalidArgumentException('Nom ou telephone requis.');
        }

        if ($name === '' && $phone !== '') {
            $name = $phone;
        } elseif ($name === '') {
            $name = 'Client';
        }

        $identity = $this->buildClientIdentity($name, $phone);

        $this->db->execute(
            'INSERT INTO clients (company_id, name, phone, email, address, client_identity, is_active, created_by)
             VALUES (:company_id, :name, :phone, :email, :address, :client_identity, :is_active, :created_by)',
            [
                'company_id' => $companyId,
                'name' => $name !== '' ? substr($name, 0, 180) : null,
                'phone' => $phone !== '' ? substr($phone, 0, 40) : null,
                'email' => $email !== '' ? substr($email, 0, 180) : null,
                'address' => $address !== '' ? $address : null,
                'client_identity' => $identity !== '' ? substr($identity, 0, 260) : null,
                'is_active' => 1,
                'created_by' => $userId > 0 ? $userId : null,
            ]
        );

        return $this->db->lastInsertId();
    }

    private function buildClientIdentity(string $name, string $phone): string
    {
        $label = trim(($phone !== '' ? $phone . ' ' : '') . $name);
        $label = preg_replace('/\s+/', ' ', $label) ?? $label;
        return trim($label);
    }

    private function normalizePhone(string $value): string
    {
        $digits = preg_replace('/[^0-9]+/', '', trim($value));
        return is_string($digits) ? $digits : '';
    }
}
