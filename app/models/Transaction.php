<?php

namespace App\Models;

use DateTimeImmutable;

class Transaction extends Model
{
    private const TREASURY_EPSILON = 0.0001;
    public function getByCompanyPaginated(
        int $companyId,
        array $filters = [],
        int $page = 1,
        int $perPage = 20,
        string $sortBy = 'transaction_date',
        string $sortDir = 'desc'
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $driver = strtolower((string) \Config::DB_DRIVER);
        $invoiceDescriptionExpr = $driver === 'sqlite'
            ? "'Encaissement facture ' || i.invoice_number || ' - ' || COALESCE(i.customer_name, 'Client')"
            : "CONCAT('Encaissement facture ', i.invoice_number, ' - ', COALESCE(i.customer_name, 'Client'))";
        $accountExpr = $driver === 'sqlite'
            ? "(SELECT TRIM(COALESCE(a.code, '') || ' ' || COALESCE(a.name, '')) FROM journal_entries j2 INNER JOIN accounts a ON a.id = j2.account_id WHERE j2.transaction_id = t.id ORDER BY j2.id ASC LIMIT 1)"
            : "(SELECT TRIM(CONCAT(COALESCE(a.code, ''), ' ', COALESCE(a.name, ''))) FROM journal_entries j2 INNER JOIN accounts a ON a.id = j2.account_id WHERE j2.transaction_id = t.id ORDER BY j2.id ASC LIMIT 1)";
        $creatorNameExprTx = $driver === 'sqlite'
            ? "TRIM(COALESCE(tu.first_name, '') || ' ' || COALESCE(tu.last_name, ''))"
            : "TRIM(CONCAT(COALESCE(tu.first_name, ''), ' ', COALESCE(tu.last_name, '')))";
        $creatorNameExprInv = $driver === 'sqlite'
            ? "TRIM(COALESCE(iu.first_name, '') || ' ' || COALESCE(iu.last_name, ''))"
            : "TRIM(CONCAT(COALESCE(iu.first_name, ''), ' ', COALESCE(iu.last_name, '')))";

        $ledgerSql = "
            SELECT
                t.id AS id,
                t.id AS source_id,
                'transaction' AS source,
                t.transaction_date AS transaction_date,
                t.description AS description,
                t.reference AS reference,
                t.type AS type,
                t.expense_subcategory AS expense_subcategory,
                t.expense_fiscal_subcategory AS expense_fiscal_subcategory,
                t.expense_subcategory_other AS expense_subcategory_other,
                t.status AS status,
                t.fiscal_period_id AS fiscal_period_id,
                t.created_by AS created_by,
                COALESCE(NULLIF($creatorNameExprTx, ''), 'Utilisateur') AS created_by_name,
                COALESCE(SUM(j.debit), 0) AS debit_total,
                COALESCE(SUM(j.credit), 0) AS credit_total,
                COALESCE($accountExpr, '-') AS account_label
            FROM transactions t
            LEFT JOIN journal_entries j ON j.transaction_id = t.id
            LEFT JOIN users tu ON tu.id = t.created_by
            WHERE t.company_id = :company_id_tx
            GROUP BY t.id, t.transaction_date, t.description, t.reference, t.type, t.expense_subcategory, t.expense_fiscal_subcategory, t.expense_subcategory_other, t.status, t.fiscal_period_id, t.created_by, tu.first_name, tu.last_name

            UNION ALL

            SELECT
                (1000000000 + i.id) AS id,
                i.id AS source_id,
                'invoice_payment' AS source,
                COALESCE(i.paid_date, i.invoice_date) AS transaction_date,
                $invoiceDescriptionExpr AS description,
                i.invoice_number AS reference,
                'billing' AS type,
                NULL AS expense_subcategory,
                NULL AS expense_fiscal_subcategory,
                NULL AS expense_subcategory_other,
                'posted' AS status,
                i.fiscal_period_id AS fiscal_period_id,
                i.created_by AS created_by,
                COALESCE(NULLIF($creatorNameExprInv, ''), 'Utilisateur') AS created_by_name,
                COALESCE(i.paid_amount, 0) AS debit_total,
                0 AS credit_total,
                'Facture client' AS account_label
            FROM invoices i
            LEFT JOIN users iu ON iu.id = i.created_by
            WHERE i.company_id = :company_id_inv
              AND COALESCE(i.paid_amount, 0) > 0
              AND i.status IN ('sent', 'paid', 'overdue')
              AND NOT EXISTS (
                    SELECT 1 FROM invoice_payment_allocations a
                    WHERE a.invoice_id = i.id
                )
        ";

        $params = [
            'company_id_tx' => $companyId,
            'company_id_inv' => $companyId,
        ];
        $where = ['1=1'];

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, ['draft', 'posted', 'void'], true)) {
            $where[] = 'l.status = :status';
            $params['status'] = $status;
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '' && in_array($type, ['income', 'expense', 'transfer', 'journal', 'billing', 'debt_payment'], true)) {
            $where[] = 'l.type = :type';
            $params['type'] = $type;
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(l.description LIKE :search OR l.reference LIKE :search OR l.expense_subcategory LIKE :search OR l.expense_fiscal_subcategory LIKE :search OR l.expense_subcategory_other LIKE :search)';
            $params['search'] = '%' . $query . '%';
        }

        $fromDate = $this->normalizeDate((string) ($filters['from_date'] ?? ''));
        if ($fromDate !== null) {
            $where[] = 'l.transaction_date >= :from_date';
            $params['from_date'] = $fromDate;
        }

        $toDate = $this->normalizeDate((string) ($filters['to_date'] ?? ''));
        if ($toDate !== null) {
            $where[] = 'l.transaction_date <= :to_date';
            $params['to_date'] = $toDate;
        }

        $sortMap = [
            'transaction_date' => 'l.transaction_date',
            'description' => 'l.description',
            'type' => 'l.type',
            'debit_total' => 'debit_total',
            'credit_total' => 'credit_total',
            'status' => 'l.status',
        ];
        $sortColumn = $sortMap[$sortBy] ?? 'l.transaction_date';
        $direction = strtolower($sortDir) === 'asc' ? 'ASC' : 'DESC';

        $totalRow = $this->db->fetchOne(
            'SELECT COUNT(*) AS total
             FROM (' . $ledgerSql . ') l
             WHERE ' . implode(' AND ', $where),
            $params
        );
        $total = (int) ($totalRow['total'] ?? 0);

        $rows = $this->db->fetchAll(
            'SELECT l.id,
                    l.source_id,
                    l.source,
                    l.transaction_date,
                    l.description,
                    l.reference,
                    l.type,
                    l.expense_subcategory,
                    l.expense_fiscal_subcategory,
                    l.expense_subcategory_other,
                    l.status,
                    l.fiscal_period_id,
                    l.created_by,
                    l.created_by_name,
                    l.debit_total,
                    l.credit_total,
                    l.account_label
             FROM (' . $ledgerSql . ') l
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY ' . $sortColumn . ' ' . $direction . ', l.id DESC
             LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
            $params
        );
        if (count($rows) > $perPage) {
            $rows = array_slice($rows, 0, $perPage);
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
            'sort_by' => $sortBy,
            'sort_dir' => strtolower($direction),
        ];
    }

    public function getByCompany(int $companyId, array $filters = []): array
    {
        $driver = strtolower((string) \Config::DB_DRIVER);
        $invoiceDescriptionExpr = $driver === 'sqlite'
            ? "'Encaissement facture ' || i.invoice_number || ' - ' || COALESCE(i.customer_name, 'Client')"
            : "CONCAT('Encaissement facture ', i.invoice_number, ' - ', COALESCE(i.customer_name, 'Client'))";
        $accountExpr = $driver === 'sqlite'
            ? "(SELECT TRIM(COALESCE(a.code, '') || ' ' || COALESCE(a.name, '')) FROM journal_entries j2 INNER JOIN accounts a ON a.id = j2.account_id WHERE j2.transaction_id = t.id ORDER BY j2.id ASC LIMIT 1)"
            : "(SELECT TRIM(CONCAT(COALESCE(a.code, ''), ' ', COALESCE(a.name, ''))) FROM journal_entries j2 INNER JOIN accounts a ON a.id = j2.account_id WHERE j2.transaction_id = t.id ORDER BY j2.id ASC LIMIT 1)";
        $creatorNameExprTx = $driver === 'sqlite'
            ? "TRIM(COALESCE(tu.first_name, '') || ' ' || COALESCE(tu.last_name, ''))"
            : "TRIM(CONCAT(COALESCE(tu.first_name, ''), ' ', COALESCE(tu.last_name, '')))";
        $creatorNameExprInv = $driver === 'sqlite'
            ? "TRIM(COALESCE(iu.first_name, '') || ' ' || COALESCE(iu.last_name, ''))"
            : "TRIM(CONCAT(COALESCE(iu.first_name, ''), ' ', COALESCE(iu.last_name, '')))";

        $ledgerSql = "
            SELECT
                t.id AS id,
                t.id AS source_id,
                'transaction' AS source,
                t.transaction_date AS transaction_date,
                t.description AS description,
                t.reference AS reference,
                t.type AS type,
                t.expense_subcategory AS expense_subcategory,
                t.expense_fiscal_subcategory AS expense_fiscal_subcategory,
                t.expense_subcategory_other AS expense_subcategory_other,
                t.status AS status,
                t.fiscal_period_id AS fiscal_period_id,
                t.created_by AS created_by,
                COALESCE(NULLIF($creatorNameExprTx, ''), 'Utilisateur') AS created_by_name,
                COALESCE(SUM(j.debit), 0) AS debit_total,
                COALESCE(SUM(j.credit), 0) AS credit_total,
                COALESCE($accountExpr, '-') AS account_label
            FROM transactions t
            LEFT JOIN journal_entries j ON j.transaction_id = t.id
            LEFT JOIN users tu ON tu.id = t.created_by
            WHERE t.company_id = :company_id_tx
            GROUP BY t.id, t.transaction_date, t.description, t.reference, t.type, t.expense_subcategory, t.expense_fiscal_subcategory, t.expense_subcategory_other, t.status, t.fiscal_period_id, t.created_by, tu.first_name, tu.last_name

            UNION ALL

            SELECT
                (1000000000 + i.id) AS id,
                i.id AS source_id,
                'invoice_payment' AS source,
                COALESCE(i.paid_date, i.invoice_date) AS transaction_date,
                $invoiceDescriptionExpr AS description,
                i.invoice_number AS reference,
                'billing' AS type,
                NULL AS expense_subcategory,
                NULL AS expense_fiscal_subcategory,
                NULL AS expense_subcategory_other,
                'posted' AS status,
                i.fiscal_period_id AS fiscal_period_id,
                i.created_by AS created_by,
                COALESCE(NULLIF($creatorNameExprInv, ''), 'Utilisateur') AS created_by_name,
                COALESCE(i.paid_amount, 0) AS debit_total,
                0 AS credit_total,
                'Facture client' AS account_label
            FROM invoices i
            LEFT JOIN users iu ON iu.id = i.created_by
            WHERE i.company_id = :company_id_inv
              AND COALESCE(i.paid_amount, 0) > 0
              AND i.status IN ('sent', 'paid', 'overdue')
              AND NOT EXISTS (
                    SELECT 1 FROM invoice_payment_allocations a
                    WHERE a.invoice_id = i.id
                )
        ";

        $params = [
            'company_id_tx' => $companyId,
            'company_id_inv' => $companyId,
        ];
        $where = ['1=1'];

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, ['draft', 'posted', 'void'], true)) {
            $where[] = 'l.status = :status';
            $params['status'] = $status;
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '' && in_array($type, ['income', 'expense', 'transfer', 'journal', 'billing', 'debt_payment'], true)) {
            $where[] = 'l.type = :type';
            $params['type'] = $type;
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(l.description LIKE :search OR l.reference LIKE :search OR l.expense_subcategory LIKE :search OR l.expense_fiscal_subcategory LIKE :search OR l.expense_subcategory_other LIKE :search)';
            $params['search'] = '%' . $query . '%';
        }

        $fromDate = $this->normalizeDate((string) ($filters['from_date'] ?? ''));
        if ($fromDate !== null) {
            $where[] = 'l.transaction_date >= :from_date';
            $params['from_date'] = $fromDate;
        }

        $toDate = $this->normalizeDate((string) ($filters['to_date'] ?? ''));
        if ($toDate !== null) {
            $where[] = 'l.transaction_date <= :to_date';
            $params['to_date'] = $toDate;
        }

        return $this->db->fetchAll(
            'SELECT l.id,
                    l.source_id,
                    l.source,
                    l.transaction_date,
                    l.description,
                    l.reference,
                    l.type,
                    l.expense_subcategory,
                    l.expense_fiscal_subcategory,
                    l.expense_subcategory_other,
                    l.status,
                    l.fiscal_period_id,
                    l.created_by,
                    l.created_by_name,
                    l.debit_total,
                    l.credit_total,
                    l.account_label
             FROM (' . $ledgerSql . ') l
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY l.transaction_date DESC, l.id DESC',
            $params
        );
    }

    public function createManual(int $companyId, int $userId, array $payload): int
    {
        $description = trim((string) ($payload['description'] ?? ''));
        $type = trim((string) ($payload['type'] ?? ''));
        $status = trim((string) ($payload['status'] ?? 'draft'));
        $transactionDate = $this->normalizeDate((string) ($payload['transaction_date'] ?? ''));
        $amount = round((float) ($payload['amount'] ?? 0), 2);

        if ($description === '' || $transactionDate === null || $amount <= 0) {
            throw new \InvalidArgumentException('Les champs obligatoires de la transaction sont invalides.');
        }

        if (!in_array($type, ['income', 'expense', 'transfer', 'journal'], true)) {
            throw new \InvalidArgumentException('Type de transaction invalide.');
        }
        [$expenseSubcategory, $expenseFiscalSubcategory, $expenseSubcategoryOther] = $this->resolveExpenseMetadata($payload, $type);

        if (!in_array($status, ['draft', 'posted', 'void'], true)) {
            throw new \InvalidArgumentException('Statut de transaction invalide.');
        }

        $reference = $this->generateNextReference($companyId, (string) $transactionDate);

        $accountTypeByTransaction = [
            'income' => 'asset',
            'expense' => 'expense',
            'transfer' => 'asset',
            'journal' => 'asset',
        ];

        $isFiscalTreasuryFlow = $type === 'expense' && $expenseSubcategory === 'fiscal';
        $accountId = (int) ($payload['account_id'] ?? 0);
        $accountModel = new Account($this->db);

        if ($isFiscalTreasuryFlow) {
            $accountId = 0;
        }

        if ($accountId > 0) {
            $account = $accountModel->findById($companyId, $accountId);
            if ($account === null) {
                throw new \InvalidArgumentException('Compte comptable introuvable.');
            }
        } else {
            $accountId = $accountModel->getOrCreateSystemAccount(
                $companyId,
                $isFiscalTreasuryFlow ? 'asset' : $accountTypeByTransaction[$type]
            );
        }

        $isDebit = $type === 'income' || $type === 'transfer';
        $debit = $isDebit ? $amount : 0.00;
        $credit = $isDebit ? 0.00 : $amount;

        if ($credit > 0) {
            $this->assertTreasuryCanCoverAmount($companyId, $credit);
        }

        $fiscalPeriodId = (new FiscalPeriod($this->db))->resolvePeriodIdForDate($companyId, (string) $transactionDate);

        $this->db->beginTransaction();

        try {
            $this->db->execute(
                'INSERT INTO transactions (company_id, transaction_date, description, reference, type, expense_subcategory, expense_fiscal_subcategory, expense_subcategory_other, status, fiscal_period_id, created_by)
                 VALUES (:company_id, :transaction_date, :description, :reference, :type, :expense_subcategory, :expense_fiscal_subcategory, :expense_subcategory_other, :status, :fiscal_period_id, :created_by)',
                [
                    'company_id' => $companyId,
                    'transaction_date' => $transactionDate,
                    'description' => $description,
                    'reference' => $reference,
                    'type' => $type,
                    'expense_subcategory' => $expenseSubcategory,
                    'expense_fiscal_subcategory' => $expenseFiscalSubcategory,
                    'expense_subcategory_other' => $expenseSubcategoryOther,
                    'status' => $status,
                    'fiscal_period_id' => $fiscalPeriodId,
                    'created_by' => $userId,
                ]
            );

            $transactionId = $this->db->lastInsertId();

            $this->db->execute(
                'INSERT INTO journal_entries (transaction_id, account_id, debit, credit, description)
                 VALUES (:transaction_id, :account_id, :debit, :credit, :description)',
                [
                    'transaction_id' => $transactionId,
                    'account_id' => $accountId,
                    'debit' => $debit,
                    'credit' => $credit,
                    'description' => $description,
                ]
            );

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }

        return $transactionId;
    }

    public function createDebtPayment(int $companyId, int $userId, array $payload): int
    {
        $transactionDate = $this->normalizeDate((string) ($payload['transaction_date'] ?? ''));
        $amount = round((float) ($payload['amount'] ?? 0), 2);
        $clientName = trim((string) ($payload['debt_client_name'] ?? $payload['client_name'] ?? ''));
        $clientPhoneRaw = trim((string) ($payload['debt_client_phone'] ?? $payload['client_phone'] ?? ''));
        $clientPhone = $this->normalizePhone($clientPhoneRaw);

        if ($transactionDate === null || $amount <= 0) {
            throw new \InvalidArgumentException('Montant ou date de paiement invalide.');
        }
        if ($clientName === '' && $clientPhone === '') {
            throw new \InvalidArgumentException('Client endette introuvable.');
        }

        $invoiceModel = new Invoice($this->db);
        $invoices = $invoiceModel->listOutstandingInvoicesForClient($companyId, $clientName, $clientPhone);
        if ($invoices === []) {
            throw new \InvalidArgumentException('Aucune facture impayee pour ce client.');
        }

        $remaining = $amount;
        $allocations = [];
        $totalDebt = 0.0;

        foreach ($invoices as $invoice) {
            $invoiceId = (int) ($invoice['id'] ?? 0);
            if ($invoiceId <= 0) {
                continue;
            }
            $total = round((float) ($invoice['total'] ?? 0), 2);
            $paid = round((float) ($invoice['paid_amount'] ?? 0), 2);
            $balance = max($total - $paid, 0);
            $totalDebt += $balance;
            if ($remaining <= 0.009 || $balance <= 0) {
                continue;
            }
            $apply = min($balance, $remaining);
            $apply = round($apply, 2);
            if ($apply <= 0) {
                continue;
            }
            $allocations[] = [
                'invoice_id' => $invoiceId,
                'amount' => $apply,
            ];
            $remaining = round(max($remaining - $apply, 0), 2);
        }

        if ($amount > $totalDebt + 0.01) {
            throw new \InvalidArgumentException('Montant superieur a la dette du client.');
        }

        if ($allocations === []) {
            throw new \InvalidArgumentException('Impossible d\'affecter le paiement aux factures.');
        }

        $description = trim((string) ($payload['description'] ?? ''));
        if ($description === '') {
            $label = $clientName !== '' ? $clientName : $clientPhone;
            $description = 'Remboursement de dettes' . ($label !== '' ? ' - ' . $label : '');
        }

        $reference = $this->generateNextReference($companyId, (string) $transactionDate);
        $accountId = (int) ($payload['account_id'] ?? 0);
        $accountModel = new Account($this->db);

        if ($accountId > 0) {
            $account = $accountModel->findById($companyId, $accountId);
            if ($account === null) {
                throw new \InvalidArgumentException('Compte comptable introuvable.');
            }
        } else {
            $accountId = $accountModel->getOrCreateSystemAccount($companyId, 'asset');
        }

        $fiscalPeriodId = (new FiscalPeriod($this->db))->resolvePeriodIdForDate($companyId, (string) $transactionDate);
        $receiptNumber = $this->generateNextReceiptNumber($companyId, (string) $transactionDate);
        $paymentGroupRef = 'PAY-' . $reference;

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                'INSERT INTO transactions (company_id, transaction_date, description, reference, type, expense_subcategory, expense_fiscal_subcategory, expense_subcategory_other, status, fiscal_period_id, created_by)
                 VALUES (:company_id, :transaction_date, :description, :reference, :type, NULL, NULL, NULL, :status, :fiscal_period_id, :created_by)',
                [
                    'company_id' => $companyId,
                    'transaction_date' => $transactionDate,
                    'description' => $description,
                    'reference' => $reference,
                    'type' => 'debt_payment',
                    'status' => 'posted',
                    'fiscal_period_id' => $fiscalPeriodId,
                    'created_by' => $userId,
                ]
            );

            $transactionId = $this->db->lastInsertId();

            $this->db->execute(
                'INSERT INTO journal_entries (transaction_id, account_id, debit, credit, description)
                 VALUES (:transaction_id, :account_id, :debit, :credit, :description)',
                [
                    'transaction_id' => $transactionId,
                    'account_id' => $accountId,
                    'debit' => $amount,
                    'credit' => 0.0,
                    'description' => $description,
                ]
            );

            foreach ($allocations as $allocation) {
                $invoiceId = (int) ($allocation['invoice_id'] ?? 0);
                $paymentAmount = round((float) ($allocation['amount'] ?? 0), 2);
                if ($invoiceId <= 0 || $paymentAmount <= 0) {
                    continue;
                }

                $this->db->execute(
                    'INSERT INTO invoice_payment_allocations (company_id, transaction_id, invoice_id, payment_group_ref, receipt_number, client_name, client_phone, amount, created_by)
                     VALUES (:company_id, :transaction_id, :invoice_id, :payment_group_ref, :receipt_number, :client_name, :client_phone, :amount, :created_by)',
                    [
                        'company_id' => $companyId,
                        'transaction_id' => $transactionId,
                        'invoice_id' => $invoiceId,
                        'payment_group_ref' => $paymentGroupRef,
                        'receipt_number' => $receiptNumber,
                        'client_name' => $clientName !== '' ? $clientName : null,
                        'client_phone' => $clientPhoneRaw !== '' ? $clientPhoneRaw : null,
                        'amount' => $paymentAmount,
                        'created_by' => $userId > 0 ? $userId : null,
                    ]
                );

                if (!$invoiceModel->registerPayment($companyId, $invoiceId, $paymentAmount)) {
                    throw new \RuntimeException('Echec enregistrement paiement facture.');
                }
            }

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }

        return $transactionId;
    }

    public function findByIdForCompany(int $companyId, int $transactionId): ?array
    {
        $transaction = $this->db->fetchOne(
            'SELECT t.id, t.company_id, t.transaction_date, t.description, t.reference, t.type, t.expense_subcategory, t.expense_fiscal_subcategory, t.expense_subcategory_other, t.status, t.fiscal_period_id, t.created_by, t.created_at, t.updated_at,
                    u.first_name AS created_by_first_name, u.last_name AS created_by_last_name
             FROM transactions t
             LEFT JOIN users u ON u.id = t.created_by
             WHERE t.company_id = :company_id
               AND t.id = :id
             LIMIT 1',
            [
                'company_id' => $companyId,
                'id' => $transactionId,
            ]
        );

        if ($transaction === null) {
            return null;
        }

        $entries = $this->db->fetchAll(
            'SELECT j.id, j.account_id, j.debit, j.credit, j.description, a.code, a.name
             FROM journal_entries j
             INNER JOIN accounts a ON a.id = j.account_id
             WHERE j.transaction_id = :transaction_id
             ORDER BY j.id ASC',
            ['transaction_id' => $transactionId]
        );

        $fullName = trim((string) (($transaction['created_by_first_name'] ?? '') . ' ' . ($transaction['created_by_last_name'] ?? '')));
        $transaction['created_by_name'] = $fullName !== '' ? $fullName : 'Utilisateur';
        $transaction['entries'] = $entries;

        return $transaction;
    }

    public function getDebtPaymentReceipt(int $companyId, int $transactionId): ?array
    {
        $transaction = $this->findByIdForCompany($companyId, $transactionId);
        if ($transaction === null) {
            return null;
        }
        if ((string) ($transaction['type'] ?? '') !== 'debt_payment') {
            return null;
        }

        $rows = $this->db->fetchAll(
            'SELECT a.amount,
                    a.receipt_number,
                    a.payment_group_ref,
                    a.client_name,
                    a.client_phone,
                    i.invoice_number,
                    i.invoice_date,
                    i.total,
                    i.paid_amount
             FROM invoice_payment_allocations a
             INNER JOIN invoices i ON i.id = a.invoice_id
             WHERE a.company_id = :company_id
               AND a.transaction_id = :transaction_id
             ORDER BY i.invoice_date ASC, i.id ASC',
            [
                'company_id' => $companyId,
                'transaction_id' => $transactionId,
            ]
        );

        if ($rows === []) {
            return null;
        }

        $receiptNumber = (string) ($rows[0]['receipt_number'] ?? '');
        $clientName = (string) ($rows[0]['client_name'] ?? '');
        $clientPhone = (string) ($rows[0]['client_phone'] ?? '');
        $totalPaid = 0.0;
        $allocations = [];

        foreach ($rows as $row) {
            $amount = round((float) ($row['amount'] ?? 0), 2);
            $totalPaid += $amount;
            $invoiceTotal = round((float) ($row['total'] ?? 0), 2);
            $invoicePaid = round((float) ($row['paid_amount'] ?? 0), 2);
            $remaining = max($invoiceTotal - $invoicePaid, 0);
            $allocations[] = [
                'invoice_number' => (string) ($row['invoice_number'] ?? ''),
                'invoice_date' => (string) ($row['invoice_date'] ?? ''),
                'invoice_total' => $invoiceTotal,
                'amount' => $amount,
                'remaining' => $remaining,
            ];
        }

        return [
            'transaction_id' => $transactionId,
            'transaction_date' => (string) ($transaction['transaction_date'] ?? ''),
            'created_at' => (string) ($transaction['created_at'] ?? ''),
            'receipt_number' => $receiptNumber,
            'payment_group_ref' => (string) ($rows[0]['payment_group_ref'] ?? ''),
            'client_name' => $clientName !== '' ? $clientName : (string) ($rows[0]['client_name'] ?? ''),
            'client_phone' => $clientPhone !== '' ? $clientPhone : (string) ($rows[0]['client_phone'] ?? ''),
            'total_paid' => round($totalPaid, 2),
            'allocations' => $allocations,
        ];
    }

    public function updateManual(int $companyId, int $transactionId, array $payload): bool
    {
        $existing = $this->findByIdForCompany($companyId, $transactionId);
        if ($existing === null) {
            throw new \InvalidArgumentException('Transaction introuvable.');
        }
        if ((string) ($existing['type'] ?? '') === 'debt_payment') {
            throw new \InvalidArgumentException('Transaction de remboursement non modifiable.');
        }

        $description = trim((string) ($payload['description'] ?? ''));
        $type = trim((string) ($payload['type'] ?? ''));
        $status = trim((string) ($payload['status'] ?? 'draft'));
        $transactionDate = $this->normalizeDate((string) ($payload['transaction_date'] ?? ''));
        $amount = round((float) ($payload['amount'] ?? 0), 2);

        if ($description === '' || $transactionDate === null || $amount <= 0) {
            throw new \InvalidArgumentException('Les champs obligatoires de la transaction sont invalides.');
        }

        if (!in_array($type, ['income', 'expense', 'transfer', 'journal'], true)) {
            throw new \InvalidArgumentException('Type de transaction invalide.');
        }
        [$expenseSubcategory, $expenseFiscalSubcategory, $expenseSubcategoryOther] = $this->resolveExpenseMetadata($payload, $type);

        if (!in_array($status, ['draft', 'posted', 'void'], true)) {
            throw new \InvalidArgumentException('Statut de transaction invalide.');
        }

        $reference = trim((string) ($existing['reference'] ?? ''));
        if ($reference === '') {
            $reference = $this->generateNextReference($companyId, (string) $transactionDate);
        }

        $accountTypeByTransaction = [
            'income' => 'asset',
            'expense' => 'expense',
            'transfer' => 'asset',
            'journal' => 'asset',
        ];

        $isFiscalTreasuryFlow = $type === 'expense' && $expenseSubcategory === 'fiscal';
        $accountId = (int) ($payload['account_id'] ?? 0);
        $accountModel = new Account($this->db);

        if ($isFiscalTreasuryFlow) {
            $accountId = 0;
        }

        if ($accountId > 0) {
            $account = $accountModel->findById($companyId, $accountId);
            if ($account === null) {
                throw new \InvalidArgumentException('Compte comptable introuvable.');
            }
        } else {
            $accountId = $accountModel->getOrCreateSystemAccount(
                $companyId,
                $isFiscalTreasuryFlow ? 'asset' : $accountTypeByTransaction[$type]
            );
        }

        $isDebit = $type === 'income' || $type === 'transfer';
        $debit = $isDebit ? $amount : 0.00;
        $credit = $isDebit ? 0.00 : $amount;

        $existingDebit = 0.0;
        $existingCredit = 0.0;
        foreach ((array) ($existing['entries'] ?? []) as $entry) {
            $existingDebit += (float) ($entry['debit'] ?? 0);
            $existingCredit += (float) ($entry['credit'] ?? 0);
        }
        $existingNet = $existingDebit - $existingCredit;
        $newNet = $debit - $credit;
        $netDelta = $newNet - $existingNet;
        if ($netDelta < 0) {
            $this->assertTreasuryCanCoverAmount($companyId, abs($netDelta));
        }

        $fiscalPeriodId = (new FiscalPeriod($this->db))->resolvePeriodIdForDate($companyId, (string) $transactionDate);
        $this->db->beginTransaction();

        try {
            $this->db->execute(
                'UPDATE transactions
                 SET transaction_date = :transaction_date,
                     description = :description,
                     reference = :reference,
                     type = :type,
                     expense_subcategory = :expense_subcategory,
                     expense_fiscal_subcategory = :expense_fiscal_subcategory,
                     expense_subcategory_other = :expense_subcategory_other,
                     status = :status,
                     fiscal_period_id = :fiscal_period_id
                 WHERE id = :id
                   AND company_id = :company_id',
                [
                    'transaction_date' => $transactionDate,
                    'description' => $description,
                    'reference' => $reference,
                    'type' => $type,
                    'expense_subcategory' => $expenseSubcategory,
                    'expense_fiscal_subcategory' => $expenseFiscalSubcategory,
                    'expense_subcategory_other' => $expenseSubcategoryOther,
                    'status' => $status,
                    'fiscal_period_id' => $fiscalPeriodId,
                    'id' => $transactionId,
                    'company_id' => $companyId,
                ]
            );

            $entry = $this->db->fetchOne(
                'SELECT id
                 FROM journal_entries
                 WHERE transaction_id = :transaction_id
                 ORDER BY id ASC
                 LIMIT 1',
                ['transaction_id' => $transactionId]
            );

            if ($entry === null) {
                $this->db->execute(
                    'INSERT INTO journal_entries (transaction_id, account_id, debit, credit, description)
                     VALUES (:transaction_id, :account_id, :debit, :credit, :description)',
                    [
                        'transaction_id' => $transactionId,
                        'account_id' => $accountId,
                        'debit' => $debit,
                        'credit' => $credit,
                        'description' => $description,
                    ]
                );
            } else {
                $this->db->execute(
                    'UPDATE journal_entries
                     SET account_id = :account_id,
                         debit = :debit,
                         credit = :credit,
                         description = :description
                     WHERE id = :id',
                    [
                        'account_id' => $accountId,
                        'debit' => $debit,
                        'credit' => $credit,
                        'description' => $description,
                        'id' => (int) $entry['id'],
                    ]
                );

                $this->db->execute(
                    'DELETE FROM journal_entries
                     WHERE transaction_id = :transaction_id
                       AND id <> :entry_id',
                    [
                        'transaction_id' => $transactionId,
                        'entry_id' => (int) $entry['id'],
                    ]
                );
            }

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }

        return true;
    }

    public function deleteForCompany(int $companyId, int $transactionId): bool
    {
        $existing = $this->findByIdForCompany($companyId, $transactionId);
        if ($existing === null) {
            return false;
        }

        if ((string) ($existing['type'] ?? '') === 'debt_payment') {
            return false;
        }

        $this->db->execute(
            'DELETE FROM transactions
             WHERE id = :id
               AND company_id = :company_id',
            [
                'id' => $transactionId,
                'company_id' => $companyId,
            ]
        );

        return true;
    }

    private function normalizeDate(string $date): ?string
    {
        $date = trim($date);
        if ($date === '') {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if ($parsed === false) {
            return null;
        }

        return $parsed->format('Y-m-d');
    }

    private function resolveExpenseMetadata(array $payload, string $type): array
    {
        if ($type !== 'expense') {
            return [null, null, null];
        }

        $subcategory = $this->normalizeExpenseSubcategory((string) ($payload['expense_subcategory'] ?? ''));
        if ($subcategory === null) {
            throw new \InvalidArgumentException('Sous-categorie de depense invalide.');
        }

        $otherDescription = trim((string) ($payload['expense_subcategory_other'] ?? ''));
        if ($subcategory === 'other' && $otherDescription === '') {
            throw new \InvalidArgumentException('Veuillez decrire la sous-categorie "autre".');
        }

        if ($subcategory !== 'other') {
            $otherDescription = '';
        }

        $fiscalSubcategory = null;
        if ($subcategory === 'fiscal') {
            $fiscalSubcategory = $this->normalizeFiscalExpenseSubcategory((string) ($payload['expense_fiscal_subcategory'] ?? ''));
            if ($fiscalSubcategory === null) {
                throw new \InvalidArgumentException('Veuillez selectionner la sous-categorie fiscale.');
            }
        }

        return [
            $subcategory,
            $fiscalSubcategory,
            $otherDescription !== '' ? substr($otherDescription, 0, 255) : null,
        ];
    }

    private function normalizeExpenseSubcategory(string $value): ?string
    {
        $normalized = trim($value);
        $allowed = ['fiscal', 'salarial', 'achat_stock', 'loyer', 'electricite', 'eau', 'internet', 'other'];

        if (!in_array($normalized, $allowed, true)) {
            return null;
        }

        return $normalized;
    }

    private function normalizeFiscalExpenseSubcategory(string $value): ?string
    {
        $normalized = trim($value);
        $allowed = ['impot', 'tax', 'versement_tva'];
        if (!in_array($normalized, $allowed, true)) {
            return null;
        }

        return $normalized;
    }

    public function generateNextReference(int $companyId, string $transactionDate): string
    {
        $prefix = 'TX-' . date('Ym', strtotime($transactionDate)) . '-';
        $row = $this->db->fetchOne(
            'SELECT reference
             FROM transactions
             WHERE company_id = :company_id
               AND reference LIKE :reference_like
             ORDER BY id DESC
             LIMIT 1',
            [
                'company_id' => $companyId,
                'reference_like' => $prefix . '%',
            ]
        );

        $next = 1;
        $lastReference = trim((string) ($row['reference'] ?? ''));
        if ($lastReference !== '' && preg_match('/^' . preg_quote($prefix, '/') . '(\d{4,})$/', $lastReference, $matches) === 1) {
            $next = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function generateNextReceiptNumber(int $companyId, string $transactionDate): string
    {
        $prefix = 'RC-' . date('Ym', strtotime($transactionDate)) . '-';
        $row = $this->db->fetchOne(
            'SELECT receipt_number
             FROM invoice_payment_allocations
             WHERE company_id = :company_id
               AND receipt_number LIKE :reference_like
             ORDER BY id DESC
             LIMIT 1',
            [
                'company_id' => $companyId,
                'reference_like' => $prefix . '%',
            ]
        );

        $next = 1;
        $lastReference = trim((string) ($row['receipt_number'] ?? ''));
        if ($lastReference !== '' && preg_match('/^' . preg_quote($prefix, '/') . '(\d{4,})$/', $lastReference, $matches) === 1) {
            $next = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function normalizePhone(string $value): string
    {
        return preg_replace('/[^0-9]+/', '', trim($value));
    }

    private function assertTreasuryCanCoverAmount(int $companyId, float $requiredAmount): void
    {
        $requiredAmount = round(max(0, $requiredAmount), 2);
        if ($requiredAmount <= 0) {
            return;
        }

        $available = $this->getAvailableTreasuryBalance($companyId);
        if (($available + self::TREASURY_EPSILON) < $requiredAmount) {
            throw new \InvalidArgumentException('Tresorerie insuffisante pour cette transaction.');
        }
    }

    private function getAvailableTreasuryBalance(int $companyId): float
    {
        $transactionsRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(j.debit - j.credit), 0) AS amount
             FROM transactions t
             INNER JOIN journal_entries j ON j.transaction_id = t.id
             WHERE t.company_id = :company_id
               AND t.status <> :void_status',
            [
                'company_id' => $companyId,
                'void_status' => 'void',
            ]
        );

        $invoicesRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(
                        CASE
                            WHEN paid_amount > 0 THEN paid_amount
                            WHEN status = :paid_status THEN total
                            ELSE 0
                        END
                    ), 0) AS amount
             FROM invoices
             WHERE company_id = :company_id
               AND status IN (:sent_status, :paid_status, :overdue_status)
               AND NOT EXISTS (
                    SELECT 1 FROM invoice_payment_allocations a
                    WHERE a.invoice_id = invoices.id
                )',
            [
                'company_id' => $companyId,
                'sent_status' => 'sent',
                'paid_status' => 'paid',
                'overdue_status' => 'overdue',
            ]
        );

        return round(
            (float) ($transactionsRow['amount'] ?? 0)
            + (float) ($invoicesRow['amount'] ?? 0),
            2
        );
    }
}
