<?php

namespace App\Models;

class Company extends Model
{
    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, name, legal_name, tax_id, email, phone, address, city, country, currency, fiscal_year_start, fiscal_period_duration_months,
                    invoice_logo_url, invoice_brand_color, default_tax_rate,
                    subscription_status, subscription_ends_at, app_locked, lock_reason, locked_at,
                    last_reminder_at, provider_callback_url, reminder_interval_days, auto_subscription_enabled,
                    created_at, updated_at
             FROM companies
             WHERE id = :id
             LIMIT 1',
            ['id' => $id]
        );
    }

    public function updateSettings(int $companyId, array $payload): bool
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $legalName = trim((string) ($payload['legal_name'] ?? ''));
        $taxId = trim((string) ($payload['tax_id'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $phone = trim((string) ($payload['phone'] ?? ''));
        $address = trim((string) ($payload['address'] ?? ''));
        $city = trim((string) ($payload['city'] ?? ''));
        $country = trim((string) ($payload['country'] ?? ''));
        $currency = 'USD';
        $hasLogoInPayload = array_key_exists('invoice_logo_url', $payload);
        $invoiceLogoUrl = trim((string) ($payload['invoice_logo_url'] ?? ''));
        $invoiceBrandColor = trim((string) ($payload['invoice_brand_color'] ?? ''));
        $defaultTaxRate = round((float) ($payload['default_tax_rate'] ?? 0), 2);

        if ($name === '' || $legalName === '') {
            throw new \InvalidArgumentException('Nom entreprise requis.');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email entreprise invalide.');
        }

        $currency = 'USD';
        if (!$hasLogoInPayload) {
            $existingCompany = $this->findById($companyId);
            $invoiceLogoUrl = trim((string) ($existingCompany['invoice_logo_url'] ?? ''));
        }

        if ($invoiceLogoUrl !== '') {
            $isRemoteUrl = filter_var($invoiceLogoUrl, FILTER_VALIDATE_URL) !== false;
            $isLocalUploadPath = preg_match('#^/public/uploads/[a-zA-Z0-9/_\.-]+$#', $invoiceLogoUrl) === 1;
            if (!$isRemoteUrl && !$isLocalUploadPath) {
                throw new \InvalidArgumentException('Logo entreprise invalide.');
            }
        }
        if ($invoiceBrandColor !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $invoiceBrandColor) !== 1) {
            throw new \InvalidArgumentException('Couleur marque invalide.');
        }
        $defaultTaxRate = max(0.0, min(100.0, $defaultTaxRate));

        $this->db->execute(
            'UPDATE companies
             SET name = :name,
                 legal_name = :legal_name,
                 tax_id = :tax_id,
                 email = :email,
                 phone = :phone,
                 address = :address,
                 city = :city,
                 country = :country,
                 currency = :currency,
                 invoice_logo_url = :invoice_logo_url,
                 invoice_brand_color = :invoice_brand_color,
                 default_tax_rate = :default_tax_rate
             WHERE id = :id',
            [
                'name' => $name,
                'legal_name' => $legalName,
                'tax_id' => $taxId !== '' ? $taxId : null,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
                'address' => $address !== '' ? $address : null,
                'city' => $city !== '' ? $city : null,
                'country' => $country !== '' ? $country : null,
                'currency' => $currency,
                'invoice_logo_url' => $invoiceLogoUrl !== '' ? $invoiceLogoUrl : null,
                'invoice_brand_color' => $invoiceBrandColor !== '' ? strtoupper($invoiceBrandColor) : null,
                'default_tax_rate' => $defaultTaxRate,
                'id' => $companyId,
            ]
        );

        return true;
    }

    public function getAllForProvider(): array
    {
        return $this->db->fetchAll(
            'SELECT id, name, email, subscription_status, subscription_ends_at, app_locked, lock_reason, locked_at,
                    last_reminder_at, provider_callback_url, reminder_interval_days, auto_subscription_enabled
             FROM companies
             ORDER BY created_at DESC'
        );
    }

    public function getCompaniesForAutomation(?int $companyId = null): array
    {
        if ($companyId !== null) {
            return $this->db->fetchAll(
                'SELECT id, name, email, subscription_status, subscription_ends_at, app_locked, lock_reason, locked_at,
                        last_reminder_at, provider_callback_url, reminder_interval_days, auto_subscription_enabled
                 FROM companies
                 WHERE id = :id
                   AND subscription_ends_at IS NOT NULL
                   AND auto_subscription_enabled = :enabled',
                [
                    'id' => $companyId,
                    'enabled' => 1,
                ]
            );
        }

        return $this->db->fetchAll(
            'SELECT id, name, email, subscription_status, subscription_ends_at, app_locked, lock_reason, locked_at,
                    last_reminder_at, provider_callback_url, reminder_interval_days, auto_subscription_enabled
             FROM companies
             WHERE subscription_ends_at IS NOT NULL
               AND auto_subscription_enabled = :enabled
             ORDER BY id ASC',
            ['enabled' => 1]
        );
    }

    public function isLockedForCompanyId(int $companyId): bool
    {
        $company = $this->findById($companyId);
        if ($company === null) {
            return false;
        }

        return ((int) ($company['app_locked'] ?? 0)) === 1
            || ((string) ($company['subscription_status'] ?? '') === 'suspended');
    }

    public function lockByProvider(int $companyId, string $reason, string $source, ?int $providerUserId = null): bool
    {
        $this->db->beginTransaction();

        try {
            $this->db->execute(
                'UPDATE companies
                 SET app_locked = 1,
                     subscription_status = :subscription_status,
                     lock_reason = :lock_reason,
                     locked_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                [
                    'subscription_status' => 'suspended',
                    'lock_reason' => $reason,
                    'id' => $companyId,
                ]
            );

            $this->logProviderAction($companyId, $providerUserId, 'locked', $reason, $source);
            $this->db->commit();

            return true;
        } catch (\Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function unlockByProvider(int $companyId, string $details, string $source, ?int $providerUserId = null): bool
    {
        $this->db->beginTransaction();

        try {
            $this->db->execute(
                'UPDATE companies
                 SET app_locked = 0,
                     subscription_status = :subscription_status,
                     lock_reason = NULL,
                     locked_at = NULL
                 WHERE id = :id',
                [
                    'subscription_status' => 'active',
                    'id' => $companyId,
                ]
            );

            $this->logProviderAction($companyId, $providerUserId, 'unlocked', $details, $source);
            $this->db->commit();

            return true;
        } catch (\Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function sendReminderByProvider(int $companyId, string $message, string $channel, string $source, ?int $providerUserId = null): bool
    {
        $this->db->beginTransaction();

        try {
            $this->db->execute(
                'INSERT INTO provider_messages (company_id, provider_user_id, channel, message)
                 VALUES (:company_id, :provider_user_id, :channel, :message)',
                [
                    'company_id' => $companyId,
                    'provider_user_id' => $providerUserId,
                    'channel' => $channel,
                    'message' => $message,
                ]
            );

            $this->db->execute(
                'UPDATE companies
                 SET subscription_status = CASE
                     WHEN subscription_status = :active_status THEN :past_due_status
                     ELSE subscription_status
                 END,
                 last_reminder_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                [
                    'active_status' => 'active',
                    'past_due_status' => 'past_due',
                    'id' => $companyId,
                ]
            );

            $this->logProviderAction($companyId, $providerUserId, 'reminder_sent', $message, $source);
            $this->db->commit();

            return true;
        } catch (\Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    private function logProviderAction(int $companyId, ?int $providerUserId, string $action, string $details, string $source): void
    {
        $this->db->execute(
            'INSERT INTO provider_actions (company_id, provider_user_id, action, details, source)
             VALUES (:company_id, :provider_user_id, :action, :details, :source)',
            [
                'company_id' => $companyId,
                'provider_user_id' => $providerUserId,
                'action' => $action,
                'details' => $details,
                'source' => $source,
            ]
        );
    }
}
