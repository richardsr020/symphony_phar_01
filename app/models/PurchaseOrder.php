<?php

namespace App\Models;

class PurchaseOrder extends Model
{
    public function getByCompany(int $companyId, int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        $orders = $this->db->fetchAll(
            'SELECT p.id, p.order_number, p.supplier_name, p.status, p.expected_date, p.total_amount, p.notes, p.created_at,
                    u.first_name AS created_by_first_name, u.last_name AS created_by_last_name
             FROM purchase_orders p
             LEFT JOIN users u ON u.id = p.created_by
             WHERE p.company_id = :company_id
             ORDER BY p.created_at DESC, p.id DESC
             LIMIT ' . $limit,
            ['company_id' => $companyId]
        );

        if ($orders === []) {
            return [];
        }

        $ids = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $orders);
        $ids = array_values(array_filter($ids, static fn(int $id): bool => $id > 0));
        if ($ids === []) {
            return $orders;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $items = $this->db->fetchAll(
            'SELECT i.purchase_order_id, i.product_id, i.description, i.quantity, i.unit_cost, i.line_total,
                    p.name AS product_name, p.sku
             FROM purchase_order_items i
             INNER JOIN products p ON p.id = i.product_id
             WHERE i.purchase_order_id IN (' . $placeholders . ')
             ORDER BY i.id ASC',
            $ids
        );

        $itemsByOrder = [];
        foreach ($items as $item) {
            $orderId = (int) ($item['purchase_order_id'] ?? 0);
            $itemsByOrder[$orderId][] = $item;
        }

        foreach ($orders as &$order) {
            $orderId = (int) ($order['id'] ?? 0);
            $fullName = trim((string) (($order['created_by_first_name'] ?? '') . ' ' . ($order['created_by_last_name'] ?? '')));
            $order['created_by_name'] = $fullName !== '' ? $fullName : 'Utilisateur';
            $order['items'] = $itemsByOrder[$orderId] ?? [];
        }
        unset($order);

        return $orders;
    }

    public function findByIdForCompany(int $companyId, int $orderId): ?array
    {
        $order = $this->db->fetchOne(
            'SELECT p.id, p.order_number, p.supplier_name, p.status, p.expected_date, p.total_amount, p.notes, p.created_at,
                    u.first_name AS created_by_first_name, u.last_name AS created_by_last_name
             FROM purchase_orders p
             LEFT JOIN users u ON u.id = p.created_by
             WHERE p.company_id = :company_id
               AND p.id = :id
             LIMIT 1',
            [
                'company_id' => $companyId,
                'id' => $orderId,
            ]
        );

        if ($order === null) {
            return null;
        }

        $items = $this->db->fetchAll(
            'SELECT i.purchase_order_id, i.product_id, i.description, i.quantity, i.unit_cost, i.line_total,
                    p.name AS product_name, p.sku
             FROM purchase_order_items i
             LEFT JOIN products p ON p.id = i.product_id
             WHERE i.purchase_order_id = :purchase_order_id
             ORDER BY i.id ASC',
            ['purchase_order_id' => $orderId]
        );

        $fullName = trim((string) (($order['created_by_first_name'] ?? '') . ' ' . ($order['created_by_last_name'] ?? '')));
        $order['created_by_name'] = $fullName !== '' ? $fullName : 'Utilisateur';
        $order['items'] = $items;

        return $order;
    }

    public function createManual(int $companyId, int $userId, array $payload): int
    {
        $supplier = trim((string) ($payload['supplier_name'] ?? ''));
        $expectedDate = $this->normalizeDate((string) ($payload['expected_date'] ?? ''));
        $notes = trim((string) ($payload['notes'] ?? ''));
        $productIds = $payload['po_product_id'] ?? [];
        $quantities = $payload['po_qty'] ?? [];
        $unitCosts = $payload['po_unit_cost'] ?? [];

        if ($supplier === '') {
            throw new \InvalidArgumentException('Fournisseur obligatoire.');
        }

        $items = $this->buildItems($companyId, $productIds, $quantities, $unitCosts);
        if ($items === []) {
            throw new \InvalidArgumentException('Ajoutez au moins une ligne valide.');
        }

        return $this->createOrder($companyId, $userId, $supplier, $expectedDate, $notes, $items);
    }

