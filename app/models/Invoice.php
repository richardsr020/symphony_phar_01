<?php

namespace App\Models;

use DateTimeImmutable;

class Invoice extends Model
{
    private function hasStockReservation(?array $invoice): bool
    {
        if (!is_array($invoice)) {
            return false;
        }
        $items = $invoice['items'] ?? [];
        if (!is_array($items)) {
            return false;
        }
        foreach ($items as $item) {
            $movementId = (int) ($item['stock_movement_id'] ?? 0);
            if ($movementId > 0) {
                return true;
            }
        }
        return false;
    }

    public function getByCompanyPaginated(
        int $companyId,
        array $filters = [],
        int $page = 1,
        int $perPage = 20,
        string $sortBy = 'invoice_date',
        string $sortDir = 'desc'
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $driver = strtolower((string) \Config::DB_DRIVER);
        $creatorExpr = $driver === 'sqlite'
            ? "TRIM(COALESCE(u.first_name, '') || ' ' || COALESCE(u.last_name, ''))"
            : "TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')))";

        $params = ['company_id' => $companyId];
        $where = ['i.company_id = :company_id'];

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, ['draft', 'sent', 'paid', 'overdue', 'cancelled'], true)) {
            $where[] = 'i.status = :status';
            $params['status'] = $status;
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(i.invoice_number LIKE :search OR i.customer_name LIKE :search OR COALESCE(i.customer_phone, \'\') LIKE :search)';
            $params['search'] = '%' . $query . '%';
        }

        $sortMap = [
            'invoice_number' => 'i.invoice_number',
            'customer_name' => 'i.customer_name',
            'invoice_date' => 'i.invoice_date',
            'due_date' => 'i.due_date',
            'total' => 'i.total',
            'status' => 'i.status',
        ];
        $sortColumn = $sortMap[$sortBy] ?? 'i.invoice_date';
        $direction = strtolower($sortDir) === 'asc' ? 'ASC' : 'DESC';

        $totalRow = $this->db->fetchOne(
            'SELECT COUNT(*) AS total
             FROM invoices i
             WHERE ' . implode(' AND ', $where),
            $params
        );
        $total = (int) ($totalRow['total'] ?? 0);

        $rows = $this->db->fetchAll(
            'SELECT i.id,
                    i.document_type,
                    i.invoice_number,
                    i.invoice_date,
                    i.due_date,
                    i.customer_name,
                    i.customer_phone,
                    i.subtotal,
                    i.tax_rate,
                    i.tax_amount,
                    i.total,
                    i.paid_amount,
                    i.status,
                    i.invoice_type,
                    i.fiscal_period_id,
                    i.downloaded_at,
                    i.created_at,
                    i.created_by,
                    COALESCE(NULLIF(' . $creatorExpr . ', \'\'), \'Utilisateur\') AS created_by_name
             FROM invoices i
             LEFT JOIN users u ON u.id = i.created_by
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY ' . $sortColumn . ' ' . $direction . ', i.id DESC
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

    public function getByCompany(int $companyId): array
    {
        $driver = strtolower((string) \Config::DB_DRIVER);
        $creatorExpr = $driver === 'sqlite'
            ? "TRIM(COALESCE(u.first_name, '') || ' ' || COALESCE(u.last_name, ''))"
            : "TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')))";

        return $this->db->fetchAll(
            'SELECT i.id,
                    i.document_type,
                    i.invoice_number,
                    i.invoice_date,
                    i.due_date,
                    i.customer_name,
                    i.customer_phone,
                    i.subtotal,
                    i.tax_rate,
                    i.tax_amount,
                    i.total,
                    i.paid_amount,
                    i.status,
                    i.invoice_type,
                    i.fiscal_period_id,
                    i.downloaded_at,
                    i.created_at,
                    i.created_by,
                    COALESCE(NULLIF(' . $creatorExpr . ', \'\'), \'Utilisateur\') AS created_by_name
             FROM invoices i
             LEFT JOIN users u ON u.id = i.created_by
             WHERE i.company_id = :company_id
             ORDER BY i.invoice_date DESC, i.id DESC',
            ['company_id' => $companyId]
        );
    }

    public function getByCompanyFiltered(
        int $companyId,
        array $filters = [],
        string $sortBy = 'invoice_date',
        string $sortDir = 'desc'
    ): array {
        $driver = strtolower((string) \Config::DB_DRIVER);
        $creatorExpr = $driver === 'sqlite'
            ? "TRIM(COALESCE(u.first_name, '') || ' ' || COALESCE(u.last_name, ''))"
            : "TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')))";

        $params = ['company_id' => $companyId];
        $where = ['i.company_id = :company_id'];

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, ['draft', 'sent', 'paid', 'overdue', 'cancelled'], true)) {
            $where[] = 'i.status = :status';
            $params['status'] = $status;
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(i.invoice_number LIKE :search OR i.customer_name LIKE :search OR COALESCE(i.customer_phone, \'\') LIKE :search)';
            $params['search'] = '%' . $query . '%';
        }

        $sortMap = [
            'invoice_number' => 'i.invoice_number',
            'customer_name' => 'i.customer_name',
            'invoice_date' => 'i.invoice_date',
            'due_date' => 'i.due_date',
            'total' => 'i.total',
            'status' => 'i.status',
        ];
        $sortColumn = $sortMap[$sortBy] ?? 'i.invoice_date';
        $direction = strtolower($sortDir) === 'asc' ? 'ASC' : 'DESC';

        return $this->db->fetchAll(
            'SELECT i.id,
                    i.document_type,
                    i.invoice_number,
                    i.invoice_date,
                    i.due_date,
                    i.customer_name,
                    i.customer_phone,
                    i.subtotal,
                    i.tax_rate,
                    i.tax_amount,
                    i.total,
                    i.paid_amount,
                    i.status,
                    i.invoice_type,
                    i.fiscal_period_id,
                    i.downloaded_at,
                    i.created_at,
                    i.created_by,
                    COALESCE(NULLIF(' . $creatorExpr . ', \'\'), \'Utilisateur\') AS created_by_name
             FROM invoices i
             LEFT JOIN users u ON u.id = i.created_by
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY ' . $sortColumn . ' ' . $direction . ', i.id DESC',
            $params
        );
    }

    public function getByCompanyDateRange(
        int $companyId,
        string $fromDate,
        string $toDate,
        array $statuses = []
    ): array {
        $allowedStatuses = ['draft', 'sent', 'paid', 'overdue', 'cancelled'];
        $filteredStatuses = array_values(array_intersect($allowedStatuses, $statuses));
        if ($filteredStatuses === []) {
            $filteredStatuses = ['sent', 'paid', 'overdue'];
        }

        $params = [
            'company_id' => $companyId,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];

        $statusPlaceholders = [];
        foreach ($filteredStatuses as $index => $status) {
            $key = 'status_' . $index;
            $statusPlaceholders[] = ':' . $key;
            $params[$key] = $status;
        }

        $where = 'i.company_id = :company_id
                  AND i.invoice_date BETWEEN :from_date AND :to_date
                  AND i.status IN (' . implode(', ', $statusPlaceholders) . ')
                  AND COALESCE(i.document_type, \'invoice\') = \'invoice\'';

        return $this->db->fetchAll(
            'SELECT i.id,
                    i.invoice_number,
                    i.invoice_date,
                    i.due_date,
                    i.customer_name,
                    i.customer_phone,
                    i.total,
                    i.paid_amount,
                    i.status
             FROM invoices i
             WHERE ' . $where . '
             ORDER BY i.invoice_date ASC, i.id ASC',
            $params
        );
    }

    public function getStatsByCompany(int $companyId): array
    {
        $row = $this->db->fetchOne(
            'SELECT COALESCE(SUM(CASE WHEN status IN (:sent_status, :paid_status, :overdue_status) THEN total ELSE 0 END), 0) AS total_billed,
                    COALESCE(SUM(CASE
                        WHEN status IN (:sent_status, :paid_status, :overdue_status) THEN
                            CASE
                                WHEN status = :paid_status AND COALESCE(paid_amount, 0) <= 0 THEN total
                                ELSE COALESCE(paid_amount, 0)
                            END
                        ELSE 0
                    END), 0) AS total_paid,
                    COALESCE(SUM(CASE WHEN status IN (:sent_status, :overdue_status) THEN total - COALESCE(paid_amount, 0) ELSE 0 END), 0) AS total_pending,
                    COALESCE(SUM(CASE WHEN status = :overdue_status THEN total ELSE 0 END), 0) AS total_overdue
             FROM invoices
             WHERE company_id = :company_id
               AND COALESCE(document_type, \'invoice\') = \'invoice\'',
            [
                'company_id' => $companyId,
                'paid_status' => 'paid',
                'sent_status' => 'sent',
                'overdue_status' => 'overdue',
            ]
        );

        return [
            'total_billed' => (float) ($row['total_billed'] ?? 0),
            'total_paid' => (float) ($row['total_paid'] ?? 0),
            'total_pending' => (float) ($row['total_pending'] ?? 0),
            'total_overdue' => (float) ($row['total_overdue'] ?? 0),
        ];
    }

    public function searchClientsForAutocomplete(int $companyId, string $query, int $limit = 8, bool $onlyDebtors = false): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $normalizedPhone = $this->normalizePhone($query);
        $phoneExpr = $this->phoneSqlExpression('i.customer_phone');
        $concatExpr = $this->clientConcatExpression('i.customer_phone', 'i.customer_name');
        $concatExprAlt = $this->clientConcatExpression('i.customer_name', 'i.customer_phone');
        $where = ['i.company_id = :company_id'];
        $params = ['company_id' => $companyId];

        $conditions = [
            'i.customer_name LIKE :search',
            $concatExpr . ' LIKE :concat_search',
            $concatExprAlt . ' LIKE :concat_search',
        ];
        $params['search'] = '%' . $query . '%';
        $params['concat_search'] = '%' . strtolower($query) . '%';
        if ($normalizedPhone !== '') {
            $conditions[] = $phoneExpr . ' LIKE :phone_search';
            $params['phone_search'] = '%' . $normalizedPhone . '%';
        }
        $where[] = '(' . implode(' OR ', $conditions) . ')';

        if ($onlyDebtors) {
            $where[] = 'i.status IN (:sent_status, :paid_status, :overdue_status)';
            $where[] = 'i.total > COALESCE(i.paid_amount, 0)';
            $params['sent_status'] = 'sent';
            $params['paid_status'] = 'paid';
            $params['overdue_status'] = 'overdue';
        }

        $rows = $this->db->fetchAll(
            'SELECT i.id,
                    i.customer_name,
                    i.customer_phone,
                    i.total,
                    i.paid_amount,
                    i.status,
                    i.invoice_date
             FROM invoices i
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY i.invoice_date DESC, i.id DESC
             LIMIT ' . max(20, $limit * 12),
            $params
        );

        if (!$onlyDebtors && $this->clientsTableExists()) {
            $clientConditions = [
                'c.name LIKE :search',
                'c.phone LIKE :search',
                'LOWER(COALESCE(c.client_identity, \'\')) LIKE :concat_search',
                $this->clientConcatExpression('c.name', 'c.phone') . ' LIKE :concat_search',
            ];
            $clientParams = [
                'company_id' => $companyId,
                'search' => '%' . $query . '%',
                'concat_search' => '%' . strtolower($query) . '%',
            ];
            if ($normalizedPhone !== '') {
                $clientConditions[] = $this->phoneSqlExpression('c.phone') . ' LIKE :phone_search';
                $clientParams['phone_search'] = '%' . $normalizedPhone . '%';
            }

            $clientRows = $this->db->fetchAll(
                'SELECT c.name AS customer_name,
                        c.phone AS customer_phone,
                        0 AS total,
                        0 AS paid_amount,
                        \'client\' AS status,
                        c.created_at AS invoice_date
                 FROM clients c
                 WHERE c.company_id = :company_id
                   AND c.is_active = 1
                   AND (' . implode(' OR ', $clientConditions) . ')
                 ORDER BY c.updated_at DESC, c.id DESC
                 LIMIT ' . max(20, $limit * 6),
                $clientParams
            );
            if ($clientRows !== []) {
                $rows = array_merge($rows, $clientRows);
            }
        }

        return array_slice($this->aggregateClientRows($rows), 0, $limit);
    }

    public function findClientSummaryByPhone(int $companyId, string $phone): ?array
    {
        $matches = $this->searchClientsForAutocomplete($companyId, $phone, 1);
        return $matches[0] ?? null;
    }

    public function getClientLedger(int $companyId, string $clientName = '', string $clientPhone = '', array $filters = []): array
    {
        $params = ['company_id' => $companyId];
        $where = ['company_id = :company_id'];
        $identityClause = $this->buildClientIdentityWhere($clientName, $clientPhone, $params, '');
        if ($identityClause === null) {
            return [
                'client' => null,
                'rows' => [],
                'summary' => [
                    'invoice_count' => 0,
                    'total' => 0.0,
                    'paid' => 0.0,
                    'debt' => 0.0,
                    'debt_count' => 0,
                    'is_regular' => false,
                ],
            ];
        }
        $where[] = $identityClause;

        $includeProforma = (bool) ($filters['include_proforma'] ?? false);
        if (!$includeProforma) {
            $where[] = 'COALESCE(document_type, \'invoice\') = \'invoice\'';
        }

        $fromDate = $this->normalizeDate((string) ($filters['from_date'] ?? ''));
        if ($fromDate !== null) {
            $where[] = 'invoice_date >= :from_date';
            $params['from_date'] = $fromDate;
        }

        $toDate = $this->normalizeDate((string) ($filters['to_date'] ?? ''));
        if ($toDate !== null) {
            $where[] = 'invoice_date <= :to_date';
            $params['to_date'] = $toDate;
        }

        $statuses = $filters['statuses'] ?? [];
        if (is_array($statuses) && $statuses !== []) {
            $allowedStatuses = array_values(array_intersect(['draft', 'sent', 'paid', 'overdue', 'cancelled'], $statuses));
            if ($allowedStatuses !== []) {
                $placeholders = [];
                foreach ($allowedStatuses as $index => $status) {
                    $key = 'ledger_status_' . $index;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $status;
                }
                $where[] = 'status IN (' . implode(', ', $placeholders) . ')';
            }
        }

        $rows = $this->db->fetchAll(
            'SELECT id,
                    invoice_number,
                    invoice_date,
                    due_date,
                    customer_name,
                    customer_phone,
                    total,
                    paid_amount,
                    status,
                    notes
             FROM invoices
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY invoice_date DESC, id DESC',
            $params
        );

        $summary = [
            'invoice_count' => 0,
            'total' => 0.0,
            'paid' => 0.0,
            'debt' => 0.0,
            'debt_count' => 0,
            'is_regular' => false,
        ];
        $client = null;
        foreach ($rows as &$row) {
            $total = round((float) ($row['total'] ?? 0), 2);
            $paid = round((float) ($row['paid_amount'] ?? 0), 2);
            $debt = round(max($total - $paid, 0), 2);
            $row['debt_amount'] = $debt;

            $summary['invoice_count']++;
            $summary['total'] += $total;
            $summary['paid'] += $paid;
            $summary['debt'] += $debt;
            if ($debt > 0.009) {
                $summary['debt_count']++;
            }

            if ($client === null) {
                $client = [
                    'name' => (string) ($row['customer_name'] ?? ''),
                    'phone' => (string) ($row['customer_phone'] ?? ''),
                ];
            }
        }
        unset($row);

        $summary['total'] = round($summary['total'], 2);
        $summary['paid'] = round($summary['paid'], 2);
        $summary['debt'] = round($summary['debt'], 2);
        $summary['is_regular'] = $summary['invoice_count'] >= 2;

        return [
            'client' => $client,
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    public function listOutstandingInvoicesForClient(int $companyId, string $clientName = '', string $clientPhone = ''): array
    {
        $params = ['company_id' => $companyId];
        $identityClause = $this->buildClientIdentityWhere($clientName, $clientPhone, $params, 'i.');
        if ($identityClause === null) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT i.id,
                    i.invoice_number,
                    i.invoice_date,
                    i.due_date,
                    i.customer_name,
                    i.customer_phone,
                    i.total,
                    i.paid_amount,
                    i.status
             FROM invoices i
             WHERE i.company_id = :company_id
               AND COALESCE(i.document_type, \'invoice\') = \'invoice\'
               AND ' . $identityClause . '
               AND i.status IN (:sent_status, :paid_status, :overdue_status)
               AND NOT (i.status = :paid_status AND COALESCE(i.paid_amount, 0) <= 0.009)
               AND i.total > COALESCE(i.paid_amount, 0)
             ORDER BY i.invoice_date ASC, i.id ASC',
            array_merge($params, [
                'sent_status' => 'sent',
                'paid_status' => 'paid',
                'overdue_status' => 'overdue',
            ])
        );
    }

    public function findByIdForCompany(int $companyId, int $invoiceId): ?array
    {
        $driver = strtolower((string) \Config::DB_DRIVER);
        $creatorExpr = $driver === 'sqlite'
            ? "TRIM(COALESCE(u.first_name, '') || ' ' || COALESCE(u.last_name, ''))"
            : "TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')))";

        $invoice = $this->db->fetchOne(
	            'SELECT i.id,
	                    i.document_type,
	                    i.invoice_number,
	                    i.invoice_date,
	                    i.due_date,
	                    i.customer_name,
	                    i.customer_phone,
	                    i.customer_tax_id,
	                    i.customer_address,
	                    i.customer_description,
	                    i.subtotal,
	                    i.tax_rate,
	                    i.tax_amount,
	                    i.labor_amount,
	                    i.labor_tax_rate,
                    i.labor_tax_amount,
                    i.total,
                    i.paid_amount,
                    i.status,
                    i.invoice_type,
                    i.issuer_company_name,
                    i.issuer_logo_url,
                    i.issuer_brand_color,
                    i.fiscal_period_id,
                    i.downloaded_at,
                    i.notes,
                    i.created_at,
                    i.created_by,
                    COALESCE(NULLIF(' . $creatorExpr . ', \'\'), \'Utilisateur\') AS created_by_name
             FROM invoices i
             LEFT JOIN users u ON u.id = i.created_by
             WHERE i.company_id = :company_id
               AND i.id = :id
             LIMIT 1',
            [
                'company_id' => $companyId,
                'id' => $invoiceId,
            ]
        );

        if ($invoice === null) {
            return null;
        }

	        $invoice['items'] = $this->db->fetchAll(
	            'SELECT id, line_kind, product_id, description, quantity, unit_code, factor_to_base, quantity_base, stock_movement_id, cogs_amount, margin_amount, unit_price, tax_rate, subtotal, tax_amount, total
	             FROM invoice_items
	             WHERE invoice_id = :invoice_id
	             ORDER BY id ASC',
	            ['invoice_id' => $invoiceId]
	        );

        return $invoice;
    }

    public function updateDraftFromPayload(int $companyId, int $invoiceId, int $userId, array $payload): bool
    {
        $existing = $this->findByIdForCompany($companyId, $invoiceId);
        if ($existing === null) {
            throw new \InvalidArgumentException('Facture introuvable.');
        }

        $existingStatus = (string) ($existing['status'] ?? '');
        if ($existingStatus !== 'draft') {
            throw new \InvalidArgumentException('Seuls les brouillons peuvent etre modifies.');
        }

	        $invoiceDate = $this->normalizeDate((string) ($payload['issue_date'] ?? ''));
	        $dueDate = $this->normalizeDate((string) ($payload['due_date'] ?? ''));
	        [$customerName, $customerPhone, $customerTaxId, $customerAddress, $clientType] = $this->resolveClientFromPayload($payload);
	        $customerDescription = trim((string) ($payload['client_description'] ?? ''));
	        if ($clientType === 'anonymous') {
	            $customerDescription = '';
	        }
	        $requestedStatus = $this->normalizeStatus((string) ($payload['status'] ?? 'draft'));
	        $status = $requestedStatus;
	        $invoiceType = $this->normalizeInvoiceType((string) ($payload['invoice_type'] ?? ($existing['invoice_type'] ?? 'product')));
	        $documentType = $this->normalizeDocumentType((string) ($payload['document_type'] ?? ($existing['document_type'] ?? 'invoice')));

        if (!in_array($requestedStatus, ['draft', 'sent', 'paid'], true)) {
            $requestedStatus = 'draft';
        }
        $status = $requestedStatus === 'paid' ? 'sent' : $requestedStatus;

        $invoiceNumber = trim((string) ($existing['invoice_number'] ?? ''));
        if ($invoiceNumber === '') {
            $invoiceNumber = $this->generateNextNumber($companyId, $documentType);
        }

        if ($invoiceNumber === '' || $invoiceDate === null || $dueDate === null || $customerName === '') {
            throw new \InvalidArgumentException('Les informations principales de la facture sont invalides.');
        }

        if (strtotime($dueDate) < strtotime($invoiceDate)) {
            throw new \InvalidArgumentException('La date echeance doit etre superieure ou egale a la date emission.');
        }

        $parsed = $this->parseInvoiceItemsFromPayload($companyId, $payload, $invoiceType);
        $items = $parsed['items'];
        $labor = $this->parseLaborFromPayload($payload);
        $laborAmount = (float) $labor['amount'];
        $laborTaxRate = (float) $labor['tax_rate'];
        $laborTaxAmount = (float) $labor['tax_amount'];

        $subtotal = (float) $parsed['subtotal'] + $laborAmount;
        $taxAmount = (float) $parsed['tax_amount'] + $laborTaxAmount;
        $this->assertStockAvailabilityForItems($companyId, $items, $invoiceType);

        $discountType = trim((string) ($payload['discount_type'] ?? 'percent'));
        $discountRaw = round((float) ($payload['discount_value'] ?? 0), 2);
        $gross = $subtotal + $taxAmount;
        $discount = $discountType === 'percent' ? $gross * ($discountRaw / 100) : $discountRaw;
        $discount = max(0, min($discount, $gross));
        $total = round($gross - $discount, 2);
        $deposit = max(0, round((float) ($payload['deposit_value'] ?? 0), 2));
        $paidAmount = min($deposit, $total);
        if ($documentType === 'proforma') {
            $paidAmount = 0.0;
            $deposit = 0.0;
            $status = 'draft';
        } elseif ($clientType === 'anonymous') {
            $paidAmount = $total;
            $deposit = $total;
            $status = 'paid';
        } elseif ($requestedStatus === 'paid') {
            $paidAmount = $total;
            $deposit = $total;
            $status = 'paid';
        } elseif ($status !== 'draft') {
            $status = $paidAmount >= ($total - 0.009) ? 'paid' : 'sent';
        }
        $remaining = max($total - $paidAmount, 0);
        if ($documentType !== 'proforma' && ($clientType === 'anonymous' || $status === 'paid' || $remaining <= 0.009)) {
            $dueDate = $invoiceDate;
        }
        $notes = $this->buildNotes($payload, $discountType, $discountRaw, $deposit, $remaining);

        $duplicate = $this->db->fetchOne(
            'SELECT id FROM invoices WHERE company_id = :company_id AND invoice_number = :invoice_number AND id <> :id LIMIT 1',
            [
                'company_id' => $companyId,
                'invoice_number' => $invoiceNumber,
                'id' => $invoiceId,
            ]
        );
        if ($duplicate !== null) {
            throw new \InvalidArgumentException('Ce numero facture existe deja.');
        }

        $averageTaxRate = $subtotal > 0 ? round(($taxAmount / $subtotal) * 100, 2) : 0;
        $fiscalPeriodId = (new FiscalPeriod($this->db))->resolvePeriodIdForDate($companyId, (string) $invoiceDate);
        $issuer = $this->getIssuerSnapshot($companyId);

        $this->db->beginTransaction();
	        try {
	            $this->db->execute(
	                'UPDATE invoices
	                 SET invoice_number = :invoice_number,
	                    document_type = :document_type,
	                     invoice_date = :invoice_date,
	                     due_date = :due_date,
	                     customer_name = :customer_name,
	                     customer_phone = :customer_phone,
	                     customer_tax_id = :customer_tax_id,
	                     customer_address = :customer_address,
	                     customer_description = :customer_description,
	                     subtotal = :subtotal,
	                     tax_rate = :tax_rate,
	                     tax_amount = :tax_amount,
	                     labor_amount = :labor_amount,
	                     labor_tax_rate = :labor_tax_rate,
                     labor_tax_amount = :labor_tax_amount,
                    total = :total,
                    paid_amount = :paid_amount,
                     status = :status,
                    invoice_type = :invoice_type,
                    issuer_company_name = :issuer_company_name,
                    issuer_logo_url = :issuer_logo_url,
                    issuer_brand_color = :issuer_brand_color,
                    fiscal_period_id = :fiscal_period_id,
                    notes = :notes
                 WHERE id = :id
                   AND company_id = :company_id',
	                [
	                    'invoice_number' => $invoiceNumber,
	                    'document_type' => $documentType,
	                    'invoice_date' => $invoiceDate,
	                    'due_date' => $dueDate,
	                    'customer_name' => $customerName,
	                    'customer_phone' => $customerPhone !== '' ? $customerPhone : null,
	                    'customer_tax_id' => $customerTaxId !== '' ? $customerTaxId : null,
	                    'customer_address' => $customerAddress !== '' ? $customerAddress : null,
	                    'customer_description' => $customerDescription !== '' ? $customerDescription : null,
	                    'subtotal' => round($subtotal, 2),
	                    'tax_rate' => $averageTaxRate,
	                    'tax_amount' => round($taxAmount, 2),
	                    'labor_amount' => round($laborAmount, 2),
                    'labor_tax_rate' => round($laborTaxRate, 2),
                    'labor_tax_amount' => round($laborTaxAmount, 2),
                    'total' => $total,
                    'paid_amount' => $paidAmount,
                    'status' => $status,
                    'invoice_type' => $invoiceType,
                    'issuer_company_name' => $issuer['company_name'],
                    'issuer_logo_url' => $issuer['logo_url'],
                    'issuer_brand_color' => $issuer['brand_color'],
                    'fiscal_period_id' => $fiscalPeriodId,
                    'notes' => $notes,
                    'id' => $invoiceId,
                    'company_id' => $companyId,
                ]
	            );

	            if (
	                $this->normalizeDocumentType((string) ($existing['document_type'] ?? 'invoice')) === 'invoice'
	                && $this->normalizeInvoiceType((string) ($existing['invoice_type'] ?? 'product')) === 'product'
	                && $this->hasStockReservation($existing)
	            ) {
	                $this->releaseStockForItems($companyId, (string) ($existing['invoice_number'] ?? ''), $existing['items'] ?? [], $userId);
	            }

            $this->db->execute('DELETE FROM invoice_items WHERE invoice_id = :invoice_id', ['invoice_id' => $invoiceId]);

	            if ($documentType === 'invoice' && $invoiceType === 'product' && $status !== 'draft') {
	                $this->reserveStockForItems($companyId, $invoiceNumber, $items, $userId);
	            }

            foreach ($items as $item) {
                $this->db->execute(
	                    'INSERT INTO invoice_items (invoice_id, line_kind, product_id, description, quantity, unit_code, factor_to_base, quantity_base, stock_movement_id, cogs_amount, margin_amount, unit_price, tax_rate, subtotal, tax_amount, total)
	                     VALUES (:invoice_id, :line_kind, :product_id, :description, :quantity, :unit_code, :factor_to_base, :quantity_base, :stock_movement_id, :cogs_amount, :margin_amount, :unit_price, :tax_rate, :subtotal, :tax_amount, :total)',
	                    [
	                        'invoice_id' => $invoiceId,
	                        'line_kind' => (string) ($item['line_kind'] ?? 'standard'),
	                        'product_id' => $item['product_id'],
	                        'description' => $item['description'],
	                        'quantity' => $item['quantity'],
	                        'unit_code' => $item['unit_code'],
                        'factor_to_base' => $item['factor_to_base'],
                        'quantity_base' => $item['quantity_base'],
                        'stock_movement_id' => $item['stock_movement_id'] ?? null,
                        'cogs_amount' => round((float) ($item['cogs_amount'] ?? 0), 2),
                        'margin_amount' => round((float) ($item['margin_amount'] ?? 0), 2),
                        'unit_price' => $item['unit_price'],
                        'tax_rate' => $item['tax_rate'],
                        'subtotal' => $item['subtotal'],
                        'tax_amount' => $item['tax_amount'],
                        'total' => $item['total'],
                    ]
                );
            }
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }

        $updated = $this->findByIdForCompany($companyId, $invoiceId);
        $this->appendInvoiceHistory($companyId, $invoiceId, 'updated', $existing, $updated, [
            'editable_status' => $existingStatus,
        ]);

        return true;
    }

    public function markSent(int $companyId, int $invoiceId, int $userId): bool
    {
        $invoice = $this->findByIdForCompany($companyId, $invoiceId);
        if ($invoice === null || (string) ($invoice['status'] ?? '') !== 'draft') {
            return false;
        }

        if ($this->normalizeDocumentType((string) ($invoice['document_type'] ?? 'invoice')) !== 'invoice') {
            return false;
        }

        $paidAmount = round((float) ($invoice['paid_amount'] ?? 0), 2);
        $total = round((float) ($invoice['total'] ?? 0), 2);
        $newStatus = $paidAmount >= ($total - 0.009) ? 'paid' : 'sent';

        $this->db->beginTransaction();
        try {
            $items = is_array($invoice['items'] ?? null) ? $invoice['items'] : [];
            if (
                $this->normalizeInvoiceType((string) ($invoice['invoice_type'] ?? 'product')) === 'product'
                && !$this->hasStockReservation($invoice)
            ) {
                $invoiceNumber = (string) ($invoice['invoice_number'] ?? '');
                $this->reserveStockForItems($companyId, $invoiceNumber, $items, $userId);
                foreach ($items as $item) {
                    $itemId = (int) ($item['id'] ?? 0);
                    if ($itemId <= 0) {
                        continue;
                    }
                    $this->db->execute(
                        'UPDATE invoice_items
                         SET stock_movement_id = :stock_movement_id,
                             cogs_amount = :cogs_amount,
                             margin_amount = :margin_amount
                         WHERE id = :id AND invoice_id = :invoice_id',
                        [
                            'stock_movement_id' => (int) ($item['stock_movement_id'] ?? 0) ?: null,
                            'cogs_amount' => round((float) ($item['cogs_amount'] ?? 0), 2),
                            'margin_amount' => round((float) ($item['margin_amount'] ?? 0), 2),
                            'id' => $itemId,
                            'invoice_id' => $invoiceId,
                        ]
                    );
                }
            }

            $this->db->execute(
                'UPDATE invoices
                 SET status = :status,
                     paid_date = CASE WHEN :status_paid = :status THEN CURRENT_DATE ELSE paid_date END
                 WHERE id = :id
                   AND company_id = :company_id',
                [
                    'status' => $newStatus,
                    'status_paid' => 'paid',
                    'id' => $invoiceId,
                    'company_id' => $companyId,
                ]
            );
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }

        $updated = $this->findByIdForCompany($companyId, $invoiceId);
        $this->appendInvoiceHistory($companyId, $invoiceId, 'sent', $invoice, $updated);

        return true;
    }

    public function convertProformaToPaidInvoice(int $companyId, int $invoiceId, int $userId): bool
    {
        $invoice = $this->findByIdForCompany($companyId, $invoiceId);
        if ($invoice === null) {
            return false;
        }

        if ((string) ($invoice['status'] ?? '') !== 'draft') {
            return false;
        }

        if ($this->normalizeDocumentType((string) ($invoice['document_type'] ?? 'invoice')) !== 'proforma') {
            return false;
        }

        $invoiceDate = $this->normalizeDate((string) ($invoice['invoice_date'] ?? '')) ?? date('Y-m-d');
        $newInvoiceNumber = $this->generateNextNumber($companyId, 'invoice');
        if ($newInvoiceNumber === '') {
            throw new \RuntimeException('Numero facture indisponible.');
        }
        $duplicate = $this->db->fetchOne(
            'SELECT id
             FROM invoices
             WHERE company_id = :company_id
               AND invoice_number = :invoice_number
               AND id <> :id
             LIMIT 1',
            [
                'company_id' => $companyId,
                'invoice_number' => $newInvoiceNumber,
                'id' => $invoiceId,
            ]
        );
        if ($duplicate !== null) {
            throw new \InvalidArgumentException('Ce numero facture existe deja.');
        }

        $items = is_array($invoice['items'] ?? null) ? $invoice['items'] : [];
        $invoiceType = $this->normalizeInvoiceType((string) ($invoice['invoice_type'] ?? 'product'));
        $total = round((float) ($invoice['total'] ?? 0), 2);
        if ($total <= 0) {
            throw new \InvalidArgumentException('Facture invalide (total <= 0).');
        }

        $this->assertStockAvailabilityForItems($companyId, $items, $invoiceType);

        $fiscalPeriodId = (new FiscalPeriod($this->db))->resolvePeriodIdForDate($companyId, $invoiceDate);

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                'UPDATE invoices
                 SET document_type = :document_type,
                     invoice_number = :invoice_number,
                     status = :status,
                     paid_amount = :paid_amount,
                     paid_date = :paid_date,
                     due_date = :due_date,
                     fiscal_period_id = :fiscal_period_id
                 WHERE id = :id
                   AND company_id = :company_id',
                [
                    'document_type' => 'invoice',
                    'invoice_number' => $newInvoiceNumber,
                    'status' => 'paid',
                    'paid_amount' => $total,
                    'paid_date' => $invoiceDate,
                    'due_date' => $invoiceDate,
                    'fiscal_period_id' => $fiscalPeriodId,
                    'id' => $invoiceId,
                    'company_id' => $companyId,
                ]
            );

            if ($invoiceType === 'product' && !$this->hasStockReservation($invoice)) {
                $this->reserveStockForItems($companyId, $newInvoiceNumber, $items, $userId);
                foreach ($items as $item) {
                    $itemId = (int) ($item['id'] ?? 0);
                    if ($itemId <= 0) {
                        continue;
                    }
                    $this->db->execute(
                        'UPDATE invoice_items
                         SET stock_movement_id = :stock_movement_id,
                             cogs_amount = :cogs_amount,
                             margin_amount = :margin_amount
                         WHERE id = :id AND invoice_id = :invoice_id',
                        [
                            'stock_movement_id' => (int) ($item['stock_movement_id'] ?? 0) ?: null,
                            'cogs_amount' => round((float) ($item['cogs_amount'] ?? 0), 2),
                            'margin_amount' => round((float) ($item['margin_amount'] ?? 0), 2),
                            'id' => $itemId,
                            'invoice_id' => $invoiceId,
                        ]
                    );
                }
            }

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }

        $converted = $this->findByIdForCompany($companyId, $invoiceId);
        $this->appendInvoiceHistory($companyId, $invoiceId, 'proforma_converted', $invoice, $converted, [
            'new_invoice_number' => $newInvoiceNumber,
        ]);

        return true;
    }

    public function cancelDraft(int $companyId, int $invoiceId, int $userId): bool
    {
        $invoice = $this->findByIdForCompany($companyId, $invoiceId);
        if ($invoice === null) {
            return false;
        }

        $status = (string) ($invoice['status'] ?? '');
        if (!in_array($status, ['draft', 'sent', 'overdue', 'paid'], true)) {
            return false;
        }

        $previousPaidAmount = round((float) ($invoice['paid_amount'] ?? 0), 2);
        $cancellationDate = date('Y-m-d');
        $invoiceNumber = (string) ($invoice['invoice_number'] ?? '');
        $allocations = $this->db->fetchAll(
            'SELECT transaction_id, COALESCE(SUM(amount), 0) AS amount
             FROM invoice_payment_allocations
             WHERE company_id = :company_id
               AND invoice_id = :invoice_id
             GROUP BY transaction_id',
            [
                'company_id' => $companyId,
                'invoice_id' => $invoiceId,
            ]
        );

        $this->db->beginTransaction();
        try {
            foreach ($allocations as $allocation) {
                $transactionId = (int) ($allocation['transaction_id'] ?? 0);
                $refundAmount = round((float) ($allocation['amount'] ?? 0), 2);
                if ($transactionId <= 0 || $refundAmount <= 0) {
                    continue;
                }

                $sharedRow = $this->db->fetchOne(
                    'SELECT COUNT(DISTINCT invoice_id) AS invoice_count
                     FROM invoice_payment_allocations
                     WHERE company_id = :company_id
                       AND transaction_id = :transaction_id',
                    [
                        'company_id' => $companyId,
                        'transaction_id' => $transactionId,
                    ]
                );
                $invoiceCount = (int) ($sharedRow['invoice_count'] ?? 0);
                if ($invoiceCount <= 1) {
                    $this->db->execute(
                        'UPDATE transactions
                         SET status = :status
                         WHERE id = :id
                           AND company_id = :company_id',
                        [
                            'status' => 'void',
                            'id' => $transactionId,
                            'company_id' => $companyId,
                        ]
                    );
                    continue;
                }

                (new Transaction($this->db))->createManual($companyId, $userId, [
                    'transaction_date' => $cancellationDate,
                    'description' => 'Remboursement annulation facture ' . $invoiceNumber,
                    'type' => 'expense',
                    'status' => 'posted',
                    'amount' => $refundAmount,
                    'expense_subcategory' => 'other',
                    'expense_subcategory_other' => 'Annulation facture ' . $invoiceNumber,
                    'account_id' => 0,
                ]);
            }

		            if (
		                $this->normalizeDocumentType((string) ($invoice['document_type'] ?? 'invoice')) === 'invoice'
		                && $this->normalizeInvoiceType((string) ($invoice['invoice_type'] ?? 'product')) === 'product'
		                && $this->hasStockReservation($invoice)
		            ) {
		                $this->releaseStockForItems($companyId, (string) ($invoice['invoice_number'] ?? ''), $invoice['items'] ?? [], $userId);
		            }

            $this->db->execute(
                'UPDATE invoices
                 SET status = :status,
                     paid_amount = :paid_amount,
                     paid_date = NULL
                 WHERE id = :id
                   AND company_id = :company_id',
                [
                    'status' => 'cancelled',
                    'paid_amount' => 0,
                    'id' => $invoiceId,
                    'company_id' => $companyId,
                ]
            );
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }

        $updated = $this->findByIdForCompany($companyId, $invoiceId);
        $this->appendInvoiceHistory($companyId, $invoiceId, 'cancelled', $invoice, $updated, [
            'cancelled_from_status' => $status,
            'previous_paid_amount' => $previousPaidAmount,
        ]);

        return true;
    }

    public function deleteForCompany(int $companyId, int $invoiceId, int $userId): bool
    {
        $invoice = $this->findByIdForCompany($companyId, $invoiceId);
        if ($invoice === null) {
            return false;
        }

        $status = (string) ($invoice['status'] ?? '');
        $paidAmount = round((float) ($invoice['paid_amount'] ?? 0), 2);
        if ($status !== 'draft' || $paidAmount > 0) {
            return false;
        }

	        $this->db->beginTransaction();
	        try {
	            if (
	                $status === 'draft'
	                && $this->normalizeDocumentType((string) ($invoice['document_type'] ?? 'invoice')) === 'invoice'
	                && $this->normalizeInvoiceType((string) ($invoice['invoice_type'] ?? 'product')) === 'product'
	                && $this->hasStockReservation($invoice)
	            ) {
	                $this->releaseStockForItems($companyId, (string) ($invoice['invoice_number'] ?? ''), $invoice['items'] ?? [], $userId);
	            }

            $this->db->execute(
                'DELETE FROM invoices
                 WHERE id = :id
                   AND company_id = :company_id',
                [
                    'id' => $invoiceId,
                    'company_id' => $companyId,
                ]
            );
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }

        return true;
    }

    public function registerPayment(int $companyId, int $invoiceId, float $amount, ?string $paymentDate = null): bool
    {
        $invoice = $this->findByIdForCompany($companyId, $invoiceId);
        if ($invoice === null) {
            return false;
        }

        if ($this->normalizeDocumentType((string) ($invoice['document_type'] ?? 'invoice')) !== 'invoice') {
            return false;
        }

        $status = (string) ($invoice['status'] ?? '');
        if (!in_array($status, ['sent', 'overdue', 'paid'], true)) {
            return false;
        }

        $amount = round($amount, 2);
        if ($amount <= 0) {
            return false;
        }

        $currentPaid = round((float) ($invoice['paid_amount'] ?? 0), 2);
        $total = round((float) ($invoice['total'] ?? 0), 2);
        $newPaid = round(min($total, $currentPaid + $amount), 2);
        $isFullyPaid = ($newPaid + 0.0001) >= $total;
        if ($isFullyPaid) {
            $newStatus = 'paid';
        } else {
            $newStatus = $status === 'overdue' ? 'overdue' : 'sent';
        }

        $normalizedPaymentDate = $this->normalizeDate((string) ($paymentDate ?? '')) ?? date('Y-m-d');

        $this->db->execute(
            'UPDATE invoices
             SET paid_amount = :paid_amount,
                 status = :status,
                 paid_date = CASE WHEN :status_paid = :status THEN :payment_date ELSE paid_date END
             WHERE id = :id
               AND company_id = :company_id',
            [
                'paid_amount' => $newPaid,
                'status' => $newStatus,
                'status_paid' => 'paid',
                'payment_date' => $normalizedPaymentDate,
                'id' => $invoiceId,
                'company_id' => $companyId,
            ]
        );

        $updated = $this->findByIdForCompany($companyId, $invoiceId);
        $this->appendInvoiceHistory($companyId, $invoiceId, 'payment_recorded', $invoice, $updated, [
            'payment_amount' => $amount,
        ]);

        return true;
    }

    public function markPdfDownloaded(int $companyId, int $invoiceId): bool
    {
        $this->db->execute(
            'UPDATE invoices
             SET downloaded_at = COALESCE(downloaded_at, CURRENT_TIMESTAMP)
             WHERE id = :id
               AND company_id = :company_id',
            [
                'id' => $invoiceId,
                'company_id' => $companyId,
            ]
        );

        return true;
    }

    public function generateNextNumber(int $companyId, string $documentType = 'invoice'): string
    {
        $documentType = $this->normalizeDocumentType($documentType);
        $prefix = $documentType === 'proforma' ? 'PF' : 'INV';
        $year = date('Y');
        $numberPrefix = sprintf('%s-%s-', $prefix, $year);

        $row = $this->db->fetchOne(
            'SELECT invoice_number
             FROM invoices
             WHERE company_id = :company_id
               AND invoice_number LIKE :invoice_like
             ORDER BY invoice_number DESC
             LIMIT 1',
            [
                'company_id' => $companyId,
                'invoice_like' => $numberPrefix . '%',
            ]
        );

        $lastNumber = trim((string) ($row['invoice_number'] ?? ''));
        $lastSeq = 0;
        if ($lastNumber !== '' && preg_match('/^' . preg_quote($numberPrefix, '/') . '(\\d+)$/', $lastNumber, $matches) === 1) {
            $lastSeq = (int) $matches[1];
        }

        $nextSeq = $lastSeq + 1;
        $width = max(4, strlen((string) $nextSeq));
        return $numberPrefix . str_pad((string) $nextSeq, $width, '0', STR_PAD_LEFT);
    }

    public function createFromPayload(int $companyId, int $userId, array $payload): int
    {
        $invoiceDate = $this->normalizeDate((string) ($payload['issue_date'] ?? ''));
        $dueDate = $this->normalizeDate((string) ($payload['due_date'] ?? ''));
        [$customerName, $customerPhone, $customerTaxId, $customerAddress, $clientType] = $this->resolveClientFromPayload($payload);
        $customerDescription = trim((string) ($payload['client_description'] ?? ''));
        if ($clientType === 'anonymous') {
            $customerDescription = '';
        }
        $requestedStatus = $this->normalizeStatus((string) ($payload['status'] ?? 'sent'));
        $status = $requestedStatus;
        $invoiceType = $this->normalizeInvoiceType((string) ($payload['invoice_type'] ?? 'product'));
        $documentType = $this->normalizeDocumentType((string) ($payload['document_type'] ?? 'invoice'));
        $invoiceNumber = $this->generateNextNumber($companyId, $documentType);


        if ($invoiceNumber === '' || $invoiceDate === null || $dueDate === null || $customerName === '') {
            throw new \InvalidArgumentException('Les informations principales de la facture sont invalides.');
        }

        if (strtotime($dueDate) < strtotime($invoiceDate)) {
            throw new \InvalidArgumentException('La date echeance doit etre superieure ou egale a la date emission.');
        }

        $parsed = $this->parseInvoiceItemsFromPayload($companyId, $payload, $invoiceType);
        $items = $parsed['items'];
        $labor = $this->parseLaborFromPayload($payload);
        $laborAmount = (float) $labor['amount'];
        $laborTaxRate = (float) $labor['tax_rate'];
        $laborTaxAmount = (float) $labor['tax_amount'];

        $subtotal = (float) $parsed['subtotal'] + $laborAmount;
        $taxAmount = (float) $parsed['tax_amount'] + $laborTaxAmount;
        $this->assertStockAvailabilityForItems($companyId, $items, $invoiceType);

        $discountType = trim((string) ($payload['discount_type'] ?? 'percent'));
        $discountRaw = round((float) ($payload['discount_value'] ?? 0), 2);
        $gross = $subtotal + $taxAmount;

        $discount = $discountRaw;
        if ($discountType === 'percent') {
            $discount = $gross * ($discountRaw / 100);
        }
        $discount = max(0, min($discount, $gross));

        $total = round($gross - $discount, 2);
        $deposit = max(0, round((float) ($payload['deposit_value'] ?? 0), 2));
        $paidAmount = min($deposit, $total);
        if ($documentType === 'proforma') {
            $paidAmount = 0.0;
            $deposit = 0.0;
            $status = 'draft';
        } elseif ($clientType === 'anonymous') {
            $paidAmount = $total;
            $deposit = $total;
            $status = 'paid';
        } elseif ($requestedStatus === 'paid') {
            $paidAmount = $total;
            $deposit = $total;
            $status = 'paid';
        } elseif ($status !== 'draft') {
            $status = $paidAmount >= ($total - 0.009) ? 'paid' : 'sent';
        }
        $remaining = max($total - $paidAmount, 0);
        if ($documentType !== 'proforma' && ($clientType === 'anonymous' || $status === 'paid' || $remaining <= 0.009)) {
            $dueDate = $invoiceDate;
        }

        $notes = $this->buildNotes($payload, $discountType, $discountRaw, $deposit, $remaining);

        $existing = $this->db->fetchOne(
            'SELECT id
             FROM invoices
             WHERE company_id = :company_id
               AND invoice_number = :invoice_number
             LIMIT 1',
            [
                'company_id' => $companyId,
                'invoice_number' => $invoiceNumber,
            ]
        );

        if ($existing !== null) {
            throw new \InvalidArgumentException('Ce numero facture existe deja.');
        }

        $averageTaxRate = $subtotal > 0 ? round(($taxAmount / $subtotal) * 100, 2) : 0;
        $fiscalPeriodId = (new FiscalPeriod($this->db))->resolvePeriodIdForDate($companyId, (string) $invoiceDate);
        $issuer = $this->getIssuerSnapshot($companyId);

        $this->db->beginTransaction();

        try {
            $this->db->execute(
                'INSERT INTO invoices (company_id, document_type, invoice_number, invoice_date, due_date, customer_name, customer_phone, customer_tax_id, customer_address, customer_description, subtotal, tax_rate, tax_amount, labor_amount, labor_tax_rate, labor_tax_amount, total, paid_amount, status, invoice_type, issuer_company_name, issuer_logo_url, issuer_brand_color, fiscal_period_id, notes, created_by)
                 VALUES (:company_id, :document_type, :invoice_number, :invoice_date, :due_date, :customer_name, :customer_phone, :customer_tax_id, :customer_address, :customer_description, :subtotal, :tax_rate, :tax_amount, :labor_amount, :labor_tax_rate, :labor_tax_amount, :total, :paid_amount, :status, :invoice_type, :issuer_company_name, :issuer_logo_url, :issuer_brand_color, :fiscal_period_id, :notes, :created_by)',
                [
                    'company_id' => $companyId,
                    'document_type' => $documentType,
                    'invoice_number' => $invoiceNumber,
                    'invoice_date' => $invoiceDate,
                    'due_date' => $dueDate,
                    'customer_name' => $customerName,
                    'customer_phone' => $customerPhone !== '' ? $customerPhone : null,
                    'customer_tax_id' => $customerTaxId !== '' ? $customerTaxId : null,
                    'customer_address' => $customerAddress !== '' ? $customerAddress : null,
                    'customer_description' => $customerDescription !== '' ? $customerDescription : null,
                    'subtotal' => round($subtotal, 2),
                    'tax_rate' => $averageTaxRate,
                    'tax_amount' => round($taxAmount, 2),
                    'labor_amount' => round($laborAmount, 2),
                    'labor_tax_rate' => round($laborTaxRate, 2),
                    'labor_tax_amount' => round($laborTaxAmount, 2),
                    'total' => $total,
                    'paid_amount' => $paidAmount,
                    'status' => $status,
                    'invoice_type' => $invoiceType,
                    'issuer_company_name' => $issuer['company_name'],
                    'issuer_logo_url' => $issuer['logo_url'],
                    'issuer_brand_color' => $issuer['brand_color'],
                    'fiscal_period_id' => $fiscalPeriodId,
                    'notes' => $notes,
                    'created_by' => $userId,
                ]
            );

            $invoiceId = $this->db->lastInsertId();

	            if ($documentType === 'invoice' && $invoiceType === 'product' && $status !== 'draft') {
	                $this->reserveStockForItems($companyId, $invoiceNumber, $items, $userId);
	            }

            foreach ($items as $item) {
                $this->db->execute(
	                    'INSERT INTO invoice_items (invoice_id, line_kind, product_id, description, quantity, unit_code, factor_to_base, quantity_base, stock_movement_id, cogs_amount, margin_amount, unit_price, tax_rate, subtotal, tax_amount, total)
	                     VALUES (:invoice_id, :line_kind, :product_id, :description, :quantity, :unit_code, :factor_to_base, :quantity_base, :stock_movement_id, :cogs_amount, :margin_amount, :unit_price, :tax_rate, :subtotal, :tax_amount, :total)',
	                    [
	                        'invoice_id' => $invoiceId,
	                        'line_kind' => (string) ($item['line_kind'] ?? 'standard'),
	                        'product_id' => $item['product_id'],
	                        'description' => $item['description'],
	                        'quantity' => $item['quantity'],
	                        'unit_code' => $item['unit_code'],
                        'factor_to_base' => $item['factor_to_base'],
                        'quantity_base' => $item['quantity_base'],
                        'stock_movement_id' => $item['stock_movement_id'] ?? null,
                        'cogs_amount' => round((float) ($item['cogs_amount'] ?? 0), 2),
                        'margin_amount' => round((float) ($item['margin_amount'] ?? 0), 2),
                        'unit_price' => $item['unit_price'],
                        'tax_rate' => $item['tax_rate'],
                        'subtotal' => $item['subtotal'],
                        'tax_amount' => $item['tax_amount'],
                        'total' => $item['total'],
                    ]
                );
            }

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }

        $created = $this->findByIdForCompany($companyId, $invoiceId);
        $this->appendInvoiceHistory($companyId, $invoiceId, 'created', null, $created);

        return $invoiceId;
    }

    public function mergeDraftInvoices(int $companyId, int $userId, array $invoiceIds): int
    {
        $ids = array_values(array_unique(array_map('intval', $invoiceIds)));
        $ids = array_values(array_filter($ids, static fn($id) => $id > 0));

        if (count($ids) < 2) {
            throw new \InvalidArgumentException('Selection insuffisante.');
        }

        $placeholders = [];
        $params = ['company_id' => $companyId];
        foreach ($ids as $index => $id) {
            $key = 'iid_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

	        $rows = $this->db->fetchAll(
	            'SELECT id, document_type, invoice_number, invoice_date, due_date, customer_name, customer_phone, customer_tax_id, customer_address, customer_description, paid_amount, status, invoice_type, labor_amount, labor_tax_rate, labor_tax_amount, notes
	             FROM invoices
	             WHERE company_id = :company_id
	               AND id IN (' . implode(', ', $placeholders) . ')',
	            $params
	        );

        if (count($rows) !== count($ids)) {
            throw new \InvalidArgumentException('Factures introuvables.');
        }

        $invoicesById = [];
        foreach ($rows as $row) {
            $invoiceId = (int) ($row['id'] ?? 0);
            if ($invoiceId <= 0) {
                continue;
            }
            $rowStatus = (string) ($row['status'] ?? '');
            $rowPaid = (float) ($row['paid_amount'] ?? 0);
            if (!in_array($rowStatus, ['draft', 'sent', 'overdue', 'paid'], true)) {
                throw new \InvalidArgumentException('Factures non eligibles a la fusion.');
            }
	            $row['items'] = $this->db->fetchAll(
	                'SELECT line_kind, product_id, description, quantity, unit_code, factor_to_base, quantity_base, stock_movement_id, unit_price, tax_rate, subtotal, tax_amount, total
	                 FROM invoice_items
	                 WHERE invoice_id = :invoice_id
	                 ORDER BY id ASC',
	                ['invoice_id' => $invoiceId]
	            );
            $invoicesById[$invoiceId] = $row;
        }

        $sorted = [];
        foreach ($ids as $id) {
            if (isset($invoicesById[$id])) {
                $sorted[] = $invoicesById[$id];
            }
        }

        if (count($sorted) < 2) {
            throw new \InvalidArgumentException('Selection invalide.');
        }

        $invoiceType = 'service';
        $documentType = 'proforma';
	        $customerName = trim((string) ($sorted[0]['customer_name'] ?? ''));
	        $customerPhone = trim((string) ($sorted[0]['customer_phone'] ?? ''));
	        $customerTaxId = trim((string) ($sorted[0]['customer_tax_id'] ?? ''));
	        $customerAddress = trim((string) ($sorted[0]['customer_address'] ?? ''));
	        $customerDescription = trim((string) ($sorted[0]['customer_description'] ?? ''));
	        $sameCustomer = true;
        $minInvoiceDate = (string) ($sorted[0]['invoice_date'] ?? date('Y-m-d'));
        $maxDueDate = (string) ($sorted[0]['due_date'] ?? date('Y-m-d'));

        foreach ($sorted as $row) {
            if ($this->normalizeDocumentType((string) ($row['document_type'] ?? 'invoice')) !== 'proforma') {
                $documentType = 'invoice';
            }
            if ($this->normalizeInvoiceType((string) ($row['invoice_type'] ?? 'product')) === 'product') {
                $invoiceType = 'product';
            }
            $rowCustomer = trim((string) ($row['customer_name'] ?? ''));
            $rowPhone = trim((string) ($row['customer_phone'] ?? ''));
            if ($rowCustomer !== $customerName || $rowPhone !== $customerPhone) {
                $sameCustomer = false;
            }
            $rowDate = (string) ($row['invoice_date'] ?? $minInvoiceDate);
            if (strtotime($rowDate) < strtotime($minInvoiceDate)) {
                $minInvoiceDate = $rowDate;
            }
            $rowDueDate = (string) ($row['due_date'] ?? $maxDueDate);
            if (strtotime($rowDueDate) > strtotime($maxDueDate)) {
                $maxDueDate = $rowDueDate;
            }
        }

	        if (!$sameCustomer) {
	            $customerName = 'Clients multiples';
	            $customerPhone = '';
	            $customerTaxId = '';
	            $customerAddress = '';
	            $customerDescription = '';
	        }

        $mergedItems = [];
        foreach ($sorted as $row) {
            foreach ((array) ($row['items'] ?? []) as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $description = trim((string) ($item['description'] ?? ''));
                $unitCode = strtolower(trim((string) ($item['unit_code'] ?? '')));
                $unitPrice = round((float) ($item['unit_price'] ?? 0), 2);
                $taxRate = round((float) ($item['tax_rate'] ?? 0), 2);
                $factor = round((float) ($item['factor_to_base'] ?? 1), 6);
                $key = implode('|', [
                    $productId,
                    $description,
                    $unitCode,
                    number_format($unitPrice, 2, '.', ''),
                    number_format($taxRate, 2, '.', ''),
                    number_format($factor, 6, '.', ''),
                ]);

	                if (!isset($mergedItems[$key])) {
	                    $lineKind = strtolower(trim((string) ($item['line_kind'] ?? 'standard')));
	                    if (!in_array($lineKind, ['other', 'vat'], true)) {
	                        $lineKind = 'standard';
	                    }
	                    $mergedItems[$key] = [
	                        'line_kind' => $lineKind,
	                        'product_id' => $productId > 0 ? $productId : null,
	                        'description' => $description,
	                        'quantity' => 0.0,
	                        'unit_code' => $unitCode !== '' ? $unitCode : null,
                        'factor_to_base' => $factor > 0 ? $factor : 1.0,
                        'quantity_base' => 0.0,
                        'stock_movement_id' => null,
                        'cogs_amount' => 0.0,
                        'margin_amount' => 0.0,
                        'unit_price' => $unitPrice,
                        'tax_rate' => $taxRate,
                        'subtotal' => 0.0,
                        'tax_amount' => 0.0,
                        'total' => 0.0,
                    ];
                }

                $qty = round((float) ($item['quantity'] ?? 0), 2);
                $qtyBase = round((float) ($item['quantity_base'] ?? ($qty * $factor)), 6);
                $mergedItems[$key]['quantity'] = round($mergedItems[$key]['quantity'] + $qty, 2);
                $mergedItems[$key]['quantity_base'] = round($mergedItems[$key]['quantity_base'] + $qtyBase, 6);
            }
        }

        if ($mergedItems === []) {
            throw new \InvalidArgumentException('Aucune ligne a fusionner.');
        }

        $items = array_values($mergedItems);
        $this->assertStockAvailabilityForItems($companyId, $items, $invoiceType);
        $subtotal = 0.0;
        $taxAmount = 0.0;
        $mergedLaborAmount = 0.0;
        $mergedLaborTaxAmount = 0.0;
        $mergedPaidAmount = 0.0;
        foreach ($sorted as $row) {
            $mergedPaidAmount += (float) ($row['paid_amount'] ?? 0);
            $mergedLaborAmount += (float) ($row['labor_amount'] ?? 0);
            $mergedLaborTaxAmount += (float) ($row['labor_tax_amount'] ?? 0);
        }
        $mergedPaidAmount = round(max(0, $mergedPaidAmount), 2);
        $mergedLaborAmount = round(max(0, $mergedLaborAmount), 2);
        $mergedLaborTaxAmount = round(max(0, $mergedLaborTaxAmount), 2);
        $mergedLaborTaxRate = $mergedLaborAmount > 0 ? round(($mergedLaborTaxAmount / $mergedLaborAmount) * 100, 2) : 0.0;
        foreach ($items as $index => $item) {
            $lineSubtotal = round((float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0), 2);
            $lineTax = round($lineSubtotal * ((float) ($item['tax_rate'] ?? 0) / 100), 2);
            $lineTotal = round($lineSubtotal + $lineTax, 2);
            $subtotal += $lineSubtotal;
            $taxAmount += $lineTax;
            $items[$index]['subtotal'] = $lineSubtotal;
            $items[$index]['tax_amount'] = $lineTax;
            $items[$index]['total'] = $lineTotal;
            $items[$index]['margin_amount'] = $lineSubtotal;
        }

        $subtotal = round($subtotal + $mergedLaborAmount, 2);
        $taxAmount = round($taxAmount + $mergedLaborTaxAmount, 2);
        $total = round($subtotal + $taxAmount, 2);
        if ($documentType === 'proforma') {
            $mergedPaidAmount = 0.0;
        } else {
            $mergedPaidAmount = min($mergedPaidAmount, $total);
        }
        $mergedStatus = 'draft';
        if ($documentType !== 'proforma' && $mergedPaidAmount > 0) {
            $mergedStatus = $mergedPaidAmount >= $total ? 'paid' : 'sent';
        }
        $averageTaxRate = $subtotal > 0 ? round(($taxAmount / $subtotal) * 100, 2) : 0.0;
        $fiscalPeriodId = (new FiscalPeriod($this->db))->resolvePeriodIdForDate($companyId, $minInvoiceDate);
        $issuer = $this->getIssuerSnapshot($companyId);
        $invoiceNumber = $this->generateNextNumber($companyId, $documentType);
        $sourceNumbers = array_map(static fn(array $row): string => (string) ($row['invoice_number'] ?? ''), $sorted);
        $notes = 'Fusion de factures: ' . implode(', ', $sourceNumbers);

        $mergedInvoiceId = 0;
        $this->db->beginTransaction();
        try {
	            foreach ($sorted as $row) {
	                if (
	                    $this->normalizeDocumentType((string) ($row['document_type'] ?? 'invoice')) === 'invoice'
	                    && $this->normalizeInvoiceType((string) ($row['invoice_type'] ?? 'product')) === 'product'
	                    && $this->hasStockReservation($row)
	                ) {
	                    $this->releaseStockForItems($companyId, (string) ($row['invoice_number'] ?? ''), (array) ($row['items'] ?? []), $userId);
	                }
                $this->db->execute(
                    'UPDATE invoices
                     SET status = :status
                     WHERE id = :id
                       AND company_id = :company_id',
                    [
                        'status' => 'cancelled',
                        'id' => (int) ($row['id'] ?? 0),
                        'company_id' => $companyId,
                    ]
                );
            }

	            $this->db->execute(
	                'INSERT INTO invoices (company_id, document_type, invoice_number, invoice_date, due_date, customer_name, customer_phone, customer_tax_id, customer_address, customer_description, subtotal, tax_rate, tax_amount, labor_amount, labor_tax_rate, labor_tax_amount, total, paid_amount, status, invoice_type, issuer_company_name, issuer_logo_url, issuer_brand_color, fiscal_period_id, notes, created_by)
	                 VALUES (:company_id, :document_type, :invoice_number, :invoice_date, :due_date, :customer_name, :customer_phone, :customer_tax_id, :customer_address, :customer_description, :subtotal, :tax_rate, :tax_amount, :labor_amount, :labor_tax_rate, :labor_tax_amount, :total, :paid_amount, :status, :invoice_type, :issuer_company_name, :issuer_logo_url, :issuer_brand_color, :fiscal_period_id, :notes, :created_by)',
	                [
	                    'company_id' => $companyId,
	                    'document_type' => $documentType,
	                    'invoice_number' => $invoiceNumber,
	                    'invoice_date' => $minInvoiceDate,
	                    'due_date' => $maxDueDate,
	                    'customer_name' => $customerName !== '' ? $customerName : 'Client',
	                    'customer_phone' => $customerPhone !== '' ? $customerPhone : null,
	                    'customer_tax_id' => $customerTaxId !== '' ? $customerTaxId : null,
	                    'customer_address' => $customerAddress !== '' ? $customerAddress : null,
	                    'customer_description' => $customerDescription !== '' ? $customerDescription : null,
	                    'subtotal' => $subtotal,
	                    'tax_rate' => $averageTaxRate,
	                    'tax_amount' => $taxAmount,
	                    'labor_amount' => $mergedLaborAmount,
                    'labor_tax_rate' => $mergedLaborTaxRate,
                    'labor_tax_amount' => $mergedLaborTaxAmount,
                    'total' => $total,
                    'paid_amount' => $mergedPaidAmount,
                    'status' => $mergedStatus,
                    'invoice_type' => $invoiceType,
                    'issuer_company_name' => $issuer['company_name'],
                    'issuer_logo_url' => $issuer['logo_url'],
                    'issuer_brand_color' => $issuer['brand_color'],
                    'fiscal_period_id' => $fiscalPeriodId,
                    'notes' => $notes,
                    'created_by' => $userId,
                ]
            );

            $mergedInvoiceId = $this->db->lastInsertId();

	            if ($documentType === 'invoice' && $invoiceType === 'product' && $mergedStatus !== 'draft') {
	                $this->reserveStockForItems($companyId, $invoiceNumber, $items, $userId);
	            }

            foreach ($items as $item) {
                $this->db->execute(
	                    'INSERT INTO invoice_items (invoice_id, line_kind, product_id, description, quantity, unit_code, factor_to_base, quantity_base, stock_movement_id, cogs_amount, margin_amount, unit_price, tax_rate, subtotal, tax_amount, total)
	                     VALUES (:invoice_id, :line_kind, :product_id, :description, :quantity, :unit_code, :factor_to_base, :quantity_base, :stock_movement_id, :cogs_amount, :margin_amount, :unit_price, :tax_rate, :subtotal, :tax_amount, :total)',
	                    [
	                        'invoice_id' => $mergedInvoiceId,
	                        'line_kind' => (string) ($item['line_kind'] ?? 'standard'),
	                        'product_id' => $item['product_id'],
	                        'description' => $item['description'],
	                        'quantity' => $item['quantity'],
	                        'unit_code' => $item['unit_code'],
                        'factor_to_base' => $item['factor_to_base'],
                        'quantity_base' => $item['quantity_base'],
                        'stock_movement_id' => $item['stock_movement_id'] ?? null,
                        'cogs_amount' => round((float) ($item['cogs_amount'] ?? 0), 2),
                        'margin_amount' => round((float) ($item['margin_amount'] ?? 0), 2),
                        'unit_price' => $item['unit_price'],
                        'tax_rate' => $item['tax_rate'],
                        'subtotal' => $item['subtotal'],
                        'tax_amount' => $item['tax_amount'],
                        'total' => $item['total'],
                    ]
                );
            }

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }

        if ($mergedInvoiceId <= 0) {
            throw new \RuntimeException('Fusion facture invalide.');
        }

        $merged = $this->findByIdForCompany($companyId, $mergedInvoiceId);
        $this->appendInvoiceHistory($companyId, $mergedInvoiceId, 'merged', null, $merged, [
            'source_invoice_numbers' => $sourceNumbers,
        ]);

        return $mergedInvoiceId;
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

    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        $map = [
            'brouillon' => 'draft',
            'draft' => 'draft',
            'envoyee' => 'sent',
            'envoyée' => 'sent',
            'sent' => 'sent',
            'payee' => 'paid',
            'payée' => 'paid',
            'paid' => 'paid',
            'overdue' => 'overdue',
            'en_retard' => 'overdue',
            'cancelled' => 'cancelled',
        ];

        return $map[$normalized] ?? 'sent';
    }

    private function normalizeDocumentType(string $documentType): string
    {
        $normalized = strtolower(trim($documentType));
        $map = [
            'proforma' => 'proforma',
            'pro-forma' => 'proforma',
            'pf' => 'proforma',
            'invoice' => 'invoice',
            'facture' => 'invoice',
        ];

        return $map[$normalized] ?? 'invoice';
    }

    private function normalizeInvoiceType(string $invoiceType): string
    {
        $normalized = strtolower(trim($invoiceType));
        return $normalized === 'service' ? 'service' : 'product';
    }

    private function getIssuerSnapshot(int $companyId): array
    {
        $company = $this->db->fetchOne(
            'SELECT name, invoice_logo_url, invoice_brand_color
             FROM companies
             WHERE id = :id
             LIMIT 1',
            ['id' => $companyId]
        ) ?? [];

        return [
            'company_name' => (string) ($company['name'] ?? ''),
            'logo_url' => (string) ($company['invoice_logo_url'] ?? ''),
            'brand_color' => (string) ($company['invoice_brand_color'] ?? '#0F172A'),
        ];
    }

    private function parseInvoiceItemsFromPayload(int $companyId, array $payload, string $invoiceType): array
    {
        $lineDescriptions = $payload['line_description'] ?? [];
        $lineQty = $payload['line_qty'] ?? [];
        $lineUnitCodes = $payload['line_unit_code'] ?? [];
        $linePrices = $payload['line_price'] ?? [];
        $lineTaxes = $payload['line_tax'] ?? [];
        $lineProductIds = $payload['line_product_id'] ?? [];
        $lineKinds = $payload['line_kind'] ?? [];

        if (!is_array($lineDescriptions) || !is_array($lineQty) || !is_array($lineUnitCodes) || !is_array($linePrices) || !is_array($lineTaxes) || !is_array($lineProductIds) || (!is_array($lineKinds) && $lineKinds !== null)) {
            throw new \InvalidArgumentException('Les lignes de facture sont invalides.');
        }

        $lineCount = count($lineQty);
        if (!is_array($lineKinds) || $lineKinds === []) {
            $lineKinds = array_fill(0, $lineCount, 'standard');
        }
        if (
            $lineCount === 0
            || $lineCount !== count($lineDescriptions)
            || $lineCount !== count($lineUnitCodes)
            || $lineCount !== count($linePrices)
            || $lineCount !== count($lineTaxes)
            || $lineCount !== count($lineProductIds)
            || $lineCount !== count($lineKinds)
        ) {
            throw new \InvalidArgumentException('Les lignes de facture sont incompletes.');
        }

        $catalog = [];
        $unitMap = [];
        if ($invoiceType === 'product') {
            $requested = [];
            for ($i = 0; $i < $lineCount; $i++) {
                $kind = strtolower(trim((string) ($lineKinds[$i] ?? 'standard')));
                if (in_array($kind, ['other', 'vat'], true)) {
                    continue;
                }
                $productId = (int) ($lineProductIds[$i] ?? 0);
                if ($productId <= 0) {
                    throw new \InvalidArgumentException('Selectionnez un produit de stock pour chaque ligne.');
                }
                $requested[] = $productId;
            }
            $catalog = $this->loadProductsCatalog($companyId, $requested);
            $unitMap = $this->loadProductUnitConversions($companyId, array_keys($catalog));
        }

        $subtotalRaw = 0.0;
        $taxAmount = 0.0;
        $vatBaseRaw = 0.0;
        $vatRate = null;
        $items = [];

        for ($i = 0; $i < $lineCount; $i++) {
            $kind = strtolower(trim((string) ($lineKinds[$i] ?? 'standard')));
            if (!in_array($kind, ['standard', 'other', 'vat'], true)) {
                $kind = 'standard';
            }

            if ($kind === 'vat') {
                $candidateRate = round((float) $lineTaxes[$i], 2);
                if ($candidateRate < 0) {
                    throw new \InvalidArgumentException('Le taux TVA est invalide.');
                }
                if ($vatRate === null) {
                    $vatRate = $candidateRate;
                }
                continue;
            }

            $isOther = $kind === 'other';
            $qty = round((float) $lineQty[$i], 6);
            $price = round((float) $linePrices[$i], 2);
            $productId = null;
            $description = trim((string) ($lineDescriptions[$i] ?? ''));
            $unitCode = null;
            $factorToBase = 1.0;
            $quantityBase = null;

            if ($invoiceType === 'product') {
                if (!$isOther) {
                    $productId = (int) ($lineProductIds[$i] ?? 0);
                    if ($productId <= 0) {
                        throw new \InvalidArgumentException('Selectionnez un produit de stock pour chaque ligne.');
                    }
                    $product = $catalog[$productId] ?? null;
                    if (!is_array($product)) {
                        throw new \InvalidArgumentException('Un produit selectionne est introuvable en stock.');
                    }
                    $selectedUnitCode = strtolower(trim((string) ($lineUnitCodes[$i] ?? '')));
                    if ($selectedUnitCode === '') {
                        $selectedUnitCode = strtolower(trim((string) ($product['unit'] ?? 'unite')));
                    }
                    $factorToBase = (float) ($unitMap[$productId][$selectedUnitCode] ?? 0);
                    if ($factorToBase <= 0) {
                        throw new \InvalidArgumentException('Unite invalide pour le produit "' . (string) ($product['name'] ?? '') . '".');
                    }
                    $unitCode = $selectedUnitCode;
                    $quantityBase = round($qty * $factorToBase, 6);
                    $description = (string) ($product['name'] ?? '');
                } else {
                    $productId = null;
                    $unitCode = null;
                    $factorToBase = 1.0;
                    $quantityBase = null;
                }
            }

            if ($description === '' || $qty <= 0 || $price < 0) {
                throw new \InvalidArgumentException('Une ligne de facture contient des valeurs invalides.');
            }

            $lineSubtotal = $qty * $price;
            $lineSubtotalRounded = round($lineSubtotal, 2);
            $lineTotal = $lineSubtotalRounded;
            $subtotalRaw += $lineSubtotal;
            if (!$isOther) {
                $vatBaseRaw += $lineSubtotal;
            }

            $items[] = [
                'line_kind' => $isOther ? 'other' : 'standard',
                'product_id' => $productId,
                'description' => $description,
                'quantity' => $qty,
                'unit_code' => $unitCode,
                'factor_to_base' => round($factorToBase, 6),
                'quantity_base' => $quantityBase,
                'stock_movement_id' => null,
                'cogs_amount' => 0.0,
                'margin_amount' => $lineSubtotalRounded,
                'unit_price' => $price,
                'tax_rate' => 0.0,
                'subtotal' => $lineSubtotalRounded,
                'tax_amount' => 0.0,
                'total' => $lineTotal,
            ];
        }

        if ($vatRate !== null) {
            $vatLineTax = $vatRate > 0.0 ? round($vatBaseRaw * ($vatRate / 100), 2) : 0.0;
            $taxAmount += $vatLineTax;
            $items[] = [
                'line_kind' => 'vat',
                'product_id' => null,
                'description' => 'TVA',
                'quantity' => 1.0,
                'unit_code' => null,
                'factor_to_base' => 1.0,
                'quantity_base' => null,
                'stock_movement_id' => null,
                'cogs_amount' => 0.0,
                'margin_amount' => 0.0,
                'unit_price' => 0.0,
                'tax_rate' => round($vatRate, 2),
                'subtotal' => 0.0,
                'tax_amount' => $vatLineTax,
                'total' => $vatLineTax,
            ];
        }

        return [
            'items' => $items,
            'subtotal' => round($subtotalRaw, 2),
            'tax_amount' => round($taxAmount, 2),
        ];
    }

    private function parseLaborFromPayload(array $payload): array
    {
        $amount = round((float) ($payload['labor_amount'] ?? 0), 2);
        $taxRate = round((float) ($payload['labor_tax_rate'] ?? 0), 2);

        if ($amount < 0 || $taxRate < 0) {
            throw new \InvalidArgumentException('La main d’oeuvre est invalide.');
        }

        $taxAmount = round($amount * ($taxRate / 100), 2);

        return [
            'amount' => $amount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
        ];
    }

    private function reserveStockForItems(int $companyId, string $invoiceNumber, array &$items, int $userId): void
    {
        $productModel = new Product($this->db);
        foreach ($items as $index => $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $qty = round((float) ($item['quantity'] ?? 0), 6);
            $unitCode = strtolower(trim((string) ($item['unit_code'] ?? '')));
            if ($productId <= 0 || $qty <= 0) {
                continue;
            }

            $movementId = $productModel->adjustStock($companyId, $productId, $userId, [
                'movement_type' => 'out',
                'quantity' => $qty,
                'unit_code' => $unitCode,
                'reason' => 'Reservation facture ' . $invoiceNumber,
                'reference' => $invoiceNumber,
                'source_type' => 'invoice',
            ]);

            $cogsAmount = $this->calculateCogsForMovement($movementId);
            $subtotal = round((float) ($item['subtotal'] ?? 0), 2);
            $items[$index]['stock_movement_id'] = $movementId;
            $items[$index]['cogs_amount'] = $cogsAmount;
            $items[$index]['margin_amount'] = round($subtotal - $cogsAmount, 2);
        }
    }

    private function assertStockAvailabilityForItems(int $companyId, array $items, string $invoiceType): void
    {
        if ($this->normalizeInvoiceType($invoiceType) !== 'product') {
            return;
        }

        $requiredByProduct = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantityBase = round((float) ($item['quantity_base'] ?? 0), 6);
            if ($productId <= 0 || $quantityBase <= 0) {
                continue;
            }
            $requiredByProduct[$productId] = round(($requiredByProduct[$productId] ?? 0) + $quantityBase, 6);
        }

        if ($requiredByProduct === []) {
            return;
        }

        $catalog = $this->loadProductsCatalog($companyId, array_keys($requiredByProduct));
        foreach ($requiredByProduct as $productId => $requiredBase) {
            $product = $catalog[$productId] ?? null;
            if (!is_array($product)) {
                throw new \InvalidArgumentException('Un produit selectionne est introuvable en stock.');
            }

            $available = round((float) ($product['quantity'] ?? 0), 6);
            if ($available < $requiredBase) {
                $productName = trim((string) ($product['name'] ?? 'Produit'));
                throw new \InvalidArgumentException(
                    'Rupture de stock: "' . $productName . '" disponible '
                    . number_format(max(0, $available), 2, '.', '')
                    . ', demande '
                    . number_format($requiredBase, 2, '.', '')
                    . '.'
                );
            }
        }
    }

    private function releaseStockForItems(int $companyId, string $invoiceNumber, array $items, int $userId): void
    {
        $productModel = new Product($this->db);
        $productIds = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            if ($productId > 0) {
                $productIds[] = $productId;
            }
        }
        $catalog = $this->loadProductsCatalog($companyId, $productIds);

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $qtyBase = round((float) ($item['quantity_base'] ?? 0), 6);
            $qty = round((float) ($item['quantity'] ?? 0), 6);
            $unitCode = strtolower(trim((string) ($item['unit_code'] ?? '')));
            $product = $catalog[$productId] ?? null;

            if ($productId <= 0 || !is_array($product)) {
                continue;
            }

            if ($qtyBase > 0) {
                $unitCode = strtolower(trim((string) ($product['unit'] ?? 'unite')));
                $qty = $qtyBase;
            } elseif ($qty <= 0) {
                continue;
            }

            $productModel->adjustStock($companyId, $productId, $userId, [
                'movement_type' => 'in',
                'quantity' => $qty,
                'unit_code' => $unitCode,
                'reason' => 'Restitution stock facture ' . $invoiceNumber,
                'reference' => $invoiceNumber,
                'source_type' => 'invoice_cancel',
            ]);
        }
    }

    private function extractProductQuantities(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $qty = round((float) ($item['quantity'] ?? 0), 2);
            if ($productId <= 0 || $qty <= 0) {
                continue;
            }
            $result[$productId] = round(($result[$productId] ?? 0) + $qty, 2);
        }

        return $result;
    }

    private function loadProductsCatalog(int $companyId, array $productIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $productIds)));
        $ids = array_filter($ids, static fn($id) => $id > 0);
        if ($ids === []) {
            return [];
        }

        $placeholders = [];
        $params = [
            'company_id' => $companyId,
            'today' => date('Y-m-d'),
        ];
        foreach ($ids as $index => $productId) {
            $key = 'pid_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $productId;
        }

        $rows = $this->db->fetchAll(
            'SELECT p.id,
                    p.name,
                    p.unit,
                    p.purchase_price,
                    CASE
                        WHEN COUNT(l.id) = 0 THEN p.quantity
                        ELSE COALESCE(SUM(CASE
                            WHEN l.quantity_remaining_base > 0
                             AND (l.expiration_date IS NULL OR l.expiration_date = \'\' OR l.expiration_date > :today)
                            THEN l.quantity_remaining_base
                            ELSE 0
                        END), 0)
                    END AS quantity
             FROM products p
             LEFT JOIN stock_lots l
               ON l.product_id = p.id
              AND l.company_id = p.company_id
              AND COALESCE(l.is_declassified, 0) = 0
             WHERE p.company_id = :company_id
               AND p.is_active = 1
               AND p.id IN (' . implode(', ', $placeholders) . ')
             GROUP BY p.id, p.name, p.unit, p.purchase_price, p.quantity',
            $params
        );

        $catalog = [];
        foreach ($rows as $row) {
            $catalog[(int) ($row['id'] ?? 0)] = $row;
        }

        return $catalog;
    }

    private function loadProductUnitConversions(int $companyId, array $productIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $productIds)));
        $ids = array_filter($ids, static fn($id) => $id > 0);
        if ($ids === []) {
            return [];
        }

        $placeholders = [];
        $params = ['company_id' => $companyId];
        foreach ($ids as $index => $productId) {
            $key = 'upid_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $productId;
        }

        $rows = $this->db->fetchAll(
            'SELECT product_id, unit_code, factor_to_base
             FROM product_unit_conversions
             WHERE company_id = :company_id
               AND is_active = 1
               AND product_id IN (' . implode(', ', $placeholders) . ')',
            $params
        );

        $result = [];
        foreach ($rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $unitCode = strtolower(trim((string) ($row['unit_code'] ?? '')));
            $factor = round((float) ($row['factor_to_base'] ?? 0), 6);
            if ($productId <= 0 || $unitCode === '' || $factor <= 0) {
                continue;
            }
            $result[$productId][$unitCode] = $factor;
        }

        return $result;
    }

    private function calculateCogsForMovement(int $movementId): float
    {
        if ($movementId <= 0) {
            return 0.0;
        }

        $row = $this->db->fetchOne(
            'SELECT COALESCE(SUM(ABS(a.quantity_base) * COALESCE(l.unit_cost_base, 0)), 0) AS cogs
             FROM stock_lot_allocations a
             INNER JOIN stock_lots l ON l.id = a.lot_id
             WHERE a.stock_movement_id = :movement_id',
            ['movement_id' => $movementId]
        );

        return round((float) ($row['cogs'] ?? 0), 2);
    }

    private function appendStockMovement(
        int $companyId,
        int $productId,
        string $type,
        float $change,
        float $before,
        float $after,
        string $reason,
        int $userId
    ): void {
        $this->db->execute(
            'INSERT INTO stock_movements (product_id, company_id, movement_type, quantity_change, quantity_before, quantity_after, reason, created_by)
             VALUES (:product_id, :company_id, :movement_type, :quantity_change, :quantity_before, :quantity_after, :reason, :created_by)',
            [
                'product_id' => $productId,
                'company_id' => $companyId,
                'movement_type' => $type,
                'quantity_change' => round($change, 2),
                'quantity_before' => round($before, 2),
                'quantity_after' => round($after, 2),
                'reason' => substr($reason, 0, 255),
                'created_by' => $userId > 0 ? $userId : null,
            ]
        );
    }

    private function buildNotes(array $payload, string $discountType, float $discountValue, float $deposit, float $remaining): ?string
    {
        $chunks = [];

        $noteClient = trim((string) ($payload['note_client'] ?? ''));
        if ($noteClient !== '') {
            $chunks[] = 'Note client: ' . $noteClient;
        }

        $paymentTerms = trim((string) ($payload['payment_terms'] ?? ''));
        if ($paymentTerms !== '') {
            $chunks[] = 'Conditions: ' . $paymentTerms;
        }

        $paymentMethod = trim((string) ($payload['payment_method'] ?? ''));
        if ($paymentMethod !== '') {
            $chunks[] = 'Paiement: ' . $paymentMethod;
        }

        $discountValue = max(0, $discountValue);
        if ($discountValue > 0) {
            $chunks[] = sprintf('Remise: %s %s', $discountValue, $discountType === 'percent' ? '%' : 'fixe');
        }

        if ($deposit > 0) {
            $chunks[] = sprintf('Acompte: %.2f | Reste: %.2f', $deposit, $remaining);
        }

        if ($chunks === []) {
            return null;
        }

        return implode("\n", $chunks);
    }

    private function resolveClientFromPayload(array $payload): array
    {
        $clientType = trim((string) ($payload['client_type'] ?? 'known'));
        $customerName = trim((string) ($payload['client_name'] ?? ''));
        $customerPhone = $this->normalizePhone((string) ($payload['client_phone'] ?? ''));
        if ($customerPhone === '') {
            $prefix = $this->normalizePhone((string) ($payload['client_phone_prefix'] ?? ''));
            $local = ltrim($this->normalizePhone((string) ($payload['client_phone_local'] ?? '')), '0');
            if ($prefix !== '' && $local !== '') {
                $customerPhone = $prefix . $local;
            }
        }

        $lookup = trim((string) ($payload['client_lookup'] ?? ''));
        if ($customerName === '' && $customerPhone === '' && $lookup !== '') {
            $digits = $this->normalizePhone($lookup);
            $hasLetters = preg_match('/[a-z]/i', $lookup) === 1;
            if ($hasLetters) {
                $nameOnly = trim((string) preg_replace('/[0-9+]+/', ' ', $lookup));
                $customerName = $nameOnly !== '' ? $nameOnly : $lookup;
                if ($digits !== '') {
                    $customerPhone = $digits;
                }
            } else {
                $customerPhone = $digits;
            }
        }

        $customerTaxId = trim((string) ($payload['client_identifier'] ?? ''));
        $customerAddress = trim((string) ($payload['client_address'] ?? ''));

        if ($clientType === 'anonymous') {
            $customerName = 'Client anonyme';
            $customerPhone = '';
            $customerTaxId = '';
            $customerAddress = '';
        } else {
            if ($customerName === '' && $customerPhone === '') {
                throw new \InvalidArgumentException('Nom ou numero de telephone obligatoire pour un client connu.');
            }
            if ($customerName === '' && $customerPhone !== '') {
                $customerName = $customerPhone;
            } elseif ($customerName === '') {
                $customerName = 'Nouveau client';
            }
        }

        return [$customerName, $customerPhone, $customerTaxId, $customerAddress, $clientType];
    }

    private function appendInvoiceHistory(
        int $companyId,
        int $invoiceId,
        string $eventType,
        ?array $before = null,
        ?array $after = null,
        array $meta = []
    ): void {
        if ($before === null && $after === null) {
            return;
        }

        $payload = [
            'before' => $this->buildInvoiceSnapshot($before),
            'after' => $this->buildInvoiceSnapshot($after),
            'meta' => $meta,
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            $encoded = '{}';
        }

        try {
            $this->db->execute(
                'INSERT INTO invoice_history (invoice_id, company_id, event_type, invoice_number, status_before, status_after, payload_json)
                 VALUES (:invoice_id, :company_id, :event_type, :invoice_number, :status_before, :status_after, :payload_json)',
                [
                    'invoice_id' => $invoiceId,
                    'company_id' => $companyId,
                    'event_type' => substr($eventType, 0, 60),
                    'invoice_number' => (string) (($after['invoice_number'] ?? $before['invoice_number'] ?? '')),
                    'status_before' => (string) (($before['status'] ?? '')),
                    'status_after' => (string) (($after['status'] ?? '')),
                    'payload_json' => $encoded,
                ]
            );
        } catch (\Throwable $exception) {
            // Never block invoice operations because of audit persistence.
        }
    }

    private function buildInvoiceSnapshot(?array $invoice): ?array
    {
        if (!is_array($invoice)) {
            return null;
        }

        return [
            'id' => (int) ($invoice['id'] ?? 0),
            'invoice_number' => (string) ($invoice['invoice_number'] ?? ''),
            'invoice_date' => (string) ($invoice['invoice_date'] ?? ''),
            'due_date' => (string) ($invoice['due_date'] ?? ''),
            'customer_name' => (string) ($invoice['customer_name'] ?? ''),
            'customer_phone' => (string) ($invoice['customer_phone'] ?? ''),
            'subtotal' => round((float) ($invoice['subtotal'] ?? 0), 2),
            'tax_amount' => round((float) ($invoice['tax_amount'] ?? 0), 2),
            'total' => round((float) ($invoice['total'] ?? 0), 2),
            'paid_amount' => round((float) ($invoice['paid_amount'] ?? 0), 2),
            'status' => (string) ($invoice['status'] ?? ''),
            'invoice_type' => (string) ($invoice['invoice_type'] ?? 'product'),
            'issuer_company_name' => (string) ($invoice['issuer_company_name'] ?? ''),
            'issuer_logo_url' => (string) ($invoice['issuer_logo_url'] ?? ''),
            'issuer_brand_color' => (string) ($invoice['issuer_brand_color'] ?? ''),
            'items' => $invoice['items'] ?? [],
        ];
    }

    private function aggregateClientRows(array $rows): array
    {
        $clients = [];
        foreach ($rows as $row) {
            $phone = $this->normalizePhone((string) ($row['customer_phone'] ?? ''));
            $name = trim((string) ($row['customer_name'] ?? ''));
            $key = $phone !== '' ? 'phone:' . $phone : 'name:' . strtolower($name);
            if ($key === 'name:') {
                continue;
            }

            if (!isset($clients[$key])) {
                $clients[$key] = [
                    'name' => $name !== '' ? $name : 'Client',
                    'phone' => (string) ($row['customer_phone'] ?? ''),
                    'invoice_count' => 0,
                    'total_amount' => 0.0,
                    'total_paid' => 0.0,
                    'debt_count' => 0,
                    'debt_total' => 0.0,
                    'is_regular' => false,
                ];
            }

            $total = round((float) ($row['total'] ?? 0), 2);
            $paid = round((float) ($row['paid_amount'] ?? 0), 2);
            $debt = round(max($total - $paid, 0), 2);

            $clients[$key]['invoice_count'] += 1;
            $clients[$key]['total_amount'] += $total;
            $clients[$key]['total_paid'] += $paid;
            $clients[$key]['debt_total'] += $debt;
            if ($debt > 0.009) {
                $clients[$key]['debt_count'] += 1;
            }

            if ($name !== '' && (($clients[$key]['name'] ?? '') === 'Client' || strlen($name) > strlen((string) ($clients[$key]['name'] ?? '')))) {
                $clients[$key]['name'] = $name;
            }
            if (($clients[$key]['phone'] ?? '') === '' && (string) ($row['customer_phone'] ?? '') !== '') {
                $clients[$key]['phone'] = (string) ($row['customer_phone'] ?? '');
            }
        }

        foreach ($clients as &$client) {
            $client['total_amount'] = round((float) ($client['total_amount'] ?? 0), 2);
            $client['total_paid'] = round((float) ($client['total_paid'] ?? 0), 2);
            $client['debt_total'] = round((float) ($client['debt_total'] ?? 0), 2);
            $client['is_regular'] = (int) ($client['invoice_count'] ?? 0) >= 2;
            $client['label'] = $this->buildClientLabel(
                (string) ($client['name'] ?? ''),
                (string) ($client['phone'] ?? '')
            );
        }
        unset($client);

        usort($clients, static function (array $left, array $right): int {
            $leftDebt = (float) ($left['debt_total'] ?? 0);
            $rightDebt = (float) ($right['debt_total'] ?? 0);
            if ($leftDebt !== $rightDebt) {
                return $rightDebt <=> $leftDebt;
            }

            return ((int) ($right['invoice_count'] ?? 0)) <=> ((int) ($left['invoice_count'] ?? 0));
        });

        return array_values($clients);
    }

    private function buildClientIdentityWhere(string $clientName, string $clientPhone, array &$params, string $prefix = ''): ?string
    {
        $normalizedPhone = $this->normalizePhone($clientPhone);
        if ($normalizedPhone !== '') {
            $params['client_phone_norm'] = $normalizedPhone;
            return $this->phoneSqlExpression($prefix . 'customer_phone') . ' = :client_phone_norm';
        }

        $clientName = trim($clientName);
        if ($clientName === '') {
            return null;
        }

        $params['client_name_exact'] = $clientName;
        return 'LOWER(COALESCE(' . $prefix . 'customer_name, \'\')) = LOWER(:client_name_exact)';
    }

    private function phoneSqlExpression(string $column): string
    {
        return 'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE(' . $column . ', \'\')), \' \', \'\'), \'-\', \'\'), \'+\', \'\'), \'(\', \'\'), \')\', \'\'), \'.\', \'\')';
    }

    private function clientConcatExpression(string $phoneColumn, string $nameColumn): string
    {
        $driver = strtolower((string) \Config::DB_DRIVER);
        if ($driver === 'sqlite') {
            return "LOWER(TRIM(COALESCE($phoneColumn, '') || ' ' || COALESCE($nameColumn, '')))";
        }
        return "LOWER(TRIM(CONCAT(COALESCE($phoneColumn, ''), ' ', COALESCE($nameColumn, ''))))";
    }

    private function buildClientLabel(string $name, string $phone): string
    {
        $name = trim($name);
        $phone = trim($phone);
        if ($phone !== '' && $name !== '') {
            return $phone . ' - ' . $name;
        }
        return $name !== '' ? $name : $phone;
    }

    private function clientsTableExists(): bool
    {
        try {
            $this->db->fetchOne('SELECT 1 FROM clients LIMIT 1');
            return true;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    private function normalizePhone(string $value): string
    {
        $digits = preg_replace('/[^0-9]+/', '', trim($value));
        return is_string($digits) ? $digits : '';
    }
}
