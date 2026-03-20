<?php

namespace App\Core;

use App\Models\Company;
use DateTimeImmutable;
use Throwable;

class SubscriptionService
{
    private Database $db;
    private Company $companyModel;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->companyModel = new Company($this->db);
    }

    public function configureCompany(
        int $companyId,
        string $dueDate,
        string $callbackUrl,
        int $reminderIntervalDays,
        bool $autoEnabled
    ): bool {
        $normalizedDueDate = $this->normalizeDate($dueDate);
        $interval = max(1, min(30, $reminderIntervalDays));
        $callback = trim($callbackUrl);

        $this->db->execute(
            'UPDATE companies
             SET subscription_ends_at = :due_date,
                 provider_callback_url = :callback_url,
                 reminder_interval_days = :interval_days,
                 auto_subscription_enabled = :auto_enabled
             WHERE id = :id',
            [
                'due_date' => $normalizedDueDate,
                'callback_url' => $callback !== '' ? $callback : null,
                'interval_days' => $interval,
                'auto_enabled' => $autoEnabled ? 1 : 0,
                'id' => $companyId,
            ]
        );

        $this->logEvent(
            $companyId,
            'subscription_config_updated',
            sprintf(
                'Echéance=%s, callback=%s, interval=%d, enabled=%d',
                $normalizedDueDate,
                $callback !== '' ? $callback : 'none',
                $interval,
                $autoEnabled ? 1 : 0
            ),
            'dashboard'
        );

        return true;
    }

    public function runAutomation(?int $companyId = null): array
    {
        $companies = $this->companyModel->getCompaniesForAutomation($companyId);
        $today = new DateTimeImmutable('today');

        $summary = [
            'companies_scanned' => count($companies),
            'licenses_generated' => 0,
            'licenses_sent' => 0,
            'reminders_sent' => 0,
            'suspended_companies' => 0,
        ];

        foreach ($companies as $company) {
            $companyDueDate = $this->normalizeDate((string) ($company['subscription_ends_at'] ?? ''));
            $dueDate = new DateTimeImmutable($companyDueDate);
            $daysUntilDue = (int) $today->diff($dueDate)->format('%r%a');
            $companyIdValue = (int) $company['id'];

            if ($daysUntilDue === (int) \Config::SUBSCRIPTION_LICENSE_LEAD_DAYS) {
                $generated = $this->generateAndSendLicense($company);
                $summary['licenses_generated'] += $generated['generated'];
                $summary['licenses_sent'] += $generated['sent'];
            }

            if ($daysUntilDue <= (int) \Config::SUBSCRIPTION_REMINDER_START_DAYS && $daysUntilDue >= 0) {
                if ($this->sendReminderIfNeeded($company, $today, $daysUntilDue)) {
                    $summary['reminders_sent']++;
                }
            }

            if ($daysUntilDue < 0 && ((int) ($company['app_locked'] ?? 0)) !== 1) {
                $this->suspendCompany($companyIdValue);
                $summary['suspended_companies']++;
            }
        }

        return $summary;
    }

    public function activateWithLicenseKey(int $companyId, string $rawLicenseKey, int $renewalDays): array
    {
        $licenseKey = trim($rawLicenseKey);
        if ($licenseKey === '') {
            return ['ok' => false, 'error' => 'empty_license_key'];
        }

        $days = max(1, min(365, $renewalDays));
        $licenseHash = hash('sha256', $licenseKey);

        $license = $this->db->fetchOne(
            'SELECT id, company_id, status
             FROM subscription_licenses
             WHERE company_id = :company_id
               AND license_hash = :license_hash
               AND status = :status
             LIMIT 1',
            [
                'company_id' => $companyId,
                'license_hash' => $licenseHash,
                'status' => 'sent',
            ]
        );

        if ($license === null) {
            return ['ok' => false, 'error' => 'invalid_or_already_used_key'];
        }

        $nextDueDate = (new DateTimeImmutable('today'))
            ->modify('+' . $days . ' days')
            ->format('Y-m-d');

        $this->db->beginTransaction();

        try {
            $this->db->execute(
                'UPDATE subscription_licenses
                 SET status = :status, activated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                [
                    'status' => 'activated',
                    'id' => (int) $license['id'],
                ]
            );

            $this->db->execute(
                'UPDATE subscription_licenses
                 SET status = :expired_status
                 WHERE company_id = :company_id
                   AND id <> :current_id
                   AND status = :sent_status',
                [
                    'expired_status' => 'expired',
                    'company_id' => $companyId,
                    'current_id' => (int) $license['id'],
                    'sent_status' => 'sent',
                ]
            );

            $this->db->execute(
                'UPDATE companies
                 SET subscription_status = :active_status,
                     subscription_ends_at = :next_due,
                     app_locked = 0,
                     lock_reason = NULL,
                     locked_at = NULL,
                     last_reminder_at = NULL
                 WHERE id = :id',
                [
                    'active_status' => 'active',
                    'next_due' => $nextDueDate,
                    'id' => $companyId,
                ]
            );

            $this->logEvent(
                $companyId,
                'license_activated',
                'Réabonnement réactivé via clé de licence.',
                'api'
            );

            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }

        return [
            'ok' => true,
            'next_due_date' => $nextDueDate,
            'renewal_days' => $days,
        ];
    }

    private function generateAndSendLicense(array $company): array
    {
        $companyId = (int) $company['id'];
        $periodEndDate = $this->normalizeDate((string) ($company['subscription_ends_at'] ?? ''));
        $callbackUrl = trim((string) ($company['provider_callback_url'] ?? ''));

        $existing = $this->db->fetchOne(
            'SELECT id, status
             FROM subscription_licenses
             WHERE company_id = :company_id
               AND period_end_date = :period_end_date
             ORDER BY id DESC
             LIMIT 1',
            [
                'company_id' => $companyId,
                'period_end_date' => $periodEndDate,
            ]
        );

        if ($existing !== null && in_array((string) $existing['status'], ['sent', 'activated'], true)) {
            return ['generated' => 0, 'sent' => 0];
        }

        if ($existing !== null && (string) $existing['status'] === 'generated') {
            $this->db->execute(
                'UPDATE subscription_licenses
                 SET status = :expired_status, webhook_response = :response
                 WHERE id = :id',
                [
                    'expired_status' => 'expired',
                    'response' => 'Clé regénérée après échec de livraison.',
                    'id' => (int) $existing['id'],
                ]
            );
        }

        $licenseKey = $this->generateLicenseKey();
        $licenseHash = hash('sha256', $licenseKey);
        $expiresAt = (new DateTimeImmutable($periodEndDate))->modify('+30 days')->format('Y-m-d');

        $this->db->execute(
            'INSERT INTO subscription_licenses (company_id, period_end_date, license_hash, status, webhook_url, expires_at)
             VALUES (:company_id, :period_end_date, :license_hash, :status, :webhook_url, :expires_at)',
            [
                'company_id' => $companyId,
                'period_end_date' => $periodEndDate,
                'license_hash' => $licenseHash,
                'status' => 'generated',
                'webhook_url' => $callbackUrl !== '' ? $callbackUrl : null,
                'expires_at' => $expiresAt,
            ]
        );

        $licenseId = $this->db->lastInsertId();
        $this->logEvent($companyId, 'license_generated', 'Clé de licence générée automatiquement.', 'system');

        if ($callbackUrl === '') {
            $this->logEvent($companyId, 'license_webhook_missing', 'Aucune URL callback configurée.', 'system');
            return ['generated' => 1, 'sent' => 0];
        }

        $payload = [
            'event' => 'subscription.license_generated',
            'company' => [
                'id' => $companyId,
                'name' => (string) ($company['name'] ?? ''),
                'email' => (string) ($company['email'] ?? ''),
            ],
            'period_end_date' => $periodEndDate,
            'license_key' => $licenseKey,
            'generated_at' => date('c'),
        ];

        $result = $this->sendWebhook($callbackUrl, $payload);
        if ($result['success']) {
            $this->db->execute(
                'UPDATE subscription_licenses
                 SET status = :status,
                     sent_at = CURRENT_TIMESTAMP,
                     webhook_response = :response
                 WHERE id = :id',
                [
                    'status' => 'sent',
                    'response' => $result['response'],
                    'id' => $licenseId,
                ]
            );

            $this->logEvent($companyId, 'license_sent', 'Clé envoyée au fournisseur.', 'system');
            return ['generated' => 1, 'sent' => 1];
        }

        $this->db->execute(
            'UPDATE subscription_licenses
             SET webhook_response = :response
             WHERE id = :id',
            [
                'response' => $result['response'],
                'id' => $licenseId,
            ]
        );

        $this->logEvent($companyId, 'license_send_failed', 'Échec envoi clé: ' . $result['response'], 'system');
        return ['generated' => 1, 'sent' => 0];
    }

    private function sendReminderIfNeeded(array $company, DateTimeImmutable $today, int $daysUntilDue): bool
    {
        $companyId = (int) $company['id'];
        $interval = (int) ($company['reminder_interval_days'] ?? \Config::SUBSCRIPTION_DEFAULT_REMINDER_INTERVAL_DAYS);
        $interval = max(1, min(30, $interval));

        $lastReminder = (string) ($company['last_reminder_at'] ?? '');
        if ($lastReminder !== '') {
            $lastReminderDate = new DateTimeImmutable(substr($lastReminder, 0, 10));
            $daysSinceLastReminder = (int) $lastReminderDate->diff($today)->format('%a');
            if ($daysSinceLastReminder < $interval) {
                return false;
            }
        }

        $dueDate = $this->normalizeDate((string) ($company['subscription_ends_at'] ?? ''));
        $message = sprintf(
            'Rappel abonnement: votre échéance Symphony est le %s (J-%d). Veuillez renouveler votre licence.',
            $dueDate,
            $daysUntilDue
        );

        $this->db->beginTransaction();

        try {
            $this->db->execute(
                'INSERT INTO provider_messages (company_id, provider_user_id, channel, message)
                 VALUES (:company_id, NULL, :channel, :message)',
                [
                    'company_id' => $companyId,
                    'channel' => 'api',
                    'message' => $message,
                ]
            );

            $this->db->execute(
                'INSERT INTO alerts (company_id, type, severity, title, message, is_resolved)
                 VALUES (:company_id, :type, :severity, :title, :message, :is_resolved)',
                [
                    'company_id' => $companyId,
                    'type' => 'subscription',
                    'severity' => 'warning',
                    'title' => 'Rappel de réabonnement',
                    'message' => $message,
                    'is_resolved' => 0,
                ]
            );

            $this->db->execute(
                'UPDATE companies
                 SET last_reminder_at = CURRENT_TIMESTAMP,
                     subscription_status = CASE
                        WHEN subscription_status = :active_status THEN :past_due_status
                        ELSE subscription_status
                     END
                 WHERE id = :id',
                [
                    'active_status' => 'active',
                    'past_due_status' => 'past_due',
                    'id' => $companyId,
                ]
            );

            $this->logEvent($companyId, 'reminder_sent', $message, 'system');
            $this->db->commit();
            return true;
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    private function suspendCompany(int $companyId): void
    {
        $this->db->execute(
            'UPDATE companies
             SET app_locked = 1,
                 subscription_status = :status,
                 lock_reason = :reason,
                 locked_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            [
                'status' => 'suspended',
                'reason' => 'Échéance dépassée - abonnement non renouvelé.',
                'id' => $companyId,
            ]
        );

        $this->logEvent($companyId, 'company_suspended', 'Entreprise suspendue automatiquement.', 'system');
    }

    private function logEvent(int $companyId, string $eventType, string $details, string $source): void
    {
        $this->db->execute(
            'INSERT INTO subscription_events (company_id, event_type, details, source)
             VALUES (:company_id, :event_type, :details, :source)',
            [
                'company_id' => $companyId,
                'event_type' => $eventType,
                'details' => $details,
                'source' => $source,
            ]
        );
    }

    private function sendWebhook(string $url, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            return ['success' => false, 'response' => 'JSON payload invalide.'];
        }

        $signature = hash_hmac('sha256', $body, (string) \Config::PROVIDER_WEBHOOK_SECRET);
        $headers = [
            'Content-Type: application/json',
            'X-Symphony-Signature: ' . $signature,
            'X-Symphony-Event: subscription.license_generated',
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error !== '') {
                return ['success' => false, 'response' => 'cURL error: ' . $error];
            }

            return [
                'success' => $statusCode >= 200 && $statusCode < 300,
                'response' => 'HTTP ' . $statusCode . ' - ' . substr((string) $response, 0, 500),
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? 'HTTP/1.1 500';
        preg_match('/\s(\d{3})\s/', $statusLine, $matches);
        $statusCode = isset($matches[1]) ? (int) $matches[1] : 500;

        return [
            'success' => $statusCode >= 200 && $statusCode < 300,
            'response' => 'HTTP ' . $statusCode . ' - ' . substr((string) $response, 0, 500),
        ];
    }

    private function generateLicenseKey(): string
    {
        return strtoupper(bin2hex(random_bytes(24)));
    }

    private function normalizeDate(string $date): string
    {
        $dateTime = DateTimeImmutable::createFromFormat('Y-m-d', trim($date));
        if ($dateTime === false || $dateTime->format('Y-m-d') !== trim($date)) {
            throw new \InvalidArgumentException('Date invalide, format attendu: YYYY-MM-DD.');
        }

        return $dateTime->format('Y-m-d');
    }
}