    public function generateOrderFromCritical(
        int $companyId,
        int $userId,
        string $supplierName = 'Fournisseur a confirmer',
        int $horizonDays = 21
    ): ?array {
        $recommendations = $this->getCriticalRecommendations($companyId, $horizonDays);
        if ($recommendations === []) {
            return null;
        }

        $items = [];
        foreach ($recommendations as $row) {
            $qty = round((float) ($row['suggested_qty'] ?? 0), 2);
            if ($qty <= 0) {
                continue;
            }
            $purchasePrice = round((float) ($row['purchase_price'] ?? 0), 2);
            $items[] = [
                'product_id' => (int) ($row['product_id'] ?? 0),
                'description' => (string) ($row['name'] ?? ''),
                'quantity' => $qty,
                'unit_cost' => $purchasePrice,
                'line_total' => round($qty * $purchasePrice, 2),
            ];
        }

        if ($items === []) {
            return null;
        }

        $notes = 'Bon genere automatiquement selon seuil critique et ecoulement.';
        $orderId = $this->createOrder($companyId, $userId, $supplierName, null, $notes, $items);

        return [
            'order_id' => $orderId,
            'order_number' => $this->findOrderNumber($companyId, $orderId),
            'items' => $items,
            'recommendations' => $recommendations,
        ];
    }

    public function updateStatus(int $companyId, int $orderId, int $userId, string $status): bool
    {
        $allowed = ['draft', 'sent', 'approved', 'received', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Statut de bon de commande invalide.');
        }

        $order = $this->db->fetchOne(
            'SELECT id, status, order_number
             FROM purchase_orders
             WHERE id = :id AND company_id = :company_id
             LIMIT 1',
            [
                'id' => $orderId,
                'company_id' => $companyId,
            ]
        );

        if ($order === null) {
            throw new \InvalidArgumentException('Bon de commande introuvable.');
        }

        $previous = (string) ($order['status'] ?? 'draft');
        if ($previous === $status) {
            return true;
        }

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                'UPDATE purchase_orders
                 SET status = :status, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND company_id = :company_id',
                [
                    'status' => $status,
                    'id' => $orderId,
                    'company_id' => $companyId,
                ]
            );

