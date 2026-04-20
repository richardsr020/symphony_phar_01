#!/usr/bin/env php
<?php

declare(strict_types=1);

define('SYMPHONY_ACCESS', true);
$root = dirname(__DIR__);
require $root . '/config.php';

use App\Core\Database;

$db = Database::getInstance();
$pdo = $db->getConnection();

$queryAll = static function (string $sql, array $params = []) use ($pdo): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
};

$queryOne = static function (string $sql, array $params = []) use ($pdo): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : [];
};

$printSection = static function (string $title): void {
    echo PHP_EOL . '== ' . $title . ' ==' . PHP_EOL;
};

$companies = $queryAll('SELECT id, name FROM companies ORDER BY id ASC');
if ($companies === []) {
    echo "Aucune entreprise en base.\n";
    exit(0);
}

foreach ($companies as $company) {
    $companyId = (int) ($company['id'] ?? 0);
    $companyName = (string) ($company['name'] ?? '');
    echo PHP_EOL . '### Company #' . $companyId . ' ' . $companyName . PHP_EOL;

    $printSection('Invoices: paid status with paid_amount=0');
    $rows = $queryAll(
        "SELECT id, invoice_number, invoice_date, paid_date, total, paid_amount, status
         FROM invoices
         WHERE company_id = :company_id
           AND status = 'paid'
           AND COALESCE(paid_amount, 0) <= 0.009
           AND COALESCE(total, 0) > 0
         ORDER BY invoice_date DESC, id DESC
         LIMIT 20",
        ['company_id' => $companyId]
    );
    echo $rows === [] ? "OK\n" : "SUSPECT (" . count($rows) . " shown)\n";
    foreach ($rows as $row) {
        echo "- invoice#" . (int) $row['id'] . " " . (string) $row['invoice_number']
            . " total=" . (float) $row['total'] . " paid_amount=" . (float) $row['paid_amount']
            . " paid_date=" . (string) ($row['paid_date'] ?? '') . "\n";
    }

    $printSection('Invoices: paid_amount > total');
    $rows = $queryAll(
        "SELECT id, invoice_number, invoice_date, total, paid_amount, status
         FROM invoices
         WHERE company_id = :company_id
           AND COALESCE(paid_amount, 0) > (COALESCE(total, 0) + 0.01)
         ORDER BY invoice_date DESC, id DESC
         LIMIT 20",
        ['company_id' => $companyId]
    );
    echo $rows === [] ? "OK\n" : "SUSPECT (" . count($rows) . " shown)\n";
    foreach ($rows as $row) {
        echo "- invoice#" . (int) $row['id'] . " " . (string) $row['invoice_number']
            . " total=" . (float) $row['total'] . " paid_amount=" . (float) $row['paid_amount']
            . " status=" . (string) $row['status'] . "\n";
    }

    $printSection('Invoices: allocations > paid_amount');
    $rows = $queryAll(
        "SELECT i.id, i.invoice_number, i.total, i.paid_amount,
                COALESCE(SUM(a.amount), 0) AS allocated
         FROM invoices i
         INNER JOIN invoice_payment_allocations a ON a.invoice_id = i.id
         WHERE i.company_id = :company_id
         GROUP BY i.id
         HAVING allocated > (COALESCE(i.paid_amount, 0) + 0.01)
         ORDER BY i.invoice_date DESC, i.id DESC
         LIMIT 20",
        ['company_id' => $companyId]
    );
    echo $rows === [] ? "OK\n" : "SUSPECT (" . count($rows) . " shown)\n";
    foreach ($rows as $row) {
        echo "- invoice#" . (int) $row['id'] . " " . (string) $row['invoice_number']
            . " paid_amount=" . (float) $row['paid_amount'] . " allocated=" . (float) $row['allocated'] . "\n";
    }

    $printSection('Invoices: direct payments not backed by allocations (paid_amount > allocated)');
    $rows = $queryAll(
        "SELECT i.id, i.invoice_number, i.invoice_date, i.paid_date, i.total, i.paid_amount,
                COALESCE(SUM(a.amount), 0) AS allocated
         FROM invoices i
         LEFT JOIN invoice_payment_allocations a ON a.invoice_id = i.id
         WHERE i.company_id = :company_id
         GROUP BY i.id
         HAVING allocated > 0.009
            AND (COALESCE(i.paid_amount, 0) - allocated) > 0.01
         ORDER BY i.invoice_date DESC, i.id DESC
         LIMIT 20",
        ['company_id' => $companyId]
    );
    echo $rows === [] ? "OK\n" : "INFO (" . count($rows) . " shown)\n";
    foreach ($rows as $row) {
        $gap = (float) $row['paid_amount'] - (float) $row['allocated'];
        echo "- invoice#" . (int) $row['id'] . " " . (string) $row['invoice_number']
            . " gap=" . round($gap, 2) . " paid_amount=" . (float) $row['paid_amount']
            . " allocated=" . (float) $row['allocated'] . "\n";
    }

    $printSection('Debt payment transactions: journal debit vs allocations sum');
    $rows = $queryAll(
        "SELECT t.id,
                t.transaction_date,
                t.reference,
                COALESCE(SUM(j.debit), 0) AS debit_total,
                COALESCE((SELECT SUM(a.amount) FROM invoice_payment_allocations a WHERE a.transaction_id = t.id), 0) AS allocated_total
         FROM transactions t
         LEFT JOIN journal_entries j ON j.transaction_id = t.id
         WHERE t.company_id = :company_id
           AND t.type = 'debt_payment'
           AND t.status <> 'void'
         GROUP BY t.id
         HAVING ABS(debit_total - allocated_total) > 0.01
         ORDER BY t.transaction_date DESC, t.id DESC
         LIMIT 20",
        ['company_id' => $companyId]
    );
    echo $rows === [] ? "OK\n" : "SUSPECT (" . count($rows) . " shown)\n";
    foreach ($rows as $row) {
        echo "- tx#" . (int) $row['id'] . " " . (string) $row['reference']
            . " date=" . (string) $row['transaction_date']
            . " debit=" . (float) $row['debit_total']
            . " allocated=" . (float) $row['allocated_total'] . "\n";
    }

    $printSection('Treasury snapshot (two methods)');
    $legacy = $queryOne(
        "SELECT
            COALESCE((
                SELECT SUM(CASE
                    WHEN paid_amount > 0 THEN paid_amount
                    WHEN status = 'paid' THEN total
                    ELSE 0
                END)
                FROM invoices
                WHERE company_id = :company_id
                  AND status IN ('sent','paid','overdue')
            ), 0) AS invoice_cash,
            COALESCE((
                SELECT SUM(CASE WHEN t.type = 'income' THEN j.debit ELSE 0 END)
                FROM transactions t
                INNER JOIN journal_entries j ON j.transaction_id = t.id
                WHERE t.company_id = :company_id
                  AND t.status <> 'void'
            ), 0) AS income_tx,
            COALESCE((
                SELECT SUM(CASE WHEN t.type = 'expense' THEN j.credit ELSE 0 END)
                FROM transactions t
                INNER JOIN journal_entries j ON j.transaction_id = t.id
                WHERE t.company_id = :company_id
                  AND t.status <> 'void'
            ), 0) AS expense_tx",
        ['company_id' => $companyId]
    );

    $new = $queryOne(
        "SELECT
            COALESCE((
                SELECT SUM(CASE
                    WHEN paid_amount > 0 THEN paid_amount
                    WHEN status = 'paid' THEN total
                    ELSE 0
                END)
                FROM invoices
                WHERE company_id = :company_id
                  AND status IN ('sent','paid','overdue')
                  AND NOT EXISTS (SELECT 1 FROM invoice_payment_allocations a WHERE a.invoice_id = invoices.id)
            ), 0) AS invoice_cash,
            COALESCE((
                SELECT SUM(CASE WHEN t.type IN ('income','debt_payment') THEN j.debit ELSE 0 END)
                FROM transactions t
                INNER JOIN journal_entries j ON j.transaction_id = t.id
                WHERE t.company_id = :company_id
                  AND t.status <> 'void'
            ), 0) AS income_tx,
            COALESCE((
                SELECT SUM(CASE WHEN t.type = 'expense' THEN j.credit ELSE 0 END)
                FROM transactions t
                INNER JOIN journal_entries j ON j.transaction_id = t.id
                WHERE t.company_id = :company_id
                  AND t.status <> 'void'
            ), 0) AS expense_tx",
        ['company_id' => $companyId]
    );

    $legacyCash = round(((float) ($legacy['invoice_cash'] ?? 0)) + ((float) ($legacy['income_tx'] ?? 0)) - ((float) ($legacy['expense_tx'] ?? 0)), 2);
    $newCash = round(((float) ($new['invoice_cash'] ?? 0)) + ((float) ($new['income_tx'] ?? 0)) - ((float) ($new['expense_tx'] ?? 0)), 2);
    echo "- legacy_cash=" . $legacyCash . "\n";
    echo "- new_cash=" . $newCash . "\n";
    echo "- diff=" . round($newCash - $legacyCash, 2) . "\n";
}

echo PHP_EOL . "Done.\n";

