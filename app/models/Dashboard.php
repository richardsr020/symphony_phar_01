<?php

namespace App\Models;

use DateTimeImmutable;

class Dashboard extends Model
{
    public function getDashboardPayloadCached(int $companyId, int $ttlSeconds = 60, bool $forceRefresh = false, array $options = []): array
    {
        $ttlSeconds = max(5, min(300, $ttlSeconds));
        $normalizedOptions = $this->normalizeDashboardOptions($options);
        $cacheFile = $this->getCacheFilePath($companyId, $normalizedOptions);

        if (!$forceRefresh && is_file($cacheFile) && is_readable($cacheFile)) {
            $raw = (string) file_get_contents($cacheFile);
            $cached = json_decode($raw, true);

            if (is_array($cached)) {
                $cachedAt = (int) ($cached['cached_at'] ?? 0);
                if ($cachedAt > 0 && (time() - $cachedAt) <= $ttlSeconds && isset($cached['payload']) && is_array($cached['payload'])) {
                    return $cached['payload'];
                }
            }
        }

        $payload = $this->getDashboardPayload($companyId, $normalizedOptions);
        $this->writeCache($cacheFile, [
            'cached_at' => time(),
            'payload' => $payload,
        ]);

        return $payload;
    }

    public function invalidateDashboardCache(int $companyId): void
    {
        $cacheDir = (string) (\Config::CACHE_DIR ?? (dirname(__DIR__, 2) . '/storage/cache/'));
        $pattern = rtrim($cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dashboard_company_' . $companyId . '*.json';
        foreach (glob($pattern) ?: [] as $cacheFile) {
            if (is_file($cacheFile)) {
                @unlink($cacheFile);
            }
        }
    }

    public function getDashboardPayload(int $companyId, array $options = []): array
    {
        $normalizedOptions = $this->normalizeDashboardOptions($options);
        $fiscalPeriod = (new FiscalPeriod($this->db))->getCurrentPeriod($companyId, date('Y-m-d'));
        $stats = $this->getStats($companyId, $fiscalPeriod);
        $recentTransactions = $this->getRecentTransactions($companyId, 6);
        $alerts = $this->getAlerts($companyId, 5);
        $cashflow = $this->getCashflowSeries($companyId, (string) $normalizedOptions['cashflow_period']);
        $expenseBreakdown = $this->getExpenseBreakdown($companyId, (string) $normalizedOptions['expenses_period'], 8);
        $cashReconciliation = $this->getCashReconciliation($companyId, $fiscalPeriod, 12);

        return [
            'currentPeriod' => $fiscalPeriod,
            'stats' => $stats,
            'recentTransactions' => $recentTransactions,
            'insights' => $this->buildInsights($stats, $alerts),
            'alerts' => $alerts,
            'cashflow' => $cashflow,
            'expenseBreakdown' => $expenseBreakdown,
            'cashReconciliation' => $cashReconciliation,
        ];
    }

    public function getStats(int $companyId, ?array $fiscalPeriod = null): array
    {
        $period = $fiscalPeriod ?? (new FiscalPeriod($this->db))->getCurrentPeriod($companyId, date('Y-m-d'));
        if (!is_array($period)) {
            return [
                'cash' => 0.0,
                'revenue' => 0.0,
                'expenses' => 0.0,
                'vat_due' => 0.0,
                'cash_invoices' => 0.0,
                'cash_transactions' => 0.0,
                'revenue_invoices' => 0.0,
                'revenue_occasional' => 0.0,
                'expenses_transactions' => 0.0,
                'vat_from_invoices' => 0.0,
                'revenue_trend' => 0.0,
                'expenses_trend' => 0.0,
            ];
        }

        $fromDate = (string) ($period['start_date'] ?? date('Y-m-01'));
        $toDate = (string) ($period['end_date'] ?? date('Y-m-d'));
        $periodStart = new DateTimeImmutable($fromDate);
        $periodEnd = new DateTimeImmutable($toDate);
        $days = max(1, (int) $periodStart->diff($periodEnd)->format('%a') + 1);
        $prevEnd = $periodStart->modify('-1 day');
        $prevStart = $prevEnd->modify('-' . ($days - 1) . ' days');

        $cashRow = $this->db->fetchOne(
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

        $monthRevenueRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(total), 0) AS amount
             FROM invoices
             WHERE company_id = :company_id
               AND status IN (:draft_status, :sent_status, :paid_status, :overdue_status)
               AND invoice_date BETWEEN :from_date AND :to_date',
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

        $monthOccasionalIncomeRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(j.debit), 0) AS amount
             FROM transactions t
             INNER JOIN journal_entries j ON j.transaction_id = t.id
             WHERE t.company_id = :company_id
               AND t.type = :income_type
               AND t.status <> :void_status
               AND t.transaction_date BETWEEN :from_date AND :to_date',
            [
                'company_id' => $companyId,
                'income_type' => 'income',
                'void_status' => 'void',
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ]
        );

