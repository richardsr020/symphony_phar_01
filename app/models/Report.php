<?php

namespace App\Models;

use DateTimeImmutable;

class Report extends Model
{
    public function resolvePeriod(array $query): array
    {
        $period = trim((string) ($query['period'] ?? 'year'));
        $fromInput = trim((string) ($query['from_date'] ?? ''));
        $toInput = trim((string) ($query['to_date'] ?? ''));
        $quickPeriods = ['month', 'last_month', 'quarter', 'year'];

        $today = new DateTimeImmutable('today');
        $from = null;
        $to = null;

        if ($fromInput !== '' && $toInput !== '') {
            $from = DateTimeImmutable::createFromFormat('Y-m-d', $fromInput) ?: null;
            $to = DateTimeImmutable::createFromFormat('Y-m-d', $toInput) ?: null;
            $period = 'custom';
        }

        if ($from === null || $to === null) {
            switch ($period) {
                case 'last_month':
                    $from = $today->modify('first day of last month');
                    $to = $today->modify('last day of last month');
                    break;
                case 'quarter':
                    $month = (int) $today->format('n');
                    $quarterStartMonth = ((int) floor(($month - 1) / 3) * 3) + 1;
                    $from = new DateTimeImmutable($today->format('Y') . '-' . str_pad((string) $quarterStartMonth, 2, '0', STR_PAD_LEFT) . '-01');
                    $to = $today;
                    break;
                case 'year':
                    $from = $today->modify('first day of january this year');
                    $to = $today;
                    break;
                case 'month':
                    $period = 'month';
                    $from = $today->modify('first day of this month');
                    $to = $today;
                    break;
                case 'year':
                default:
                    $period = 'year';
                    $from = $today->modify('first day of january this year');
                    $to = $today;
                    break;
            }
        }

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [
            'period' => $period,
            'from_date' => $from->format('Y-m-d'),
            'to_date' => $to->format('Y-m-d'),
            'label' => $from->format('d/m/Y') . ' - ' . $to->format('d/m/Y'),
        ];
    }

