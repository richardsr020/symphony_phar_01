<?php

namespace App\Models;

use DateTimeImmutable;

class FiscalPeriod extends Model
{
    public function getCurrentPeriod(int $companyId, ?string $referenceDate = null): ?array
    {
        $refDate = $this->normalizeDate($referenceDate) ?? date('Y-m-d');
        return $this->ensureCurrentPeriod($companyId, $refDate);
    }

    public function getByCompany(int $companyId): array
    {
        return $this->db->fetchAll(
            'SELECT id, company_id, label, start_date, end_date, is_closed
             FROM fiscal_periods
             WHERE company_id = :company_id
             ORDER BY start_date DESC, id DESC',
            ['company_id' => $companyId]
        );
    }

    public function findByIdForCompany(int $companyId, int $periodId): ?array
    {
        if ($companyId <= 0 || $periodId <= 0) {
            return null;
        }

        return $this->db->fetchOne(
            'SELECT id, company_id, label, start_date, end_date, is_closed
             FROM fiscal_periods
             WHERE company_id = :company_id
               AND id = :id
             LIMIT 1',
            [
                'company_id' => $companyId,
                'id' => $periodId,
            ]
        );
    }

    public function ensureCurrentPeriod(int $companyId, string $referenceDate): ?array
    {
        $refDate = $this->normalizeDate($referenceDate) ?? date('Y-m-d');

        $period = $this->findByDate($companyId, $refDate);
        if ($period !== null) {
            $this->bindUnassignedRecordsToPeriod($companyId, (int) ($period['id'] ?? 0), (string) $period['start_date'], (string) $period['end_date']);
            return $period;
        }

        $latest = $this->db->fetchOne(
            'SELECT id, company_id, label, start_date, end_date, is_closed
             FROM fiscal_periods
             WHERE company_id = :company_id
             ORDER BY end_date DESC, id DESC
             LIMIT 1',
            ['company_id' => $companyId]
        );

        if ($latest === null) {
            $company = (new Company($this->db))->findById($companyId);
            if ($company === null) {
                return null;
            }

            $durationMonths = max(1, (int) ($company['fiscal_period_duration_months'] ?? 12));
            $configuredStart = $this->normalizeDate((string) ($company['fiscal_year_start'] ?? ''));
            if ($configuredStart === null) {
                $year = (int) substr($refDate, 0, 4);
                $configuredStart = $year . '-01-01';
            }

            $start = new DateTimeImmutable($configuredStart);
            while ($start->format('Y-m-d') > $refDate) {
                $start = $start->modify('-' . $durationMonths . ' months');
            }

            while (true) {
                $end = $start->modify('+' . $durationMonths . ' months -1 day');
                if ($refDate <= $end->format('Y-m-d')) {
                    break;
                }
                $start = $start->modify('+' . $durationMonths . ' months');
            }

            $period = $this->createPeriod(
                $companyId,
                $start->format('Y-m-d'),
                $end->format('Y-m-d')
            );
            $this->bindUnassignedRecordsToPeriod($companyId, (int) ($period['id'] ?? 0), (string) $period['start_date'], (string) $period['end_date']);
            return $period;
        }

        $company = (new Company($this->db))->findById($companyId);
        $durationMonths = max(1, (int) ($company['fiscal_period_duration_months'] ?? 12));

        $start = new DateTimeImmutable((string) $latest['start_date']);
        $end = new DateTimeImmutable((string) $latest['end_date']);

        while ($refDate > $end->format('Y-m-d')) {
            $start = $end->modify('+1 day');
            $end = $start->modify('+' . $durationMonths . ' months -1 day');
            $latest = $this->createPeriod($companyId, $start->format('Y-m-d'), $end->format('Y-m-d'));
        }

        if (is_array($latest)) {
            $this->bindUnassignedRecordsToPeriod($companyId, (int) ($latest['id'] ?? 0), (string) $latest['start_date'], (string) $latest['end_date']);
            return $latest;
        }

        return null;
    }