            if ($status === 'received' && $previous !== 'received') {
                $items = $this->db->fetchAll(
                    'SELECT product_id, quantity, unit_cost
                     FROM purchase_order_items
                     WHERE purchase_order_id = :purchase_order_id',
                    ['purchase_order_id' => $orderId]
                );
                $productModel = new Product($this->db);
                foreach ($items as $item) {
                    $productId = (int) ($item['product_id'] ?? 0);
                    $quantity = round((float) ($item['quantity'] ?? 0), 2);
                    $unitCost = round((float) ($item['unit_cost'] ?? 0), 6);
                    if ($productId <= 0 || $quantity <= 0) {
                        continue;
                    }
                    $productModel->adjustStock($companyId, $productId, $userId, [
                        'movement_type' => 'in',
                        'quantity' => $quantity,
                        'purchase_unit_cost' => $unitCost,
                        'reason' => 'Reception bon commande ' . (string) ($order['order_number'] ?? ''),
                        'reference' => (string) ($order['order_number'] ?? ''),
                        'source_type' => 'purchase_order',
                        'supplier' => (string) ($order['supplier_name'] ?? ''),
                    ]);
                }
            }

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }

        return true;
    }

    public function updateFromPayload(int $companyId, int $orderId, array $payload): bool
    {
        $existing = $this->findByIdForCompany($companyId, $orderId);
        if ($existing === null) {
            throw new \InvalidArgumentException('Bon de commande introuvable.');
        }

        $currentStatus = (string) ($existing['status'] ?? 'draft');
        if (!in_array($currentStatus, ['draft', 'sent', 'approved'], true)) {
            throw new \InvalidArgumentException('Seuls les bons non receptionnes peuvent etre modifies.');
        }

        $supplier = trim((string) ($payload['supplier_name'] ?? ''));
        $expectedDate = $this->normalizeDate((string) ($payload['expected_date'] ?? ''));
        $notes = trim((string) ($payload['notes'] ?? ''));
        $productIds = $payload['po_product_id'] ?? [];
        $quantities = $payload['po_qty'] ?? [];
        $unitCosts = $payload['po_unit_cost'] ?? [];

        if ($supplier === '') {
            throw new \InvalidArgumentException('Fournisseur obligatoire.');
        }

        $items = $this->buildItems($companyId, $productIds, $quantities, $unitCosts);
        if ($items === []) {
            throw new \InvalidArgumentException('Ajoutez au moins une ligne valide.');
        }

        $total = 0.0;
        foreach ($items as $item) {
            $total += (float) ($item['line_total'] ?? 0);
        }
        $total = round($total, 2);

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                'UPDATE purchase_orders
                 SET supplier_name = :supplier_name,
                     expected_date = :expected_date,
                     total_amount = :total_amount,
                     notes = :notes,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id
                   AND company_id = :company_id',
                [
                    'supplier_name' => substr($supplier, 0, 180),
                    'expected_date' => $expectedDate,
                    'total_amount' => $total,
                    'notes' => $notes !== '' ? substr($notes, 0, 5000) : null,
                    'id' => $orderId,
                    'company_id' => $companyId,
                ]
            );

            $this->db->execute(
                'DELETE FROM purchase_order_items
                 WHERE purchase_order_id = :purchase_order_id',
                ['purchase_order_id' => $orderId]
            );

            foreach ($items as $item) {
                $this->db->execute(
                    'INSERT INTO purchase_order_items (purchase_order_id, product_id, description, quantity, unit_cost, line_total)
                     VALUES (:purchase_order_id, :product_id, :description, :quantity, :unit_cost, :line_total)',
                    [
                        'purchase_order_id' => $orderId,
                        'product_id' => (int) ($item['product_id'] ?? 0),
                        'description' => substr((string) ($item['description'] ?? ''), 0, 255),
                        'quantity' => round((float) ($item['quantity'] ?? 0), 2),
                        'unit_cost' => round((float) ($item['unit_cost'] ?? 0), 2),
                        'line_total' => round((float) ($item['line_total'] ?? 0), 2),
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

    public function deleteForCompany(int $companyId, int $orderId): bool
    {
        $order = $this->db->fetchOne(
            'SELECT id, status
             FROM purchase_orders
             WHERE id = :id AND company_id = :company_id
             LIMIT 1',
            [
                'id' => $orderId,
                'company_id' => $companyId,
            ]
        );

        if ($order === null) {
            return false;
        }

        if ((string) ($order['status'] ?? '') === 'received') {
            throw new \InvalidArgumentException('Impossible de supprimer un bon deja receptionne.');
        }

        $this->db->execute(
            'DELETE FROM purchase_orders
             WHERE id = :id
               AND company_id = :company_id',
            [
                'id' => $orderId,
                'company_id' => $companyId,
            ]
        );

        return true;
    }

    public function getCriticalRecommendations(int $companyId, int $horizonDays = 21): array
    {
        $horizonDays = max(7, min(90, $horizonDays));
        $windowDays = 60;
        $sinceDate = date('Y-m-d', strtotime('-' . $windowDays . ' days'));
        $rows = $this->db->fetchAll(
            'SELECT p.id AS product_id,
                    p.name,
                    p.sku,
                    p.quantity,
                    p.min_stock,
                    p.purchase_price,
                    COALESCE(SUM(CASE WHEN m.movement_type = :out_type THEN ABS(m.quantity_change) ELSE 0 END), 0) AS outflow_qty
             FROM products p
             LEFT JOIN stock_movements m
                ON m.product_id = p.id
               AND m.company_id = p.company_id
               AND m.created_at >= :since_date
             WHERE p.company_id = :company_id
               AND p.is_active = 1
             GROUP BY p.id, p.name, p.sku, p.quantity, p.min_stock, p.purchase_price
             ORDER BY p.quantity ASC, p.name ASC',
            [
                'company_id' => $companyId,
                'since_date' => $sinceDate,
                'out_type' => 'out',
            ]
        );

        $recommendations = [];
        foreach ($rows as $row) {
            $quantity = round((float) ($row['quantity'] ?? 0), 2);
            $minStock = max(0, round((float) ($row['min_stock'] ?? 0), 2));
            $outflow = max(0, round((float) ($row['outflow_qty'] ?? 0), 2));
            $avgDailyOutflow = $outflow > 0 ? $outflow / $windowDays : 0.0;
            $daysCover = $avgDailyOutflow > 0 ? $quantity / $avgDailyOutflow : 9999;

            $targetStock = max($minStock * 2, $avgDailyOutflow * $horizonDays);
            $suggestedQty = max(0, round($targetStock - $quantity, 2));
            $isCritical = $quantity <= $minStock || $daysCover <= 14;
            if (!$isCritical || $suggestedQty <= 0) {
                continue;
            }

            $urgencyScore = (int) (($quantity <= $minStock ? 50 : 0) + ($daysCover <= 7 ? 40 : ($daysCover <= 14 ? 20 : 0)));
            $recommendations[] = [
                'product_id' => (int) ($row['product_id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'sku' => (string) ($row['sku'] ?? ''),
                'quantity' => $quantity,
                'min_stock' => $minStock,
                'outflow_qty_60d' => round($outflow, 2),
                'avg_daily_outflow' => round($avgDailyOutflow, 3),
                'days_cover' => round($daysCover, 1),
                'suggested_qty' => ceil($suggestedQty),
                'purchase_price' => round((float) ($row['purchase_price'] ?? 0), 2),
                'urgency_score' => $urgencyScore,
            ];
        }

        usort($recommendations, static function (array $a, array $b): int {
            $scoreCmp = (int) (($b['urgency_score'] ?? 0) <=> ($a['urgency_score'] ?? 0));
            if ($scoreCmp !== 0) {
                return $scoreCmp;
            }
            return (float) ($a['days_cover'] ?? 0) <=> (float) ($b['days_cover'] ?? 0);
        });

        return $recommendations;
    }

    private function createOrder(
        int $companyId,
        int $userId,
        string $supplier,
        ?string $expectedDate,
        string $notes,
        array $items
    ): int {
        $orderNumber = $this->generateNextOrderNumber($companyId);
        $total = 0.0;
        foreach ($items as $item) {
            $total += (float) ($item['line_total'] ?? 0);
        }
        $total = round($total, 2);

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                'INSERT INTO purchase_orders (company_id, order_number, supplier_name, status, expected_date, total_amount, notes, created_by)
                 VALUES (:company_id, :order_number, :supplier_name, :status, :expected_date, :total_amount, :notes, :created_by)',
                [
                    'company_id' => $companyId,
                    'order_number' => $orderNumber,
                    'supplier_name' => substr($supplier, 0, 180),
                    'status' => 'draft',
                    'expected_date' => $expectedDate,
                    'total_amount' => $total,
                    'notes' => $notes !== '' ? substr($notes, 0, 5000) : null,
                    'created_by' => $userId > 0 ? $userId : null,
                ]
            );
            $orderId = $this->db->lastInsertId();

            foreach ($items as $item) {
                $this->db->execute(
                    'INSERT INTO purchase_order_items (purchase_order_id, product_id, description, quantity, unit_cost, line_total)
                     VALUES (:purchase_order_id, :product_id, :description, :quantity, :unit_cost, :line_total)',
                    [
                        'purchase_order_id' => $orderId,
                        'product_id' => (int) ($item['product_id'] ?? 0),
                        'description' => substr((string) ($item['description'] ?? ''), 0, 255),
                        'quantity' => round((float) ($item['quantity'] ?? 0), 2),
                        'unit_cost' => round((float) ($item['unit_cost'] ?? 0), 2),
                        'line_total' => round((float) ($item['line_total'] ?? 0), 2),
                    ]
                );
            }

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }

        return $orderId;
    }

    private function buildItems(int $companyId, $productIds, $quantities, $unitCosts): array
    {
        if (!is_array($productIds) || !is_array($quantities) || !is_array($unitCosts)) {
            throw new \InvalidArgumentException('Lignes de bon de commande invalides.');
        }
        $count = count($productIds);
        if ($count === 0 || $count !== count($quantities) || $count !== count($unitCosts)) {
            throw new \InvalidArgumentException('Lignes de bon de commande incompletes.');
        }

        $requested = [];
        for ($i = 0; $i < $count; $i++) {
            $productId = (int) ($productIds[$i] ?? 0);
            if ($productId > 0) {
                $requested[] = $productId;
            }
        }
        $requested = array_values(array_unique($requested));
        if ($requested === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($requested), '?'));
        $params = array_merge([$companyId], $requested);
        $products = $this->db->fetchAll(
            'SELECT id, name
             FROM products
             WHERE company_id = ?
               AND is_active = 1
               AND id IN (' . $placeholders . ')',
            $params
        );
        $catalog = [];
        foreach ($products as $product) {
            $catalog[(int) ($product['id'] ?? 0)] = $product;
        }

        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $productId = (int) ($productIds[$i] ?? 0);
            $qty = round((float) ($quantities[$i] ?? 0), 2);
            $unitCost = round((float) ($unitCosts[$i] ?? 0), 2);
            if ($productId <= 0 || $qty <= 0) {
                continue;
            }
            $product = $catalog[$productId] ?? null;
            if (!is_array($product)) {
                throw new \InvalidArgumentException('Un produit selectionne est introuvable.');
            }
            $items[] = [
                'product_id' => $productId,
                'description' => (string) ($product['name'] ?? ''),
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'line_total' => round($qty * $unitCost, 2),
            ];
        }

        return $items;
    }

    private function generateNextOrderNumber(int $companyId): string
    {
        $prefix = 'PO-' . date('Ym') . '-';
        $row = $this->db->fetchOne(
            'SELECT order_number
             FROM purchase_orders
             WHERE company_id = :company_id
               AND order_number LIKE :prefix
             ORDER BY id DESC
             LIMIT 1',
            [
                'company_id' => $companyId,
                'prefix' => $prefix . '%',
            ]
        );

        $next = 1;
        $last = trim((string) ($row['order_number'] ?? ''));
        if ($last !== '' && preg_match('/^' . preg_quote($prefix, '/') . '(\d{4,})$/', $last, $matches) === 1) {
            $next = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function findOrderNumber(int $companyId, int $orderId): string
    {
        $row = $this->db->fetchOne(
            'SELECT order_number
             FROM purchase_orders
             WHERE id = :id AND company_id = :company_id
             LIMIT 1',
            [
                'id' => $orderId,
                'company_id' => $companyId,
            ]
        );
        return (string) ($row['order_number'] ?? '');
    }

    private function normalizeDate(string $date): ?string
    {
        $date = trim($date);
        if ($date === '') {
            return null;
        }
        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if ($parsed === false) {
            return null;
        }
        return $parsed->format('Y-m-d');
    }
}