    public function getOverview(int $companyId, string $fromDate, string $toDate): array
    {
        $current = $this->getIncomeExpense($companyId, $fromDate, $toDate);

        $from = new DateTimeImmutable($fromDate);
        $to = new DateTimeImmutable($toDate);
        $days = max(1, (int) $from->diff($to)->format('%a') + 1);
        $previousTo = $from->modify('-1 day');
        $previousFrom = $previousTo->modify('-' . ($days - 1) . ' days');

        $previous = $this->getIncomeExpense($companyId, $previousFrom->format('Y-m-d'), $previousTo->format('Y-m-d'));

        $vatRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(tax_amount), 0) AS vat_due
             FROM invoices
             WHERE company_id = :company_id
               AND invoice_date BETWEEN :from_date AND :to_date
               AND status IN (:sent_status, :paid_status, :overdue_status)',
            [
                'company_id' => $companyId,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'sent_status' => 'sent',
                'paid_status' => 'paid',
                'overdue_status' => 'overdue',
            ]
        );

        $net = $current['revenue'] - $current['expenses'];
        $previousNet = $previous['revenue'] - $previous['expenses'];
        $profitMargin = $current['revenue'] > 0 ? ($net / $current['revenue']) * 100 : 0.0;
        $expenseRatio = $current['revenue'] > 0 ? ($current['expenses'] / $current['revenue']) * 100 : 0.0;
        $clientDebt = $this->getClientDebt($companyId, $toDate);
        $cashAvailable = $this->getCashAvailable($companyId, $fromDate, $toDate);
        $revenueDelta = $current['revenue'] - $previous['revenue'];
        $expensesDelta = $current['expenses'] - $previous['expenses'];
        $netDelta = $net - $previousNet;

        return [
            'revenue' => $current['revenue'],
            'expenses' => $current['expenses'],
            'net' => $net,
            'vat_due' => (float) ($vatRow['vat_due'] ?? 0),
            'client_debt' => $clientDebt,
            'profit_margin' => round($profitMargin, 2),
            'expense_ratio' => round($expenseRatio, 2),
            'cash_available' => $cashAvailable,
            'revenue_delta' => round($revenueDelta, 2),
            'expenses_delta' => round($expensesDelta, 2),
            'net_delta' => round($netDelta, 2),
            'revenue_prev' => $previous['revenue'],
            'expenses_prev' => $previous['expenses'],
            'net_prev' => $previousNet,
            'revenue_trend' => $this->computeTrend($current['revenue'], $previous['revenue']),
            'expenses_trend' => $this->computeTrend($current['expenses'], $previous['expenses']),
            'net_trend' => $this->computeTrend($net, $previousNet),
        ];
    }

    public function getMonthlySeries(int $companyId, string $fromDate, string $toDate): array
    {
        $isSqlite = strtolower((string) \Config::DB_DRIVER) === 'sqlite';
        $monthExprTransaction = $isSqlite
            ? 'strftime("%Y-%m", t.transaction_date)'
            : 'DATE_FORMAT(t.transaction_date, "%Y-%m")';
        $monthExprInvoice = $isSqlite
            ? 'strftime("%Y-%m", i.invoice_date)'
            : 'DATE_FORMAT(i.invoice_date, "%Y-%m")';

        $transactionRows = $this->db->fetchAll(
            'SELECT ' . $monthExprTransaction . ' AS month_key,
                    type,
                    COALESCE(SUM(j.debit), 0) AS debit_total,
                    COALESCE(SUM(j.credit), 0) AS credit_total
             FROM transactions t
             INNER JOIN journal_entries j ON j.transaction_id = t.id
             WHERE t.company_id = :company_id
               AND t.status <> :void_status
               AND t.transaction_date BETWEEN :from_date AND :to_date
             GROUP BY month_key, type
             ORDER BY month_key ASC',
            [
                'company_id' => $companyId,
                'void_status' => 'void',
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ]
        );

        $invoiceRows = $this->db->fetchAll(
            'SELECT ' . $monthExprInvoice . ' AS month_key,
                    COALESCE(SUM(i.total), 0) AS billed_amount
             FROM invoices i
             WHERE i.company_id = :company_id
               AND i.status IN (:draft_status, :sent_status, :paid_status, :overdue_status)
               AND i.invoice_date BETWEEN :from_date AND :to_date
             GROUP BY month_key
             ORDER BY month_key ASC',
            [
                'company_id' => $companyId,
                'draft_status' => 'draft',
                'sent_status' => 'sent',
                'paid_status' => 'paid',
                'overdue_status' => 'overdue',
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ]
        );

        $fromMonth = (new DateTimeImmutable($fromDate))->modify('first day of this month');
        $toMonth = (new DateTimeImmutable($toDate))->modify('first day of this month');

        $labels = [];
        $invoiceIncomeByMonth = [];
        $occasionalIncomeByMonth = [];
        $expenseByMonth = [];
        $cursor = $fromMonth;
        while ($cursor <= $toMonth) {
            $key = $cursor->format('Y-m');
            $labels[] = $key;
            $invoiceIncomeByMonth[$key] = 0.0;
            $occasionalIncomeByMonth[$key] = 0.0;
            $expenseByMonth[$key] = 0.0;
            $cursor = $cursor->modify('+1 month');
        }

        foreach ($transactionRows as $row) {
            $key = (string) ($row['month_key'] ?? '');
            if (!isset($invoiceIncomeByMonth[$key])) {
                continue;
            }

            if ((string) $row['type'] === 'income') {
                $occasionalIncomeByMonth[$key] += (float) ($row['debit_total'] ?? 0);
            } elseif ((string) $row['type'] === 'expense') {
                $expenseByMonth[$key] += (float) ($row['credit_total'] ?? 0);
            }
        }

        foreach ($invoiceRows as $row) {
            $key = (string) ($row['month_key'] ?? '');
            if (!isset($invoiceIncomeByMonth[$key])) {
                continue;
            }
            $invoiceIncomeByMonth[$key] += (float) ($row['billed_amount'] ?? 0);
        }

        $invoiceIncome = [];
        $occasionalIncome = [];
        $incomeTotal = [];
        $expenses = [];
        foreach ($labels as $monthKey) {
            $invoiceValue = round((float) ($invoiceIncomeByMonth[$monthKey] ?? 0), 2);
            $occasionalValue = round((float) ($occasionalIncomeByMonth[$monthKey] ?? 0), 2);
            $invoiceIncome[] = $invoiceValue;
            $occasionalIncome[] = $occasionalValue;
            $incomeTotal[] = round($invoiceValue + $occasionalValue, 2);
            $expenses[] = round((float) ($expenseByMonth[$monthKey] ?? 0), 2);
        }

        if ($labels === []) {
            $labels = ['Aucune periode'];
            $invoiceIncome = [0];
            $occasionalIncome = [0];
            $incomeTotal = [0];
            $expenses = [0];
        }

        return [
            'labels' => $labels,
            'income_invoice' => $invoiceIncome,
            'income_occasional' => $occasionalIncome,
            'income' => $incomeTotal,
            'expenses' => $expenses,
        ];
    }

    public function getExpenseBreakdown(int $companyId, string $fromDate, string $toDate): array
    {
        $rows = $this->db->fetchAll(
            'SELECT COALESCE(NULLIF(t.expense_subcategory, ""), "other") AS subcategory,
                    COALESCE(SUM(j.credit), 0) AS amount
             FROM transactions t
             INNER JOIN journal_entries j ON j.transaction_id = t.id
             WHERE t.company_id = :company_id
               AND t.type = :expense_type
               AND t.status <> :void_status
               AND t.transaction_date BETWEEN :from_date AND :to_date
             GROUP BY COALESCE(NULLIF(t.expense_subcategory, ""), "other")
             ORDER BY amount DESC',
            [
                'company_id' => $companyId,
                'expense_type' => 'expense',
                'void_status' => 'void',
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ]
        );

        if ($rows === []) {
            return [
                'labels' => ['Aucune depense'],
                'values' => [0],
                'colors' => ['#94A3B8'],
            ];
        }

        $subcategoryLabels = [
            'fiscal' => 'Fiscal',
            'salarial' => 'Salarial',
            'achat_stock' => 'Achat de stock',
            'loyer' => 'Loyer',
            'electricite' => 'Electricite',
            'eau' => 'Eau',
            'internet' => 'Internet',
            'other' => 'Autre',
        ];
        $subcategoryColors = [
            'fiscal' => '#0EA5E9',
            'salarial' => '#F97316',
            'achat_stock' => '#14B8A6',
            'loyer' => '#EF4444',
            'electricite' => '#F59E0B',
            'eau' => '#06B6D4',
            'internet' => '#22C55E',
            'other' => '#64748B',
        ];

        $labels = [];
        $values = [];
        $colors = [];
        foreach ($rows as $row) {
            $key = (string) ($row['subcategory'] ?? 'other');
            $labels[] = $subcategoryLabels[$key] ?? 'Autre';
            $values[] = round((float) ($row['amount'] ?? 0), 2);
            $colors[] = $subcategoryColors[$key] ?? '#64748B';
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'colors' => $colors,
        ];
    }

    public function getRevenueBreakdown(int $companyId, string $fromDate, string $toDate): array
    {
        $rowsTransactions = $this->db->fetchAll(
            'SELECT COALESCE(a.name, "Autres") AS label,
                    COALESCE(SUM(j.debit), 0) AS amount
             FROM transactions t
             INNER JOIN journal_entries j ON j.transaction_id = t.id
             LEFT JOIN accounts a ON a.id = j.account_id
             WHERE t.company_id = :company_id
               AND t.type = :income_type
               AND t.status <> :void_status
               AND t.transaction_date BETWEEN :from_date AND :to_date
             GROUP BY COALESCE(a.name, "Autres")
             ORDER BY amount DESC
             LIMIT 6',
            [
                'company_id' => $companyId,
                'income_type' => 'income',
                'void_status' => 'void',
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ]
        );

        $rowsInvoices = $this->db->fetchAll(
            'SELECT COALESCE(i.customer_name, "Client facture") AS label,
                    COALESCE(SUM(i.total), 0) AS amount
             FROM invoices i
             WHERE i.company_id = :company_id
               AND i.status IN (:draft_status, :sent_status, :paid_status, :overdue_status)
               AND i.invoice_date BETWEEN :from_date AND :to_date
             GROUP BY COALESCE(i.customer_name, "Client facture")',
            [
                'company_id' => $companyId,
                'draft_status' => 'draft',
                'sent_status' => 'sent',
                'paid_status' => 'paid',
                'overdue_status' => 'overdue',
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ]
        );

        $combined = [];
        foreach ($rowsTransactions as $row) {
            $label = (string) ($row['label'] ?? 'Autres');
            $combined[$label] = ($combined[$label] ?? 0) + (float) ($row['amount'] ?? 0);
        }
        foreach ($rowsInvoices as $row) {
            $label = (string) ($row['label'] ?? 'Client facture');
            $combined[$label] = ($combined[$label] ?? 0) + (float) ($row['amount'] ?? 0);
        }

        arsort($combined);
        $combined = array_slice($combined, 0, 6, true);

        $labels = [];
        $values = [];
        foreach ($combined as $label => $amount) {
            $labels[] = (string) $label;
            $values[] = round((float) $amount, 2);
        }

        if ($labels === []) {
            $labels = ['Aucune donnee'];
            $values = [0];
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    public function getProfitLoss(int $companyId, string $fromDate, string $toDate): array
    {
        $data = $this->getIncomeExpense($companyId, $fromDate, $toDate);

        return [
            'revenue' => $data['revenue'],
            'expenses' => $data['expenses'],
            'net' => $data['revenue'] - $data['expenses'],
        ];
    }

    public function getBalanceSheet(int $companyId, string $toDate): array
    {
        $row = $this->db->fetchOne(
            'SELECT
                COALESCE(SUM(CASE WHEN a.type = :asset_type THEN j.debit - j.credit ELSE 0 END), 0) AS assets,
                COALESCE(SUM(CASE WHEN a.type = :liability_type THEN j.credit - j.debit ELSE 0 END), 0) AS liabilities,
                COALESCE(SUM(CASE WHEN a.type = :equity_type THEN j.credit - j.debit ELSE 0 END), 0) AS equity
             FROM transactions t
             INNER JOIN journal_entries j ON j.transaction_id = t.id
             INNER JOIN accounts a ON a.id = j.account_id
             WHERE t.company_id = :company_id
               AND t.status <> :void_status
               AND t.transaction_date <= :to_date',
            [
                'asset_type' => 'asset',
                'liability_type' => 'liability',
                'equity_type' => 'equity',
                'company_id' => $companyId,
                'void_status' => 'void',
                'to_date' => $toDate,
            ]
        );

        return [
            'assets' => round((float) ($row['assets'] ?? 0), 2),
            'liabilities' => round((float) ($row['liabilities'] ?? 0), 2),
            'equity' => round((float) ($row['equity'] ?? 0), 2),
        ];
    }

    public function getTva(int $companyId, string $fromDate, string $toDate): array
    {
        $row = $this->db->fetchOne(
            'SELECT
                COALESCE(SUM(CASE WHEN status IN (:sent_status, :overdue_status) THEN tax_amount ELSE 0 END), 0) AS vat_due,
                COALESCE(SUM(CASE WHEN status = :paid_status THEN tax_amount ELSE 0 END), 0) AS vat_paid,
                COALESCE(SUM(CASE WHEN status IN (:sent_status, :paid_status, :overdue_status) THEN tax_amount ELSE 0 END), 0) AS vat_total
             FROM invoices
             WHERE company_id = :company_id
               AND invoice_date BETWEEN :from_date AND :to_date',
            [
                'company_id' => $companyId,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'sent_status' => 'sent',
                'overdue_status' => 'overdue',
                'paid_status' => 'paid',
            ]
        );

        return [
            'vat_total' => round((float) ($row['vat_total'] ?? 0), 2),
            'vat_due' => round((float) ($row['vat_due'] ?? 0), 2),
            'vat_paid' => round((float) ($row['vat_paid'] ?? 0), 2),
        ];
    }

    private function getIncomeExpense(int $companyId, string $fromDate, string $toDate): array
    {
        $rowTransactions = $this->db->fetchOne(
            'SELECT
                COALESCE(SUM(CASE WHEN t.type = :income_type THEN j.debit ELSE 0 END), 0) AS revenue,
                COALESCE(SUM(CASE WHEN t.type = :expense_type THEN j.credit ELSE 0 END), 0) AS expenses
             FROM transactions t
             INNER JOIN journal_entries j ON j.transaction_id = t.id
             WHERE t.company_id = :company_id
               AND t.status <> :void_status
               AND t.transaction_date BETWEEN :from_date AND :to_date',
            [
                'company_id' => $companyId,
                'income_type' => 'income',
                'expense_type' => 'expense',
                'void_status' => 'void',
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ]
        );

        $rowInvoices = $this->db->fetchOne(
            'SELECT COALESCE(SUM(i.total), 0) AS revenue
             FROM invoices i
             WHERE i.company_id = :company_id
               AND i.status IN (:draft_status, :sent_status, :paid_status, :overdue_status)
               AND i.invoice_date BETWEEN :from_date AND :to_date',
            [
                'company_id' => $companyId,
                'draft_status' => 'draft',
                'sent_status' => 'sent',
                'paid_status' => 'paid',
                'overdue_status' => 'overdue',
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ]
        );

        return [
            'revenue' => round((float) ($rowTransactions['revenue'] ?? 0) + (float) ($rowInvoices['revenue'] ?? 0), 2),
            'expenses' => round((float) ($rowTransactions['expenses'] ?? 0), 2),
        ];
    }

    private function getClientDebt(int $companyId, string $toDate): float
    {
        $row = $this->db->fetchOne(
            'SELECT COALESCE(SUM(i.total - COALESCE(i.paid_amount, 0)), 0) AS client_debt
             FROM invoices i
             WHERE i.company_id = :company_id
               AND i.status IN (:sent_status, :overdue_status)
               AND i.invoice_date <= :to_date',
            [
                'company_id' => $companyId,
                'sent_status' => 'sent',
                'overdue_status' => 'overdue',
                'to_date' => $toDate,
            ]
        );

        return round((float) ($row['client_debt'] ?? 0), 2);
    }

    private function getCashAvailable(int $companyId, string $fromDate, string $toDate): float
    {
        $cashInvoicesRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(
                CASE
                    WHEN paid_amount > 0 THEN paid_amount
                    WHEN status = :paid_status THEN total
                    ELSE 0
                END
            ), 0) AS total_paid
             FROM invoices
             WHERE company_id = :company_id
               AND status IN (:sent_status, :paid_status, :overdue_status)
               AND COALESCE(paid_date, invoice_date) BETWEEN :from_date AND :to_date
               AND NOT EXISTS (
                    SELECT 1 FROM invoice_payment_allocations a
                    WHERE a.invoice_id = invoices.id
                )',
            [
                'company_id' => $companyId,
                'sent_status' => 'sent',
                'paid_status' => 'paid',
                'overdue_status' => 'overdue',
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ]
        );

        $cashTransactionsRow = $this->db->fetchOne(
            'SELECT
                COALESCE(SUM(CASE WHEN t.type IN (:income_type, :debt_payment_type) THEN j.debit ELSE 0 END), 0) AS income_total,
                COALESCE(SUM(CASE WHEN t.type = :expense_type THEN j.credit ELSE 0 END), 0) AS expense_total
             FROM transactions t
             INNER JOIN journal_entries j ON j.transaction_id = t.id
             WHERE t.company_id = :company_id
               AND t.status <> :void_status
               AND t.transaction_date BETWEEN :from_date AND :to_date',
            [
                'company_id' => $companyId,
                'void_status' => 'void',
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'income_type' => 'income',
                'debt_payment_type' => 'debt_payment',
                'expense_type' => 'expense',
            ]
        );

        $cashFromInvoices = (float) ($cashInvoicesRow['total_paid'] ?? 0);
        $transactionsIncome = (float) ($cashTransactionsRow['income_total'] ?? 0);
        $transactionsExpense = (float) ($cashTransactionsRow['expense_total'] ?? 0);

        return round($cashFromInvoices + $transactionsIncome - $transactionsExpense, 2);
    }

    private function computeTrend(float $current, float $previous): float
    {
        if ($previous <= 0.0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return (($current - $previous) / $previous) * 100.0;
    }
}