    public function configureCompany(int $companyId, string $startDate, int $durationMonths): array
    {
        $normalizedStart = $this->normalizeDate($startDate);
        if ($normalizedStart === null) {
            throw new \InvalidArgumentException('Date de debut exercice invalide.');
        }

        $months = max(1, min(24, $durationMonths));

        $this->db->beginTransaction();

        try {
            $this->db->execute(
                'UPDATE companies
                 SET fiscal_year_start = :fiscal_year_start,
                     fiscal_period_duration_months = :duration_months
                 WHERE id = :id',
                [
                    'fiscal_year_start' => $normalizedStart,
                    'duration_months' => $months,
                    'id' => $companyId,
                ]
            );

            $this->db->execute('DELETE FROM fiscal_periods WHERE company_id = :company_id', ['company_id' => $companyId]);

            $start = new DateTimeImmutable($normalizedStart);
            $end = $start->modify('+' . $months . ' months -1 day');
            $period = $this->createPeriod($companyId, $start->format('Y-m-d'), $end->format('Y-m-d'));

            $this->db->execute(
                'UPDATE transactions
                 SET fiscal_period_id = :period_id
                 WHERE company_id = :company_id',
                [
                    'period_id' => (int) ($period['id'] ?? 0),
                    'company_id' => $companyId,
                ]
            );

            $this->db->execute(
                'UPDATE invoices
                 SET fiscal_period_id = :period_id
                 WHERE company_id = :company_id',
                [
                    'period_id' => (int) ($period['id'] ?? 0),
                    'company_id' => $companyId,
                ]
            );

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }

        return $period;
    }

    public function resolvePeriodIdForDate(int $companyId, string $date): int
    {
        $normalized = $this->normalizeDate($date);
        if ($normalized === null) {
            throw new \InvalidArgumentException('Date invalide pour la periode fiscale.');
        }

        $period = $this->findByDate($companyId, $normalized);
        if ($period === null) {
            $period = $this->ensureCurrentPeriod($companyId, $normalized);
        }

        if (!is_array($period) || !isset($period['id'])) {
            throw new \RuntimeException('Impossible de resoudre la periode fiscale.');
        }

        return (int) $period['id'];
    }

    public function findByDate(int $companyId, string $date): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, company_id, label, start_date, end_date, is_closed
             FROM fiscal_periods
             WHERE company_id = :company_id
               AND :reference_date BETWEEN start_date AND end_date
             ORDER BY start_date DESC
             LIMIT 1',
            [
                'company_id' => $companyId,
                'reference_date' => $date,
            ]
        );
    }

    private function createPeriod(int $companyId, string $startDate, string $endDate): array
    {
        $label = 'Exercice ' . $startDate . ' -> ' . $endDate;

        $this->db->execute(
            'INSERT INTO fiscal_periods (company_id, label, start_date, end_date, is_closed)
             VALUES (:company_id, :label, :start_date, :end_date, :is_closed)',
            [
                'company_id' => $companyId,
                'label' => $label,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_closed' => 0,
            ]
        );

        $id = $this->db->lastInsertId();

        return [
            'id' => $id,
            'company_id' => $companyId,
            'label' => $label,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_closed' => 0,
        ];
    }

    private function normalizeDate(?string $date): ?string
    {
        $raw = trim((string) $date);
        if ($raw === '') {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $raw);
        if ($parsed === false) {
            return null;
        }

        return $parsed->format('Y-m-d');
    }

    private function bindUnassignedRecordsToPeriod(int $companyId, int $periodId, string $startDate, string $endDate): void
    {
        if ($periodId <= 0) {
            return;
        }

        $this->db->execute(
            'UPDATE transactions
             SET fiscal_period_id = :period_id
             WHERE company_id = :company_id
               AND fiscal_period_id IS NULL
               AND transaction_date BETWEEN :start_date AND :end_date',
            [
                'period_id' => $periodId,
                'company_id' => $companyId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        );

        $this->db->execute(
            'UPDATE invoices
             SET fiscal_period_id = :period_id
             WHERE company_id = :company_id
               AND fiscal_period_id IS NULL
               AND invoice_date BETWEEN :start_date AND :end_date',
            [
                'period_id' => $periodId,
                'company_id' => $companyId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        );
    }
}