        $prevRevenueRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(total), 0) AS amount
             FROM invoices
             WHERE company_id = :company_id
               AND status IN (:draft_status, :sent_status, :paid_status, :overdue_status)
               AND invoice_date BETWEEN :from_date AND :to_date',
            [
                'company_id' => $companyId,
                'draft_status' => 'draft',
                'sent_status' => 'sent',
                'paid_status' => 'paid',
                'overdue_status' => 'overdue',
                'from_date' => $prevStart->format('Y-m-d'),
                'to_date' => $prevEnd->format('Y-m-d'),
            ]
        );

        $prevOccasionalIncomeRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(j.debit), 0) AS amount
             FROM transactions t
             INNER JOIN journal_entries j ON j.transaction_id = t.id
             WHERE t.company_id = :company_id
               AND t.type = :income_type
               AND t.status <> :void_status
               AND t.transaction_date BETWEEN :from_date AND :to_date',
            [
                'company_id' => $companyId,
                'income_type' => 'income',
                'void_status' => 'void',
                'from_date' => $prevStart->format('Y-m-d'),
                'to_date' => $prevEnd->format('Y-m-d'),
            ]
        );

        $monthExpenseRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(j.credit), 0) AS amount
             FROM transactions t
             INNER JOIN journal_entries j ON j.transaction_id = t.id
             WHERE t.company_id = :company_id
               AND t.type = :expense_type
               AND t.status <> :void_status
               AND t.transaction_date BETWEEN :from_date AND :to_date',
            [
                'company_id' => $companyId,
                'expense_type' => 'expense',
                'void_status' => 'void',
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ]
        );

        $prevExpenseRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(j.credit), 0) AS amount
             FROM transactions t
             INNER JOIN journal_entries j ON j.transaction_id = t.id
             WHERE t.company_id = :company_id
               AND t.type = :expense_type
               AND t.status <> :void_status
               AND t.transaction_date BETWEEN :from_date AND :to_date',
            [
                'company_id' => $companyId,
                'expense_type' => 'expense',
                'void_status' => 'void',
                'from_date' => $prevStart->format('Y-m-d'),
                'to_date' => $prevEnd->format('Y-m-d'),
            ]
        );

        $vatDueRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(tax_amount), 0) AS amount
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

        $vatPaidRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(j.credit), 0) AS amount
             FROM transactions t
             INNER JOIN journal_entries j ON j.transaction_id = t.id
             WHERE t.company_id = :company_id
               AND t.type = :expense_type
               AND t.expense_subcategory = :fiscal_subcategory
               AND t.expense_fiscal_subcategory = :vat_payment_subcategory
               AND t.status = :posted_status
               AND t.transaction_date BETWEEN :from_date AND :to_date',
            [
                'company_id' => $companyId,
                'expense_type' => 'expense',
                'fiscal_subcategory' => 'fiscal',
                'vat_payment_subcategory' => 'versement_tva',
                'posted_status' => 'posted',
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ]
        );

        $cashFromInvoices = (float) ($cashRow['total_paid'] ?? 0);
        $cashFromTransactions = (float) ($cashTransactionsRow['income_total'] ?? 0) - (float) ($cashTransactionsRow['expense_total'] ?? 0);
        $revenueFromInvoices = (float) ($monthRevenueRow['amount'] ?? 0);
        $revenueFromOccasional = (float) ($monthOccasionalIncomeRow['amount'] ?? 0);
        $expensesFromTransactions = (float) ($monthExpenseRow['amount'] ?? 0);
        $vatFromInvoices = (float) ($vatDueRow['amount'] ?? 0);
        $vatPaid = (float) ($vatPaidRow['amount'] ?? 0);
        $vatDue = max($vatFromInvoices - $vatPaid, 0);

        $periodDebtRow = $this->db->fetchOne(
            'SELECT COALESCE(COUNT(*), 0) AS count, COALESCE(SUM(total - paid_amount), 0) AS amount
             FROM invoices
             WHERE company_id = :company_id
               AND invoice_date BETWEEN :from_date AND :to_date
               AND total > paid_amount
               AND status IN (:draft_status, :sent_status, :paid_status, :overdue_status)',
            [
                'company_id' => $companyId,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'draft_status' => 'draft',
                'sent_status' => 'sent',
                'paid_status' => 'paid',
                'overdue_status' => 'overdue',
            ]
        );

        $periodSalesWithDebtCount = (int) ($periodDebtRow['count'] ?? 0);
        $periodDebtAmount = (float) ($periodDebtRow['amount'] ?? 0);

        $today = date('Y-m-d');
        $dailyPaidRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(paid_amount), 0) AS amount
             FROM invoices
             WHERE company_id = :company_id
               AND paid_date = :today
               AND status IN (:paid_status, :overdue_status)',
            [
                'company_id' => $companyId,
                'today' => $today,
                'paid_status' => 'paid',
                'overdue_status' => 'overdue',
            ]
        );

        $dailyTotalRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(total), 0) AS amount
             FROM invoices
             WHERE company_id = :company_id
               AND invoice_date = :today
               AND status IN (:draft_status, :sent_status, :paid_status, :overdue_status)',
            [
                'company_id' => $companyId,
                'today' => $today,
                'draft_status' => 'draft',
                'sent_status' => 'sent',
                'paid_status' => 'paid',
                'overdue_status' => 'overdue',
            ]
        );

        $dailyDebtSalesRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(total), 0) AS amount
             FROM invoices
             WHERE company_id = :company_id
               AND invoice_date = :today
               AND total > paid_amount
               AND status IN (:draft_status, :sent_status, :paid_status, :overdue_status)',
            [
                'company_id' => $companyId,
                'today' => $today,
                'draft_status' => 'draft',
                'sent_status' => 'sent',
                'paid_status' => 'paid',
                'overdue_status' => 'overdue',
            ]
        );

        $dailyDebtInvoiceCountRow = $this->db->fetchOne(
            'SELECT COALESCE(COUNT(*), 0) AS amount
             FROM invoices
             WHERE company_id = :company_id
               AND invoice_date = :today
               AND total > paid_amount
               AND status IN (:draft_status, :sent_status, :paid_status, :overdue_status)',
            [
                'company_id' => $companyId,
                'today' => $today,
                'draft_status' => 'draft',
                'sent_status' => 'sent',
                'paid_status' => 'paid',
                'overdue_status' => 'overdue',
            ]
        );

        $dailyDebtAmountRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(total - paid_amount), 0) AS amount
             FROM invoices
             WHERE company_id = :company_id
               AND invoice_date = :today
               AND total > paid_amount
               AND status IN (:draft_status, :sent_status, :paid_status, :overdue_status)',
            [
                'company_id' => $companyId,
                'today' => $today,
                'draft_status' => 'draft',
                'sent_status' => 'sent',
                'paid_status' => 'paid',
                'overdue_status' => 'overdue',
            ]
        );

        $dailySalesTotal = (float) ($dailyTotalRow['amount'] ?? 0);
        $dailyDebtAmount = (float) ($dailyDebtAmountRow['amount'] ?? 0);
        $dailySalesCollected = max(0, $dailySalesTotal - $dailyDebtAmount);
        $dailySalesWithDebt = (int) ($dailyDebtInvoiceCountRow['amount'] ?? 0);

        $cash = $cashFromInvoices + $cashFromTransactions;
        $revenue = $revenueFromInvoices + $revenueFromOccasional;
        $expenses = $expensesFromTransactions;
        $prevRevenue = (float) ($prevRevenueRow['amount'] ?? 0) + (float) ($prevOccasionalIncomeRow['amount'] ?? 0);
        $prevExpenses = (float) ($prevExpenseRow['amount'] ?? 0);

        return [
            'cash' => $cash,
            'revenue' => $revenue,
            'expenses' => $expenses,
            'vat_due' => $vatDue,
            'cash_invoices' => $cashFromInvoices,
            'cash_transactions' => $cashFromTransactions,
            'revenue_invoices' => $revenueFromInvoices,
            'revenue_occasional' => $revenueFromOccasional,
            'expenses_transactions' => $expensesFromTransactions,
            'vat_from_invoices' => $vatFromInvoices,
            'vat_paid' => $vatPaid,
            'daily_sales_collected' => $dailySalesCollected,
            'daily_sales_total' => $dailySalesTotal,
            'daily_sales_with_debt_count' => $dailySalesWithDebt,
            'daily_debt_amount' => $dailyDebtAmount,
            'period_sales_with_debt_count' => $periodSalesWithDebtCount,
            'period_debt_amount' => $periodDebtAmount,
            'revenue_trend' => $this->computeTrendPercent($revenue, $prevRevenue),
            'expenses_trend' => $this->computeTrendPercent($expenses, $prevExpenses),
        ];
    }

    public function getRecentTransactions(int $companyId, int $limit = 6): array
    {
        $rows = $this->db->fetchAll(
            'SELECT t.id,
                    t.transaction_date,
                    t.description,
                    t.type,
                    COALESCE(SUM(j.debit), 0) AS debit_total,
                    COALESCE(SUM(j.credit), 0) AS credit_total,
                    MAX(a.name) AS account_name
             FROM transactions t
             LEFT JOIN journal_entries j ON j.transaction_id = t.id
             LEFT JOIN accounts a ON a.id = j.account_id
             WHERE t.company_id = :company_id
               AND t.status <> :void_status
             GROUP BY t.id, t.transaction_date, t.description, t.type
             ORDER BY t.transaction_date DESC, t.id DESC
             LIMIT ' . (int) max(1, min(20, $limit)),
            [
                'company_id' => $companyId,
                'void_status' => 'void',
            ]
        );

        foreach ($rows as &$row) {
            $type = (string) ($row['type'] ?? '');
            $isDebit = in_array($type, ['income', 'transfer', 'debt_payment'], true);
            $row['amount'] = $isDebit
                ? (float) ($row['debit_total'] ?? 0)
                : (float) ($row['credit_total'] ?? 0);
            $row['category'] = (string) ($row['account_name'] ?: ucfirst($type));
        }

        return $rows;
    }

    public function getCashReconciliation(int $companyId, ?array $period, int $limit = 12): array
    {
        if (!is_array($period)) {
            return [];
        }
        $fromDate = (string) ($period['start_date'] ?? '');
        $toDate = (string) ($period['end_date'] ?? '');
        if ($fromDate === '' || $toDate === '') {
            return [];
        }

        $driver = strtolower((string) \Config::DB_DRIVER);
        $invoiceDescriptionExpr = $driver === 'sqlite'
            ? "'Encaissement facture ' || i.invoice_number || ' - ' || COALESCE(i.customer_name, 'Client')"
            : "CONCAT('Encaissement facture ', i.invoice_number, ' - ', COALESCE(i.customer_name, 'Client'))";

        $ledgerSql = "
            SELECT
                i.id AS sort_id,
                COALESCE(i.paid_date, i.invoice_date) AS movement_date,
                'invoice' AS source,
                $invoiceDescriptionExpr AS label,
                CASE
                    WHEN i.paid_amount > 0 THEN i.paid_amount
                    WHEN i.status = :paid_status THEN i.total
                    ELSE 0
                END AS amount
            FROM invoices i
            WHERE i.company_id = :company_id
              AND i.status IN (:sent_status, :paid_status, :overdue_status)
              AND COALESCE(i.paid_date, i.invoice_date) BETWEEN :from_date AND :to_date
              AND (i.paid_amount > 0 OR i.status = :paid_status)
              AND NOT EXISTS (
                    SELECT 1 FROM invoice_payment_allocations a
                    WHERE a.invoice_id = i.id
              )

            UNION ALL

            SELECT
                (1000000000 + t.id) AS sort_id,
                t.transaction_date AS movement_date,
                'transaction' AS source,
                t.description AS label,
                COALESCE(SUM(j.debit - j.credit), 0) AS amount
            FROM transactions t
            LEFT JOIN journal_entries j ON j.transaction_id = t.id
            WHERE t.company_id = :company_id
              AND t.status <> :void_status
              AND t.transaction_date BETWEEN :from_date AND :to_date
            GROUP BY t.id, t.transaction_date, t.description
        ";

        $rows = $this->db->fetchAll(
            'SELECT movement_date, source, label, amount
             FROM (' . $ledgerSql . ') r
             WHERE r.amount <> 0
             ORDER BY r.movement_date DESC, r.sort_id DESC
             LIMIT ' . (int) max(1, min(50, $limit)),
            [
                'company_id' => $companyId,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'sent_status' => 'sent',
                'paid_status' => 'paid',
                'overdue_status' => 'overdue',
                'void_status' => 'void',
            ]
        );

        return $rows;
    }

    public function getAlerts(int $companyId, int $limit = 5): array
    {
        $storedAlerts = $this->db->fetchAll(
            'SELECT id, type, severity, title, message, created_at
             FROM alerts
             WHERE company_id = :company_id
               AND is_resolved = :is_resolved
             ORDER BY created_at DESC, id DESC
             LIMIT ' . (int) max(1, min(20, $limit)),
            [
                'company_id' => $companyId,
                'is_resolved' => 0,
            ]
        );

        $stockAlerts = $this->getStockAlerts($companyId);
        $merged = array_merge($stockAlerts, $storedAlerts);

        usort($merged, static function (array $left, array $right): int {
            $leftDate = strtotime((string) ($left['created_at'] ?? '')) ?: 0;
            $rightDate = strtotime((string) ($right['created_at'] ?? '')) ?: 0;
            return $rightDate <=> $leftDate;
        });

        return array_slice($merged, 0, max(1, min(20, $limit)));
    }

    private function getStockAlerts(int $companyId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, name, quantity, min_stock
             FROM products
             WHERE company_id = :company_id
               AND is_active = 1',
            ['company_id' => $companyId]
        );

        if ($rows === []) {
            return $this->getExpiryAlerts($companyId);
        }

        $outOfStock = [];
        $lowStock = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['name'] ?? 'Produit'));
            $qty = round((float) ($row['quantity'] ?? 0), 2);
            $min = round((float) ($row['min_stock'] ?? 0), 2);

            if ($id <= 0) {
                continue;
            }

            if ($qty <= 0) {
                $outOfStock[] = [
                    'id' => 'stock-out-' . $id,
                    'type' => 'stock',
                    'status_key' => 'out_of_stock',
                    'severity' => 'critical',
                    'title' => 'Rupture de stock',
                    'message' => $name . ' est en rupture de stock.',
                    'product_id' => $id,
                    'product_name' => $name,
                    'current_quantity' => $qty,
                    'min_stock' => $min,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
                continue;
            }

            if ($qty <= $min) {
                $lowStock[] = [
                    'id' => 'stock-low-' . $id,
                    'type' => 'stock',
                    'status_key' => 'low_stock',
                    'severity' => 'warning',
                    'title' => 'Stock bas',
                    'message' => $name . ' est en dessous du seuil minimum (' . number_format($qty, 2, '.', '') . ' / ' . number_format($min, 2, '.', '') . ').',
                    'product_id' => $id,
                    'product_name' => $name,
                    'current_quantity' => $qty,
                    'min_stock' => $min,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            }
        }

        return array_merge($outOfStock, $lowStock, $this->getExpiryAlerts($companyId));
    }

    private function getExpiryAlerts(int $companyId): array
    {
        $today = new \DateTimeImmutable('today');
        $threshold = $today->modify('+5 days')->format('Y-m-d');

        $rows = $this->db->fetchAll(
            'SELECT l.product_id, l.expiration_date, l.lot_code, p.name
             FROM stock_lots l
             INNER JOIN products p ON p.id = l.product_id
             WHERE l.company_id = :company_id
               AND p.is_active = 1
               AND COALESCE(l.is_declassified, 0) = 0
               AND l.quantity_remaining_base > 0
               AND l.expiration_date IS NOT NULL
               AND l.expiration_date <> \'\'
               AND l.expiration_date <= :threshold
             ORDER BY l.expiration_date ASC, l.id ASC',
            [
                'company_id' => $companyId,
                'threshold' => $threshold,
            ]
        );

        if ($rows === []) {
            return [];
        }

        $byProduct = [];
        foreach ($rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            if (!isset($byProduct[$productId])) {
                $byProduct[$productId] = [
                    'name' => (string) ($row['name'] ?? 'Produit'),
                    'expiration_date' => (string) ($row['expiration_date'] ?? ''),
                    'lot_code' => (string) ($row['lot_code'] ?? ''),
                ];
            }
        }

        $alerts = [];
        foreach ($byProduct as $productId => $payload) {
            $expRaw = trim((string) ($payload['expiration_date'] ?? ''));
            if ($expRaw === '') {
                continue;
            }
            $expDate = new \DateTimeImmutable($expRaw);
            $daysLeft = (int) $today->diff($expDate)->format('%r%a');
            $isExpired = $daysLeft < 0;
            $label = (string) ($payload['name'] ?? 'Produit');
            $lotCode = trim((string) ($payload['lot_code'] ?? ''));
            $lotSuffix = $lotCode !== '' ? ' (lot ' . $lotCode . ')' : '';
            $message = $isExpired
                ? $label . $lotSuffix . ' a deja expire le ' . $expDate->format('d/m/Y') . '.'
                : $label . $lotSuffix . ' expire le ' . $expDate->format('d/m/Y') . ' (J' . $daysLeft . ').';

            $alerts[] = [
                'id' => 'expiry-' . $productId,
                'type' => 'expiry',
                'status_key' => 'expiry',
                'severity' => 'critical',
                'title' => 'Expiration produit',
                'message' => $message,
                'product_id' => $productId,
                'product_name' => $label,
                'lot_code' => $lotCode,
                'expiration_date' => $expDate->format('Y-m-d'),
                'days_left' => $daysLeft,
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }

        return $alerts;
    }

    public function getCashflowSeries(int $companyId, string $period = '30d'): array
    {
        $today = new DateTimeImmutable('today');
        $period = in_array($period, ['7d', '30d', 'year'], true) ? $period : '30d';

        if ($period === '7d') {
            $start = $today->modify('-6 days');
            $end = $today;
        } elseif ($period === 'year') {
            $start = $today->modify('-11 months')->modify('first day of this month');
            $end = $today->modify('last day of this month');
        } else {
            $start = $today->modify('-29 days');
            $end = $today;
        }

        $rows = $this->db->fetchAll(
            'SELECT t.transaction_date,
                    t.type,
                    COALESCE(SUM(j.debit), 0) AS debit_total,
                    COALESCE(SUM(j.credit), 0) AS credit_total
             FROM transactions t
             INNER JOIN journal_entries j ON j.transaction_id = t.id
             WHERE t.company_id = :company_id
               AND t.status <> :void_status
               AND t.transaction_date BETWEEN :start_date AND :end_date
             GROUP BY t.transaction_date, t.type',
            [
                'company_id' => $companyId,
                'void_status' => 'void',
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
            ]
        );

        $invoiceRows = $this->db->fetchAll(
            'SELECT COALESCE(i.paid_date, i.invoice_date) AS movement_date,
                    COALESCE(SUM(
                        CASE
                            WHEN i.paid_amount > 0 THEN i.paid_amount
                            WHEN i.status = :paid_status THEN i.total
                            ELSE 0
                        END
                    ), 0) AS cash_total
             FROM invoices i
             WHERE i.company_id = :company_id
               AND i.status IN (:sent_status, :paid_status, :overdue_status)
               AND COALESCE(i.paid_date, i.invoice_date) BETWEEN :start_date AND :end_date
               AND (COALESCE(i.paid_amount, 0) > 0 OR i.status = :paid_status)
               AND NOT EXISTS (
                    SELECT 1 FROM invoice_payment_allocations a
                    WHERE a.invoice_id = i.id
               )
             GROUP BY movement_date',
            [
                'company_id' => $companyId,
                'sent_status' => 'sent',
                'paid_status' => 'paid',
                'overdue_status' => 'overdue',
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
            ]
        );

        $dailyInvoiceIncome = [];
        $dailyOccasionalIncome = [];
        $dailyExpenses = [];
        $dailyNet = [];
        foreach ($rows as $row) {
            $dateKey = (string) ($row['transaction_date'] ?? '');
            if ($dateKey === '') {
                continue;
            }

            $type = (string) ($row['type'] ?? '');
            $debit = (float) ($row['debit_total'] ?? 0);
            $credit = (float) ($row['credit_total'] ?? 0);

            if (in_array($type, ['income', 'debt_payment'], true)) {
                $dailyOccasionalIncome[$dateKey] = ($dailyOccasionalIncome[$dateKey] ?? 0.0) + $debit;
            } elseif ($type === 'expense') {
                $dailyExpenses[$dateKey] = ($dailyExpenses[$dateKey] ?? 0.0) + $credit;
            }

            if (in_array($type, ['income', 'debt_payment'], true)) {
                $dailyNet[$dateKey] = ($dailyNet[$dateKey] ?? 0.0) + $debit;
            } elseif ($type === 'expense') {
                $dailyNet[$dateKey] = ($dailyNet[$dateKey] ?? 0.0) - $credit;
            }
        }
        foreach ($invoiceRows as $row) {
            $key = (string) ($row['movement_date'] ?? '');
            if ($key === '') {
                continue;
            }
            $cashValue = (float) ($row['cash_total'] ?? 0);
            $dailyInvoiceIncome[$key] = ($dailyInvoiceIncome[$key] ?? 0.0) + $cashValue;
            $dailyNet[$key] = ($dailyNet[$key] ?? 0.0) + $cashValue;
        }

        $labels = [];
        $netValues = [];
        $invoiceIncomeValues = [];
        $occasionalIncomeValues = [];
        $expenseValues = [];
        if ($period === 'year') {
            $cursor = $start;
            while ($cursor <= $end) {
                $monthStart = $cursor->modify('first day of this month');
                $monthEnd = $cursor->modify('last day of this month');

                $netSum = 0.0;
                $invoiceIncomeSum = 0.0;
                $occasionalIncomeSum = 0.0;
                $expenseSum = 0.0;

                $dayCursor = $monthStart;
                while ($dayCursor <= $monthEnd) {
                    $key = $dayCursor->format('Y-m-d');
                    $netSum += (float) ($dailyNet[$key] ?? 0);
                    $invoiceIncomeSum += (float) ($dailyInvoiceIncome[$key] ?? 0);
                    $occasionalIncomeSum += (float) ($dailyOccasionalIncome[$key] ?? 0);
                    $expenseSum += (float) ($dailyExpenses[$key] ?? 0);
                    $dayCursor = $dayCursor->modify('+1 day');
                }

                $labels[] = $monthStart->format('M Y');
                $netValues[] = round($netSum, 2);
                $invoiceIncomeValues[] = round($invoiceIncomeSum, 2);
                $occasionalIncomeValues[] = round($occasionalIncomeSum, 2);
                $expenseValues[] = round($expenseSum, 2);
                $cursor = $cursor->modify('+1 month');
            }
        } else {
            $cursor = $start;
            while ($cursor <= $end) {
                $key = $cursor->format('Y-m-d');
                $labels[] = $cursor->format('d/m');
                $netValues[] = round((float) ($dailyNet[$key] ?? 0), 2);
                $invoiceIncomeValues[] = round((float) ($dailyInvoiceIncome[$key] ?? 0), 2);
                $occasionalIncomeValues[] = round((float) ($dailyOccasionalIncome[$key] ?? 0), 2);
                $expenseValues[] = round((float) ($dailyExpenses[$key] ?? 0), 2);
                $cursor = $cursor->modify('+1 day');
            }
        }

        return [
            'labels' => $labels,
            'net' => $netValues,
            'invoice_income' => $invoiceIncomeValues,
            'occasional_income' => $occasionalIncomeValues,
            'expenses' => $expenseValues,
        ];
    }

    public function getExpenseBreakdown(int $companyId, string $period = 'month', int $limit = 5): array
    {
        $today = new DateTimeImmutable('today');
        $period = in_array($period, ['month', 'last_month', 'year'], true) ? $period : 'month';
        if ($period === 'last_month') {
            $fromDate = $today->modify('first day of last month')->format('Y-m-d');
            $toDate = $today->modify('last day of last month')->format('Y-m-d');
        } elseif ($period === 'year') {
            $fromDate = $today->modify('-11 months')->modify('first day of this month')->format('Y-m-d');
            $toDate = $today->modify('last day of this month')->format('Y-m-d');
        } else {
            $fromDate = $today->modify('first day of this month')->format('Y-m-d');
            $toDate = $today->format('Y-m-d');
        }

        $rows = $this->db->fetchAll(
            'SELECT COALESCE(NULLIF(t.expense_subcategory, ""), "other") AS subcategory,
                    COALESCE(SUM(j.credit), 0) AS amount
             FROM transactions t
             INNER JOIN journal_entries j ON j.transaction_id = t.id
             WHERE t.company_id = :company_id
               AND t.type = :type
               AND t.status <> :void_status
               AND t.transaction_date BETWEEN :from_date AND :to_date
             GROUP BY COALESCE(NULLIF(t.expense_subcategory, ""), "other")
             ORDER BY amount DESC
             LIMIT ' . (int) max(1, min(10, $limit)),
            [
                'company_id' => $companyId,
                'type' => 'expense',
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

    private function buildInsights(array $stats, array $alerts): array
    {
        $insights = [];

        if ($alerts !== []) {
            foreach (array_slice($alerts, 0, 2) as $alert) {
                $severity = (string) ($alert['severity'] ?? 'info');
                $type = $severity === 'critical' ? 'warning' : $severity;
                if (!in_array($type, ['warning', 'info', 'success'], true)) {
                    $type = 'info';
                }

                $insights[] = [
                    'type' => $type,
                    'title' => (string) ($alert['title'] ?? 'Alerte'),
                    'message' => (string) ($alert['message'] ?? ''),
                ];
            }
        }

        if (($stats['revenue'] ?? 0) > ($stats['expenses'] ?? 0)) {
            $insights[] = [
                'type' => 'success',
                'title' => 'Marge positive',
                'message' => 'Les revenus du mois couvrent les depenses en cours.',
            ];
        }

        $insights[] = [
            'type' => 'info',
            'title' => 'TVA estimee',
            'message' => 'Montant TVA non reglee: $' . number_format((float) ($stats['vat_due'] ?? 0), 2),
        ];

        return array_slice($insights, 0, 4);
    }

    private function computeTrendPercent(float $current, float $previous): float
    {
        if ($previous <= 0.0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return (($current - $previous) / $previous) * 100;
    }

    private function normalizeDashboardOptions(array $options): array
    {
        $cashflowPeriod = (string) ($options['cashflow_period'] ?? '30d');
        $expensesPeriod = (string) ($options['expenses_period'] ?? 'month');

        if (!in_array($cashflowPeriod, ['7d', '30d', 'year'], true)) {
            $cashflowPeriod = '30d';
        }

        if (!in_array($expensesPeriod, ['month', 'last_month', 'year'], true)) {
            $expensesPeriod = 'month';
        }

        return [
            'cashflow_period' => $cashflowPeriod,
            'expenses_period' => $expensesPeriod,
        ];
    }

    private function getCacheFilePath(int $companyId, array $options = []): string
    {
        $cacheDir = (string) (\Config::CACHE_DIR ?? (dirname(__DIR__, 2) . '/storage/cache/'));
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        $normalized = $this->normalizeDashboardOptions($options);
        $suffix = $normalized['cashflow_period'] . '_' . $normalized['expenses_period'];

        return rtrim($cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dashboard_company_' . $companyId . '_' . $suffix . '.json';
    }

    private function writeCache(string $cacheFile, array $payload): void
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return;
        }

        @file_put_contents($cacheFile, $encoded, LOCK_EX);
    }
}
