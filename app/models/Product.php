<?php

namespace App\Models;

class Product extends Model
{
    public function getSuppliersByCompany(int $companyId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT supplier
             FROM products
             WHERE company_id = :company_id
               AND is_active = 1
               AND supplier IS NOT NULL
               AND TRIM(supplier) <> \'\'
             ORDER BY supplier ASC',
            ['company_id' => $companyId]
        );

        $suppliers = [];
        foreach ($rows as $row) {
            $value = trim((string) ($row['supplier'] ?? ''));
            if ($value === '') {
                continue;
            }
            $suppliers[] = $value;
        }

        return $suppliers;
    }
    public function getByCompany(int $companyId, array $filters = []): array
    {
        $params = ['company_id' => $companyId];
        $where = ['p.company_id = :company_id', 'p.is_active = 1'];

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(p.name LIKE :search OR COALESCE(p.sku, "") LIKE :search OR COALESCE(p.brand, "") LIKE :search OR COALESCE(p.dosage, "") LIKE :search OR COALESCE(p.forme, "") LIKE :search OR COALESCE(p.presentation, "") LIKE :search)';
            $params['search'] = '%' . $query . '%';
        }

        $supplierFilter = trim((string) ($filters['supplier'] ?? ''));
        if ($supplierFilter !== '') {
            $where[] = 'COALESCE(p.supplier, "") LIKE :supplier_search';
            $params['supplier_search'] = '%' . $supplierFilter . '%';
        }

        $stockState = trim((string) ($filters['stock_state'] ?? ''));
        if ($stockState === 'low') {
            $where[] = 'p.quantity <= p.min_stock';
        } elseif ($stockState === 'out') {
            $where[] = 'p.quantity <= 0';
        }

        $expirationFilter = $this->normalizeDate((string) ($filters['expiration_date'] ?? ''));
        if ($expirationFilter !== null) {
            $where[] = '(
                (p.expiration_date IS NOT NULL AND p.expiration_date <> \'\' AND p.expiration_date <= :exp_date)
                OR EXISTS (
                    SELECT 1
                    FROM stock_lots l
                    WHERE l.product_id = p.id
                      AND l.company_id = :company_id
                      AND l.quantity_remaining_base > 0
                      AND COALESCE(l.is_declassified, 0) = 0
                      AND l.expiration_date IS NOT NULL
                      AND l.expiration_date <> \'\'
                      AND l.expiration_date <= :exp_date
                )
            )';
            $params['exp_date'] = $expirationFilter;
        }

        $products = $this->db->fetchAll(
            'SELECT p.id,
                    p.name,
                    p.sku,
                    p.brand,
                    p.supplier,
                    p.dosage,
                    p.forme,
                    p.presentation,
                    p.color_hex,
                    p.unit,
                    p.quantity,
                    p.min_stock,
                    p.purchase_price,
                    p.sale_price,
                    p.expiration_date,
                    p.updated_at
             FROM products p
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY p.name ASC, p.id DESC',
            $params
        );

        if ($products === []) {
            return [];
        }

        $this->hydrateUnitOptions($companyId, $products);
        $this->hydrateLotsSummary($companyId, $products);

        return $products;
    }

    public function getSummary(int $companyId): array
    {
        $today = date('Y-m-d');
        $row = $this->db->fetchOne(
            'SELECT
                COUNT(*) AS product_count,
                COALESCE(SUM(quantity), 0) AS total_quantity,
                COALESCE(SUM(quantity * purchase_price), 0) AS stock_value,
                COALESCE(SUM(CASE WHEN quantity <= min_stock THEN 1 ELSE 0 END), 0) AS low_stock_count,
                COALESCE((
                    SELECT COUNT(1)
                    FROM stock_lots l
                    INNER JOIN products p2 ON p2.id = l.product_id
                    WHERE l.company_id = :company_id
                      AND p2.company_id = :company_id
                      AND p2.is_active = 1
                      AND l.quantity_remaining_base > 0
                      AND COALESCE(l.is_declassified, 0) = 0
                      AND l.expiration_date IS NOT NULL
                      AND l.expiration_date <> \'\'
                      AND l.expiration_date <= :today
                ), 0) AS expired_lot_count,
                COALESCE((
                    SELECT SUM(l.quantity_remaining_base)
                    FROM stock_lots l
                    INNER JOIN products p2 ON p2.id = l.product_id
                    WHERE l.company_id = :company_id
                      AND p2.company_id = :company_id
                      AND p2.is_active = 1
                      AND l.quantity_remaining_base > 0
                      AND COALESCE(l.is_declassified, 0) = 0
                      AND l.expiration_date IS NOT NULL
                      AND l.expiration_date <> \'\'
                      AND l.expiration_date <= :today
                ), 0) AS expired_quantity,
                COALESCE((
                    SELECT SUM(l.quantity_remaining_base * l.unit_cost_base)
                    FROM stock_lots l
                    INNER JOIN products p2 ON p2.id = l.product_id
                    WHERE l.company_id = :company_id
                      AND p2.company_id = :company_id
                      AND p2.is_active = 1
                      AND l.quantity_remaining_base > 0
                      AND COALESCE(l.is_declassified, 0) = 0
                      AND l.expiration_date IS NOT NULL
                      AND l.expiration_date <> \'\'
                      AND l.expiration_date <= :today
                ), 0) AS expired_stock_value
             FROM products
             WHERE company_id = :company_id
               AND is_active = 1',
            [
                'company_id' => $companyId,
                'today' => $today,
            ]
        );

        return [
            'product_count' => (int) ($row['product_count'] ?? 0),
            'total_quantity' => round((float) ($row['total_quantity'] ?? 0), 2),
            'stock_value' => round((float) ($row['stock_value'] ?? 0), 2),
            'low_stock_count' => (int) ($row['low_stock_count'] ?? 0),
            'expired_lot_count' => (int) ($row['expired_lot_count'] ?? 0),
            'expired_quantity' => round((float) ($row['expired_quantity'] ?? 0), 2),
            'expired_stock_value' => round((float) ($row['expired_stock_value'] ?? 0), 2),
        ];
    }

    public function findByIdForCompany(int $companyId, int $productId): ?array
    {
        $product = $this->db->fetchOne(
            'SELECT id, company_id, name, sku, brand, supplier, dosage, forme, presentation, color_hex, unit, quantity, min_stock, purchase_price, sale_price, expiration_date, is_active, updated_at
             FROM products
             WHERE company_id = :company_id
               AND id = :id
               AND is_active = 1
             LIMIT 1',
            [
                'company_id' => $companyId,
                'id' => $productId,
            ]
        );

        if ($product === null) {
            return null;
        }

        $this->ensureBaseUnitConversion(
            $companyId,
            $productId,
            (string) ($product['unit'] ?? 'unite')
        );
        $product['unit_options'] = $this->getUnitOptionsForProduct($companyId, $productId);
        $product['lots'] = $this->getOpenLots($companyId, $productId, 30, true);

        return $product;
    }

    public function getInvoiceOptions(int $companyId): array
    {
        $today = date('Y-m-d');
        $products = $this->db->fetchAll(
            'SELECT p.id,
                    p.name,
                    p.sku,
                    p.brand,
                    p.supplier,
                    p.dosage,
                    p.forme,
                    p.presentation,
                    p.color_hex,
                    p.unit,
                    p.quantity,
                    p.min_stock,
                    p.purchase_price,
                    p.sale_price,
                    (
                        SELECT l.expiration_date
                        FROM stock_lots l
                        WHERE l.company_id = :company_id
                          AND l.product_id = p.id
                          AND l.quantity_remaining_base > 0
                          AND COALESCE(l.is_declassified, 0) = 0
                          AND (l.expiration_date IS NULL OR l.expiration_date = \'\' OR l.expiration_date > :today)
                        ORDER BY (l.expiration_date IS NULL OR l.expiration_date = \'\') ASC,
                                 l.expiration_date ASC,
                                 l.quantity_remaining_base ASC,
                                 l.opened_at ASC,
                                 l.id ASC
                        LIMIT 1
                    ) AS priority_expiration_date,
                    (
                        SELECT l.quantity_remaining_base
                        FROM stock_lots l
                        WHERE l.company_id = :company_id
                          AND l.product_id = p.id
                          AND l.quantity_remaining_base > 0
                          AND COALESCE(l.is_declassified, 0) = 0
                          AND (l.expiration_date IS NULL OR l.expiration_date = \'\' OR l.expiration_date > :today)
                        ORDER BY (l.expiration_date IS NULL OR l.expiration_date = \'\') ASC,
                                 l.expiration_date ASC,
                                 l.quantity_remaining_base ASC,
                                 l.opened_at ASC,
                                 l.id ASC
                        LIMIT 1
                    ) AS priority_quantity,
                    (
                        SELECT COALESCE(SUM(l.quantity_remaining_base), 0)
                        FROM stock_lots l
                        WHERE l.company_id = :company_id
                          AND l.product_id = p.id
                          AND l.quantity_remaining_base > 0
                          AND COALESCE(l.is_declassified, 0) = 0
                          AND (l.expiration_date IS NULL OR l.expiration_date = \'\' OR l.expiration_date > :today)
                    ) AS lots_available,
                    (
                        SELECT COUNT(1)
                        FROM stock_lots l
                        WHERE l.company_id = :company_id
                          AND l.product_id = p.id
                          AND COALESCE(l.is_declassified, 0) = 0
                    ) AS lot_count
             FROM products p
             WHERE p.company_id = :company_id
               AND p.is_active = 1
             ORDER BY CASE WHEN p.quantity <= p.min_stock THEN 0 ELSE 1 END ASC,
                      (priority_expiration_date IS NULL OR priority_expiration_date = \'\') ASC,
                      priority_expiration_date ASC,
                      priority_quantity ASC,
                      p.name ASC,
                      p.id DESC',
            [
                'company_id' => $companyId,
                'today' => $today,
            ]
        );

        if ($products === []) {
            return [];
        }

        $filtered = [];
        foreach ($products as $product) {
            $lotCount = (int) ($product['lot_count'] ?? 0);
            $availableLots = round((float) ($product['lots_available'] ?? 0), 6);
            $available = $lotCount > 0 ? $availableLots : round((float) ($product['quantity'] ?? 0), 6);
            if ($available <= 0) {
                continue;
            }
            $product['quantity'] = $available;
            $filtered[] = $product;
        }

        if ($filtered === []) {
            return [];
        }

        $this->hydrateUnitOptions($companyId, $filtered);

        return $filtered;
    }

    public function createFromPayload(int $companyId, int $userId, array $payload): int
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $brand = trim((string) ($payload['brand'] ?? ''));
        $supplier = trim((string) ($payload['supplier'] ?? ''));
        $dosage = trim((string) ($payload['dosage'] ?? ''));
        $forme = trim((string) ($payload['forme'] ?? ''));
        $presentation = trim((string) ($payload['presentation'] ?? ''));
        $colorHex = trim((string) ($payload['color_hex'] ?? ''));
        $baseUnit = $this->resolveBaseUnitFromPayload($payload);
        $quantity = round((float) ($payload['quantity'] ?? 0), 2);
        $minStock = max(0, round((float) ($payload['min_stock'] ?? 0), 2));
        $purchasePrice = max(0, round((float) ($payload['purchase_price'] ?? 0), 2));
        $salePrice = max(0, round((float) ($payload['sale_price'] ?? 0), 2));
        $expirationDate = $this->normalizeDate((string) ($payload['expiration_date'] ?? ''));

        $name = $this->buildProductDisplayName($name, $supplier);

        if ($name === '' || $quantity <= 0) {
            throw new \InvalidArgumentException('Produit invalide.');
        }
        if ($salePrice < $purchasePrice) {
            throw new \InvalidArgumentException('Le prix de vente ne peut pas etre inferieur au prix d achat.');
        }

        $sku = $this->generateNextSku($companyId, $name);
        $colorHex = $this->normalizeColorHex($colorHex);

        $this->ensureSkuAvailable($companyId, $sku, null);

        $pdo = $this->db->getConnection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $this->db->execute(
                'INSERT INTO products (company_id, name, sku, brand, supplier, dosage, forme, presentation, color_hex, unit, quantity, min_stock, purchase_price, sale_price, expiration_date, created_by)
                 VALUES (:company_id, :name, :sku, :brand, :supplier, :dosage, :forme, :presentation, :color_hex, :unit, :quantity, :min_stock, :purchase_price, :sale_price, :expiration_date, :created_by)',
                [
                    'company_id' => $companyId,
                    'name' => $name,
                    'sku' => $sku,
                    'brand' => $brand !== '' ? substr($brand, 0, 120) : null,
                    'supplier' => $this->normalizeShortText($supplier, 160),
                    'dosage' => $this->normalizeShortText($dosage, 80),
                    'forme' => $this->normalizeShortText($forme, 80),
                    'presentation' => $this->normalizeShortText($presentation, 160),
                    'color_hex' => $colorHex,
                    'unit' => $baseUnit,
                    'quantity' => $quantity,
                    'min_stock' => $minStock,
                    'purchase_price' => $purchasePrice,
                    'sale_price' => $salePrice,
                    'expiration_date' => $expirationDate,
                    'created_by' => $userId > 0 ? $userId : null,
                ]
            );

            $productId = $this->db->lastInsertId();
            $this->ensureBaseUnitConversion($companyId, $productId, $baseUnit);
            $this->upsertOptionalPackagingFromPayload($companyId, $productId, $payload, $baseUnit);

            if ($quantity > 0) {
                $movementId = $this->appendMovement(
                    $companyId,
                    $productId,
                    'in',
                    $quantity,
                    0.0,
                    $quantity,
                    'Stock initial',
                    $userId,
                    null
                );
                $this->createLotForInflow(
                    $companyId,
                    $productId,
                    $quantity,
                    'initial_stock',
                    (string) ($payload['initial_lot_code'] ?? ''),
                    $userId,
                    $movementId,
                    null,
                    $purchasePrice,
                    $expirationDate,
                    $supplier
                );
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $this->db->rollback();
            }
            throw $exception;
        }

        return $productId;
    }

    public function updateFromPayload(int $companyId, int $productId, array $payload): bool
    {
        $product = $this->findByIdForCompany($companyId, $productId);
        if ($product === null) {
            throw new \InvalidArgumentException('Produit introuvable.');
        }

        $name = trim((string) ($payload['name'] ?? ''));
        $brand = trim((string) ($payload['brand'] ?? ''));
        $supplier = trim((string) ($payload['supplier'] ?? ''));
        $dosage = trim((string) ($payload['dosage'] ?? ''));
        $forme = trim((string) ($payload['forme'] ?? ''));
        $presentation = trim((string) ($payload['presentation'] ?? ''));
        $colorHex = trim((string) ($payload['color_hex'] ?? ''));
        $baseUnit = $this->resolveBaseUnitFromPayload($payload);
        $minStock = max(0, round((float) ($payload['min_stock'] ?? 0), 2));
        $purchasePrice = max(0, round((float) ($payload['purchase_price'] ?? 0), 2));
        $salePrice = max(0, round((float) ($payload['sale_price'] ?? 0), 2));
        $expirationDate = $this->normalizeDate((string) ($payload['expiration_date'] ?? ''));

        $name = $this->buildProductDisplayName($name, $supplier);

        if ($name === '') {
            throw new \InvalidArgumentException('Produit invalide.');
        }
        if ($salePrice < $purchasePrice) {
            throw new \InvalidArgumentException('Le prix de vente ne peut pas etre inferieur au prix d achat.');
        }

        $sku = trim((string) ($product['sku'] ?? ''));
        if ($sku === '') {
            $sku = $this->generateNextSku($companyId, $name);
        }
        $colorHex = $this->normalizeColorHex($colorHex);
        $this->ensureSkuAvailable($companyId, $sku, $productId);

        $pdo = $this->db->getConnection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $this->db->execute(
                'UPDATE products
                 SET name = :name,
                     sku = :sku,
                     brand = :brand,
                     supplier = :supplier,
                     dosage = :dosage,
                     forme = :forme,
                     presentation = :presentation,
                     color_hex = :color_hex,
                     unit = :unit,
                     min_stock = :min_stock,
                     purchase_price = :purchase_price,
                     sale_price = :sale_price,
                     expiration_date = :expiration_date,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE company_id = :company_id
                   AND id = :id',
                [
                    'name' => $name,
                    'sku' => $sku,
                    'brand' => $brand !== '' ? substr($brand, 0, 120) : null,
                    'supplier' => $this->normalizeShortText($supplier, 160),
                    'dosage' => $this->normalizeShortText($dosage, 80),
                    'forme' => $this->normalizeShortText($forme, 80),
                    'presentation' => $this->normalizeShortText($presentation, 160),
                    'color_hex' => $colorHex,
                    'unit' => $baseUnit,
                    'min_stock' => $minStock,
                    'purchase_price' => $purchasePrice,
                    'sale_price' => $salePrice,
                    'expiration_date' => $expirationDate,
                    'company_id' => $companyId,
                    'id' => $productId,
                ]
            );

            $this->ensureBaseUnitConversion($companyId, $productId, $baseUnit);
            $this->upsertOptionalPackagingFromPayload($companyId, $productId, $payload, $baseUnit);

            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $this->db->rollback();
            }
            throw $exception;
        }

        return true;
    }

    public function previewNextSku(int $companyId, string $productName = ''): string
    {
        return $this->generateNextSku($companyId, $productName);
    }

    public function deactivate(int $companyId, int $productId): bool
    {
        $product = $this->findByIdForCompany($companyId, $productId);
        if ($product === null) {
            throw new \InvalidArgumentException('Produit introuvable.');
        }

        $this->db->execute(
            'UPDATE products
             SET is_active = 0,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND company_id = :company_id',
            [
                'id' => $productId,
                'company_id' => $companyId,
            ]
        );

        return true;
    }

    public function adjustStock(int $companyId, int $productId, int $userId, array $payload): int
    {
        $product = $this->findByIdForCompany($companyId, $productId);
        if ($product === null) {
            throw new \InvalidArgumentException('Produit introuvable.');
        }

        $type = strtolower(trim((string) ($payload['movement_type'] ?? 'in')));
        if (!in_array($type, ['in', 'out', 'adjustment'], true)) {
            throw new \InvalidArgumentException('Mouvement invalide.');
        }

        $inputQuantity = round((float) ($payload['quantity'] ?? 0), 6);
        $unitCode = $this->normalizeUnitCode((string) ($payload['unit_code'] ?? ''));
        if ($unitCode === '') {
            $unitCode = $this->normalizeUnitCode((string) ($product['unit'] ?? 'unite'));
        }
        if ($unitCode === '') {
            $unitCode = 'unite';
        }

        $factorToBase = $this->findUnitFactor($companyId, $productId, $unitCode);
        if ($factorToBase <= 0) {
            throw new \InvalidArgumentException('Unite invalide pour ce produit.');
        }

        $reason = trim((string) ($payload['reason'] ?? ''));
        $reference = trim((string) ($payload['reference'] ?? ''));
        $sourceType = trim((string) ($payload['source_type'] ?? 'manual'));
        if ($sourceType === '') {
            $sourceType = 'manual';
        }
        $purchaseUnitCost = round((float) ($payload['purchase_unit_cost'] ?? 0), 6);
        $expirationDate = $this->normalizeDate((string) ($payload['expiration_date'] ?? ''));
        $supplier = trim((string) ($payload['supplier'] ?? ''));

        if ($inputQuantity <= 0 && $type !== 'adjustment') {
            throw new \InvalidArgumentException('Quantite invalide.');
        }
        if ($type === 'adjustment' && $inputQuantity < 0) {
            throw new \InvalidArgumentException('Quantite invalide.');
        }

        $before = round((float) ($product['quantity'] ?? 0), 6);
        $convertedQty = round($inputQuantity * $factorToBase, 6);
        $delta = 0.0;

        if ($type === 'in') {
            $delta = $convertedQty;
        } elseif ($type === 'out') {
            $delta = -$convertedQty;
        } else {
            $targetBase = $convertedQty;
            $delta = round($targetBase - $before, 6);
        }

        $after = round($before + $delta, 6);
        if ($after < 0) {
            throw new \InvalidArgumentException('Stock insuffisant.');
        }

        $displayReason = $reason;
        if ($displayReason === '') {
            $displayReason = 'Ajustement automatique';
        }
        if ($inputQuantity > 0) {
            $displayReason .= ' [' . $this->trimNumeric($inputQuantity) . ' ' . $unitCode . ']';
        }

        $lotCode = trim((string) ($payload['lot_code'] ?? ''));

        $pdo = $this->db->getConnection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $this->db->execute(
                'UPDATE products
                 SET quantity = :quantity,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id
                   AND company_id = :company_id',
                [
                    'quantity' => $after,
                    'id' => $productId,
                    'company_id' => $companyId,
                ]
            );

            $movementId = $this->appendMovement($companyId, $productId, $type, $delta, $before, $after, $displayReason, $userId, $reference);

            if ($delta > 0) {
                $unitCostBase = $factorToBase > 0 ? round($purchaseUnitCost / $factorToBase, 6) : 0.0;
                $this->createLotForInflow(
                    $companyId,
                    $productId,
                    $delta,
                    $sourceType,
                    $lotCode,
                    $userId,
                    $movementId,
                    $reference,
                    $unitCostBase,
                    $expirationDate,
                    $supplier
                );
            } elseif ($delta < 0) {
                $this->allocateOutflowToLots($companyId, $productId, abs($delta), $movementId, $userId);
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $this->db->rollback();
            }
            throw $exception;
        }

        return $movementId;
    }

    public function getRecentMovements(int $companyId, int $limit = 12): array
    {
        return $this->db->fetchAll(
            'SELECT m.id,
                    m.product_id,
                    p.name AS product_name,
                    m.movement_type,
                    m.quantity_change,
                    m.quantity_before,
                    m.quantity_after,
                    m.reason,
                    m.reference,
                    m.created_at
             FROM stock_movements m
             INNER JOIN products p ON p.id = m.product_id
             WHERE m.company_id = :company_id
             ORDER BY m.created_at DESC, m.id DESC
             LIMIT ' . (int) max(1, min(100, $limit)),
            ['company_id' => $companyId]
        );
    }

    public function getOpenLotsByCompany(int $companyId, array $filters = [], ?int $limit = 120): array
    {
        $params = ['company_id' => $companyId];
        $where = ['l.company_id = :company_id', 'l.quantity_remaining_base > 0', 'COALESCE(l.is_declassified, 0) = 0'];

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(p.name LIKE :search
                OR COALESCE(p.sku, "") LIKE :search
                OR COALESCE(p.brand, "") LIKE :search
                OR COALESCE(p.dosage, "") LIKE :search
                OR COALESCE(p.forme, "") LIKE :search
                OR COALESCE(p.presentation, "") LIKE :search
                OR COALESCE(l.lot_code, "") LIKE :search
                OR COALESCE(l.supplier, "") LIKE :search)';
            $params['search'] = '%' . $query . '%';
        }

        $supplierFilter = trim((string) ($filters['supplier'] ?? ''));
        if ($supplierFilter !== '') {
            $where[] = '(COALESCE(l.supplier, "") LIKE :supplier_search OR COALESCE(p.supplier, "") LIKE :supplier_search)';
            $params['supplier_search'] = '%' . $supplierFilter . '%';
        }

        $stockState = trim((string) ($filters['stock_state'] ?? ''));
        if ($stockState === 'low') {
            $where[] = 'p.quantity <= p.min_stock';
        } elseif ($stockState === 'out') {
            $where[] = 'p.quantity <= 0';
        }

        $expirationFilter = $this->normalizeDate((string) ($filters['expiration_date'] ?? ''));
        if ($expirationFilter !== null) {
            $where[] = '(
                (l.expiration_date IS NOT NULL AND l.expiration_date <> \'\' AND l.expiration_date <= :exp_date)
                OR (p.expiration_date IS NOT NULL AND p.expiration_date <> \'\' AND p.expiration_date <= :exp_date)
            )';
            $params['exp_date'] = $expirationFilter;
        }

        $sql = 'SELECT l.id,
                       l.product_id,
                       p.name AS product_name,
                       p.sku AS product_sku,
                       p.unit AS base_unit,
                       l.lot_code,
                       l.supplier,
                       l.source_type,
                       l.source_reference,
                       l.quantity_initial_base,
                       l.quantity_remaining_base,
                       l.unit_cost_base,
                       l.expiration_date,
                       l.opened_at,
                       l.exhausted_at
                FROM stock_lots l
                INNER JOIN products p ON p.id = l.product_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY l.opened_at DESC, l.id DESC';

        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) max(1, min(5000, $limit));
        }

        $lots = $this->db->fetchAll($sql, $params);
        $today = date('Y-m-d');
        foreach ($lots as &$lot) {
            $lot['is_expired'] = $this->isLotExpired((string) ($lot['expiration_date'] ?? ''), $today);
        }
        unset($lot);

        return $lots;
    }

    public function getLotCatalog(int $companyId, int $limit = 2000): array
    {
        if ($companyId <= 0) {
            return [];
        }

        $sql = 'SELECT l.id,
                       l.product_id,
                       p.name AS product_name,
                       p.unit AS base_unit,
                       l.lot_code,
                       l.supplier,
                       l.quantity_initial_base,
                       l.unit_cost_base,
                       l.expiration_date,
                       COALESCE(l.is_declassified, 0) AS is_declassified
                FROM stock_lots l
                INNER JOIN products p ON p.id = l.product_id
                WHERE l.company_id = :company_id
                  AND p.company_id = :company_id
                  AND p.is_active = 1
                ORDER BY l.id DESC';

        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) max(1, min(5000, $limit));
        }

        return $this->db->fetchAll($sql, ['company_id' => $companyId]);
    }

    public function updateLotFromPayload(int $companyId, int $lotId, array $payload, int $userId = 0): bool
    {
        $lot = $this->findLotByIdForCompany($companyId, $lotId);
        if ($lot === null) {
            throw new \InvalidArgumentException('Lot introuvable.');
        }

        foreach (['lot_code', 'supplier', 'unit_cost_base', 'expiration_date'] as $field) {
            if (array_key_exists($field, $payload) && trim((string) ($payload[$field] ?? '')) !== '') {
                throw new \InvalidArgumentException('Modification des proprietes du lot non autorisee.');
            }
        }

        $quantityAdd = round((float) ($payload['quantity_add'] ?? 0), 6);
        $unitCode = $this->normalizeUnitCode((string) ($payload['unit_code'] ?? ''));
        if ($quantityAdd <= 0) {
            throw new \InvalidArgumentException('Quantite invalide.');
        }

        $productId = (int) ($lot['product_id'] ?? 0);
        $product = $this->db->fetchOne(
            'SELECT id, unit, quantity
             FROM products
             WHERE id = :id
               AND company_id = :company_id
             LIMIT 1',
            [
                'id' => $productId,
                'company_id' => $companyId,
            ]
        );
        if (!is_array($product)) {
            throw new \InvalidArgumentException('Produit introuvable.');
        }

        if ($unitCode === '') {
            $unitCode = $this->normalizeUnitCode((string) ($product['unit'] ?? 'unite'));
        }
        if ($unitCode === '') {
            $unitCode = 'unite';
        }

        if ($this->isLotExpired((string) ($lot['expiration_date'] ?? ''), date('Y-m-d'))) {
            throw new \InvalidArgumentException('Lot perime. Ajout impossible.');
        }

        $factorToBase = $this->findUnitFactor($companyId, $productId, $unitCode);
        if ($factorToBase <= 0) {
            throw new \InvalidArgumentException('Unite invalide pour ce produit.');
        }

        $addedBase = round($quantityAdd * $factorToBase, 6);
        if ($addedBase <= 0) {
            throw new \InvalidArgumentException('Quantite invalide.');
        }

        $before = round((float) ($product['quantity'] ?? 0), 6);
        $after = round($before + $addedBase, 6);
        $lotCode = (string) ($lot['lot_code'] ?? '');
        $reason = 'Ajout quantite lot ' . $lotCode . ' [' . $this->trimNumeric($quantityAdd) . ' ' . $unitCode . ']';

        $pdo = $this->db->getConnection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $this->db->execute(
                'UPDATE products
                 SET quantity = :quantity,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id
                   AND company_id = :company_id',
                [
                    'quantity' => $after,
                    'id' => $productId,
                    'company_id' => $companyId,
                ]
            );

            $movementId = $this->appendMovement(
                $companyId,
                $productId,
                'in',
                $addedBase,
                $before,
                $after,
                $reason,
                $userId,
                $lotCode
            );

            $this->db->execute(
                'UPDATE stock_lots
                 SET quantity_initial_base = quantity_initial_base + :added_base,
                     quantity_remaining_base = quantity_remaining_base + :added_base,
                     exhausted_at = NULL,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id
                   AND company_id = :company_id',
                [
                    'added_base' => $addedBase,
                    'id' => $lotId,
                    'company_id' => $companyId,
                ]
            );

            $this->db->execute(
                'INSERT INTO stock_lot_allocations (stock_movement_id, lot_id, quantity_base)
                 VALUES (:stock_movement_id, :lot_id, :quantity_base)',
                [
                    'stock_movement_id' => $movementId,
                    'lot_id' => $lotId,
                    'quantity_base' => $addedBase,
                ]
            );

            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $this->db->rollback();
            }
            throw $exception;
        }

        return true;
    }

    public function getLotIdByMovementId(int $companyId, int $movementId): int
    {
        if ($movementId <= 0) {
            return 0;
        }

        $row = $this->db->fetchOne(
            'SELECT l.id
             FROM stock_lot_allocations a
             INNER JOIN stock_lots l ON l.id = a.lot_id
             WHERE a.stock_movement_id = :movement_id
               AND l.company_id = :company_id
             ORDER BY l.id DESC
             LIMIT 1',
            [
                'movement_id' => $movementId,
                'company_id' => $companyId,
            ]
        );

        return (int) ($row['id'] ?? 0);
    }

    public function getLatestLotIdForProduct(int $companyId, int $productId): int
    {
        if ($companyId <= 0 || $productId <= 0) {
            return 0;
        }

        $row = $this->db->fetchOne(
            'SELECT id
             FROM stock_lots
             WHERE company_id = :company_id
               AND product_id = :product_id
             ORDER BY id DESC
             LIMIT 1',
            [
                'company_id' => $companyId,
                'product_id' => $productId,
            ]
        );

        return (int) ($row['id'] ?? 0);
    }

    public function declassLot(int $companyId, int $lotId, int $userId): bool
    {
        $lot = $this->findLotByIdForCompany($companyId, $lotId);
        if ($lot === null) {
            throw new \InvalidArgumentException('Lot introuvable.');
        }
        if ((int) ($lot['is_declassified'] ?? 0) === 1) {
            return true;
        }

        $productId = (int) ($lot['product_id'] ?? 0);
        $remaining = round((float) ($lot['quantity_remaining_base'] ?? 0), 6);
        if ($productId <= 0) {
            throw new \InvalidArgumentException('Lot invalide.');
        }

        $product = $this->db->fetchOne(
            'SELECT id, quantity
             FROM products
             WHERE company_id = :company_id
               AND id = :id
             LIMIT 1',
            [
                'company_id' => $companyId,
                'id' => $productId,
            ]
        );
        if ($product === null) {
            throw new \InvalidArgumentException('Produit introuvable.');
        }

        $before = round((float) ($product['quantity'] ?? 0), 6);
        $effectiveOut = $remaining > 0 ? min($before, $remaining) : 0.0;
        $after = max(0.0, round($before - $remaining, 6));

        $reason = 'Declassement lot ' . (string) ($lot['lot_code'] ?? '');

        $pdo = $this->db->getConnection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            if ($before !== $after) {
                $this->db->execute(
                    'UPDATE products
                     SET quantity = :quantity,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id
                       AND company_id = :company_id',
                    [
                        'quantity' => $after,
                        'id' => $productId,
                        'company_id' => $companyId,
                    ]
                );
            }

            $movementId = 0;
            if ($effectiveOut > 0) {
                $movementId = $this->appendMovement(
                    $companyId,
                    $productId,
                    'out',
                    round(-$effectiveOut, 6),
                    $before,
                    $after,
                    $reason,
                    $userId,
                    (string) ($lot['lot_code'] ?? '')
                );
            }

            $this->db->execute(
                'UPDATE stock_lots
                 SET is_declassified = 1,
                     declassified_at = :declassified_at,
                     quantity_remaining_base = 0,
                     exhausted_at = :exhausted_at,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id
                   AND company_id = :company_id',
                [
                    'declassified_at' => date('Y-m-d H:i:s'),
                    'exhausted_at' => date('Y-m-d H:i:s'),
                    'id' => $lotId,
                    'company_id' => $companyId,
                ]
            );

            if ($movementId > 0 && $effectiveOut > 0) {
                $this->db->execute(
                    'INSERT INTO stock_lot_allocations (stock_movement_id, lot_id, quantity_base)
                     VALUES (:stock_movement_id, :lot_id, :quantity_base)',
                    [
                        'stock_movement_id' => $movementId,
                        'lot_id' => $lotId,
                        'quantity_base' => round(-$effectiveOut, 6),
                    ]
                );
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $this->db->rollback();
            }
            throw $exception;
        }

        return true;
    }

    public function deleteLot(int $companyId, int $lotId, int $userId): bool
    {
        $lot = $this->findLotByIdForCompany($companyId, $lotId);
        if ($lot === null) {
            throw new \InvalidArgumentException('Lot introuvable.');
        }

        $productId = (int) ($lot['product_id'] ?? 0);
        $remaining = round((float) ($lot['quantity_remaining_base'] ?? 0), 6);
        if ($productId <= 0) {
            throw new \InvalidArgumentException('Lot invalide.');
        }
        if ($remaining <= 0) {
            return true;
        }

        $product = $this->db->fetchOne(
            'SELECT id, quantity
             FROM products
             WHERE company_id = :company_id
               AND id = :id
             LIMIT 1',
            [
                'company_id' => $companyId,
                'id' => $productId,
            ]
        );
        if ($product === null) {
            throw new \InvalidArgumentException('Produit introuvable.');
        }

        $before = round((float) ($product['quantity'] ?? 0), 6);
        $effectiveOut = min($before, $remaining);
        $after = max(0.0, round($before - $remaining, 6));

        $reason = 'Suppression lot ' . (string) ($lot['lot_code'] ?? '');

        $pdo = $this->db->getConnection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $this->db->execute(
                'UPDATE products
                 SET quantity = :quantity,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id
                   AND company_id = :company_id',
                [
                    'quantity' => $after,
                    'id' => $productId,
                    'company_id' => $companyId,
                ]
            );

            $movementId = 0;
            if ($effectiveOut > 0) {
                $movementId = $this->appendMovement(
                    $companyId,
                    $productId,
                    'out',
                    round(-$effectiveOut, 6),
                    $before,
                    $after,
                    $reason,
                    $userId,
                    (string) ($lot['lot_code'] ?? '')
                );
            }

            $this->db->execute(
                'UPDATE stock_lots
                 SET quantity_remaining_base = 0,
                     exhausted_at = :exhausted_at,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id
                   AND company_id = :company_id',
                [
                    'exhausted_at' => date('Y-m-d H:i:s'),
                    'id' => $lotId,
                    'company_id' => $companyId,
                ]
            );

            if ($movementId > 0 && $effectiveOut > 0) {
                $this->db->execute(
                    'INSERT INTO stock_lot_allocations (stock_movement_id, lot_id, quantity_base)
                     VALUES (:stock_movement_id, :lot_id, :quantity_base)',
                    [
                        'stock_movement_id' => $movementId,
                        'lot_id' => $lotId,
                        'quantity_base' => round(-$effectiveOut, 6),
                    ]
                );
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $this->db->rollback();
            }
            throw $exception;
        }

        return true;
    }

    private function hydrateUnitOptions(int $companyId, array &$products): void
    {
        $productIds = [];
        foreach ($products as $row) {
            $productId = (int) ($row['id'] ?? 0);
            if ($productId > 0) {
                $productIds[] = $productId;
                $this->ensureBaseUnitConversion(
                    $companyId,
                    $productId,
                    (string) ($row['unit'] ?? 'unite')
                );
            }
        }

        $productIds = array_values(array_unique($productIds));
        if ($productIds === []) {
            return;
        }

        $placeholders = [];
        $params = ['company_id' => $companyId];
        foreach ($productIds as $idx => $productId) {
            $key = 'pid_' . $idx;
            $placeholders[] = ':' . $key;
            $params[$key] = $productId;
        }

        $rows = $this->db->fetchAll(
            'SELECT product_id, unit_code, unit_label, factor_to_base, is_base
             FROM product_unit_conversions
             WHERE company_id = :company_id
               AND is_active = 1
               AND product_id IN (' . implode(', ', $placeholders) . ')
             ORDER BY product_id ASC, is_base DESC, factor_to_base ASC, unit_code ASC',
            $params
        );

        $byProduct = [];
        foreach ($rows as $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            $byProduct[$pid][] = [
                'unit_code' => (string) ($row['unit_code'] ?? ''),
                'unit_label' => (string) ($row['unit_label'] ?? ''),
                'factor_to_base' => round((float) ($row['factor_to_base'] ?? 1), 6),
                'is_base' => (int) ($row['is_base'] ?? 0) === 1,
            ];
        }

        foreach ($products as &$product) {
            $pid = (int) ($product['id'] ?? 0);
            $product['unit_options'] = $byProduct[$pid] ?? [[
                'unit_code' => (string) ($product['unit'] ?? 'unite'),
                'unit_label' => (string) ($product['unit'] ?? 'unite'),
                'factor_to_base' => 1.0,
                'is_base' => true,
            ]];
        }
        unset($product);
    }

    private function hydrateLotsSummary(int $companyId, array &$products): void
    {
        $productIds = [];
        foreach ($products as $row) {
            $productId = (int) ($row['id'] ?? 0);
            if ($productId > 0) {
                $productIds[] = $productId;
            }
        }
        $productIds = array_values(array_unique($productIds));
        if ($productIds === []) {
            return;
        }

        $productIdListSql = implode(', ', array_map('intval', $productIds));
        $params = ['company_id' => $companyId];

        $today = date('Y-m-d');
        $params['today'] = $today;
        $rows = $this->db->fetchAll(
            'SELECT product_id,
                    COUNT(*) AS open_lots,
                    COALESCE(SUM(quantity_remaining_base), 0) AS lots_qty
             FROM stock_lots
             WHERE company_id = :company_id
               AND quantity_remaining_base > 0
               AND COALESCE(is_declassified, 0) = 0
               AND (expiration_date IS NULL OR expiration_date = \'\' OR expiration_date > :today)
               AND product_id IN (' . $productIdListSql . ')
             GROUP BY product_id',
            $params
        );

        $firstLotRows = $this->db->fetchAll(
            'SELECT l.product_id, l.lot_code
             FROM stock_lots l
             INNER JOIN (
                 SELECT product_id, MIN(id) AS min_id
                 FROM stock_lots
                 WHERE company_id = :company_id
                   AND COALESCE(is_declassified, 0) = 0
                   AND product_id IN (' . $productIdListSql . ')
                 GROUP BY product_id
             ) x ON x.product_id = l.product_id AND x.min_id = l.id',
            ['company_id' => $companyId]
        );

        $summary = [];
        foreach ($rows as $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            $summary[$pid] = [
                'open_lots' => (int) ($row['open_lots'] ?? 0),
                'lots_qty' => round((float) ($row['lots_qty'] ?? 0), 6),
            ];
        }

        $firstLotByProduct = [];
        foreach ($firstLotRows as $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            $firstLotByProduct[$pid] = (string) ($row['lot_code'] ?? '');
        }

        foreach ($products as &$product) {
            $pid = (int) ($product['id'] ?? 0);
            $lotData = $summary[$pid] ?? ['open_lots' => 0, 'lots_qty' => 0.0];
            $product['open_lots'] = $lotData['open_lots'];
            $product['lots_qty'] = $lotData['lots_qty'];
            $product['initial_lot_code'] = $firstLotByProduct[$pid] ?? '';
        }
        unset($product);
    }

    private function getUnitOptionsForProduct(int $companyId, int $productId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT unit_code, unit_label, factor_to_base, is_base
             FROM product_unit_conversions
             WHERE company_id = :company_id
               AND product_id = :product_id
               AND is_active = 1
             ORDER BY is_base DESC, factor_to_base ASC, unit_code ASC',
            [
                'company_id' => $companyId,
                'product_id' => $productId,
            ]
        );

        $options = [];
        foreach ($rows as $row) {
            $options[] = [
                'unit_code' => (string) ($row['unit_code'] ?? ''),
                'unit_label' => (string) ($row['unit_label'] ?? ''),
                'factor_to_base' => round((float) ($row['factor_to_base'] ?? 1), 6),
                'is_base' => (int) ($row['is_base'] ?? 0) === 1,
            ];
        }

        return $options;
    }

    private function getOpenLots(int $companyId, int $productId, int $limit = 30, bool $includeExpired = false): array
    {
        $params = [
            'company_id' => $companyId,
            'product_id' => $productId,
        ];
        $where = [
            'company_id = :company_id',
            'product_id = :product_id',
            'quantity_remaining_base > 0',
            'COALESCE(is_declassified, 0) = 0',
        ];

        if (!$includeExpired) {
            $params['today'] = date('Y-m-d');
            $where[] = '(expiration_date IS NULL OR expiration_date = \'\' OR expiration_date > :today)';
        }

        $rows = $this->db->fetchAll(
            'SELECT id, lot_code, supplier, source_type, source_reference, quantity_initial_base, quantity_remaining_base, expiration_date, opened_at
             FROM stock_lots
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY (expiration_date IS NULL OR expiration_date = \'\') ASC,
                      expiration_date ASC,
                      quantity_remaining_base ASC,
                      opened_at ASC,
                      id ASC
             LIMIT ' . (int) max(1, min(100, $limit)),
            $params
        );

        $today = date('Y-m-d');
        foreach ($rows as &$row) {
            $row['is_expired'] = $this->isLotExpired((string) ($row['expiration_date'] ?? ''), $today);
        }
        unset($row);

        return $rows;
    }

    private function isLotExpired(string $expirationDate, ?string $today = null): bool
    {
        $exp = trim($expirationDate);
        if ($exp === '') {
            return false;
        }
        $todayValue = $today ?? date('Y-m-d');
        $expTs = strtotime($exp);
        $todayTs = strtotime($todayValue);
        if ($expTs === false || $todayTs === false) {
            return false;
        }
        return $expTs <= $todayTs;
    }

    private function buildProductDisplayName(string $name, string $supplier): string
    {
        $baseName = trim($name);
        $supplierName = trim($supplier);
        if ($baseName === '' || $supplierName === '') {
            return $baseName;
        }
        if (stripos($baseName, $supplierName) !== false) {
            return $baseName;
        }
        return trim($baseName . ' - ' . $supplierName);
    }

    private function findLotByIdForCompany(int $companyId, int $lotId): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, product_id, company_id, lot_code, supplier, quantity_initial_base, quantity_remaining_base, unit_cost_base, expiration_date, is_declassified, declassified_at
             FROM stock_lots
             WHERE id = :id
               AND company_id = :company_id
             LIMIT 1',
            [
                'id' => $lotId,
                'company_id' => $companyId,
            ]
        );
    }

    private function upsertOptionalPackagingFromPayload(int $companyId, int $productId, array $payload, string $baseUnit): void
    {
        $packagingCode = $this->normalizeUnitCode((string) ($payload['packaging_unit_code'] ?? ''));
        $packagingLabel = trim((string) ($payload['packaging_unit_label'] ?? ''));
        $packagingFactor = round((float) ($payload['packaging_factor'] ?? 0), 6);

        if ($packagingCode === '' || $packagingFactor <= 0 || $packagingCode === $baseUnit) {
            return;
        }

        if ($packagingLabel === '') {
            $packagingLabel = $packagingCode;
        }

        $existing = $this->db->fetchOne(
            'SELECT id
             FROM product_unit_conversions
             WHERE company_id = :company_id
               AND product_id = :product_id
               AND unit_code = :unit_code
             LIMIT 1',
            [
                'company_id' => $companyId,
                'product_id' => $productId,
                'unit_code' => $packagingCode,
            ]
        );

        if ($existing !== null) {
            $this->db->execute(
                'UPDATE product_unit_conversions
                 SET unit_label = :unit_label,
                     factor_to_base = :factor_to_base,
                     is_active = 1,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                [
                    'unit_label' => substr($packagingLabel, 0, 80),
                    'factor_to_base' => $packagingFactor,
                    'id' => (int) ($existing['id'] ?? 0),
                ]
            );
            return;
        }

        $this->db->execute(
            'INSERT INTO product_unit_conversions (product_id, company_id, unit_code, unit_label, factor_to_base, is_base, is_active)
             VALUES (:product_id, :company_id, :unit_code, :unit_label, :factor_to_base, 0, 1)',
            [
                'product_id' => $productId,
                'company_id' => $companyId,
                'unit_code' => $packagingCode,
                'unit_label' => substr($packagingLabel, 0, 80),
                'factor_to_base' => $packagingFactor,
            ]
        );
    }

    private function resolveBaseUnitFromPayload(array $payload): string
    {
        $baseUnit = $this->normalizeUnitCode((string) ($payload['base_unit_code'] ?? ''));
        if ($baseUnit === '') {
            $baseUnit = $this->normalizeUnitCode((string) ($payload['unit'] ?? ''));
        }
        if ($baseUnit === '') {
            $baseUnit = 'unite';
        }

        return $baseUnit;
    }

    private function findUnitFactor(int $companyId, int $productId, string $unitCode): float
    {
        $row = $this->db->fetchOne(
            'SELECT factor_to_base
             FROM product_unit_conversions
             WHERE company_id = :company_id
               AND product_id = :product_id
               AND unit_code = :unit_code
               AND is_active = 1
             LIMIT 1',
            [
                'company_id' => $companyId,
                'product_id' => $productId,
                'unit_code' => $unitCode,
            ]
        );

        if ($row === null) {
            return 0.0;
        }

        return round((float) ($row['factor_to_base'] ?? 0), 6);
    }

    private function ensureBaseUnitConversion(int $companyId, int $productId, string $baseUnit): void
    {
        $baseUnit = $this->normalizeUnitCode($baseUnit);
        if ($baseUnit === '') {
            $baseUnit = 'unite';
        }

        $this->db->execute(
            'UPDATE product_unit_conversions
             SET is_base = 0,
                 updated_at = CURRENT_TIMESTAMP
             WHERE company_id = :company_id
               AND product_id = :product_id
               AND unit_code <> :unit_code',
            [
                'company_id' => $companyId,
                'product_id' => $productId,
                'unit_code' => $baseUnit,
            ]
        );

        $existing = $this->db->fetchOne(
            'SELECT id
             FROM product_unit_conversions
             WHERE company_id = :company_id
               AND product_id = :product_id
               AND unit_code = :unit_code
             LIMIT 1',
            [
                'company_id' => $companyId,
                'product_id' => $productId,
                'unit_code' => $baseUnit,
            ]
        );

        if ($existing !== null) {
            $this->db->execute(
                'UPDATE product_unit_conversions
                 SET unit_label = :unit_label,
                     factor_to_base = 1,
                     is_base = 1,
                     is_active = 1,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                [
                    'unit_label' => substr($baseUnit, 0, 80),
                    'id' => (int) ($existing['id'] ?? 0),
                ]
            );
            return;
        }

        $this->db->execute(
            'INSERT INTO product_unit_conversions (product_id, company_id, unit_code, unit_label, factor_to_base, is_base, is_active)
             VALUES (:product_id, :company_id, :unit_code, :unit_label, 1, 1, 1)',
            [
                'product_id' => $productId,
                'company_id' => $companyId,
                'unit_code' => $baseUnit,
                'unit_label' => substr($baseUnit, 0, 80),
            ]
        );
    }

    private function createLotForInflow(
        int $companyId,
        int $productId,
        float $quantityBase,
        string $sourceType,
        string $lotCode,
        int $userId,
        int $movementId,
        ?string $sourceReference = null,
        float $unitCostBase = 0.0,
        ?string $expirationDate = null,
        ?string $supplier = null
    ): void {
        $quantityBase = round($quantityBase, 6);
        if ($quantityBase <= 0) {
            return;
        }

        $lotCode = trim($lotCode);
        if ($lotCode === '') {
            $requiresManual = in_array($sourceType, ['initial_stock', 'restock_lot', 'manual'], true);
            if ($requiresManual) {
                throw new \InvalidArgumentException('Code lot obligatoire.');
            }
            $lotCode = $this->generateLotCode($productId, $sourceType);
        }

        $normalizedSupplier = $this->normalizeShortText(trim((string) ($supplier ?? '')), 160);
        $this->db->execute(
            'INSERT INTO stock_lots (product_id, company_id, lot_code, supplier, source_type, source_reference, quantity_initial_base, quantity_remaining_base, unit_cost_base, expiration_date, created_by)
             VALUES (:product_id, :company_id, :lot_code, :supplier, :source_type, :source_reference, :quantity_initial_base, :quantity_remaining_base, :unit_cost_base, :expiration_date, :created_by)',
            [
                'product_id' => $productId,
                'company_id' => $companyId,
                'lot_code' => substr($lotCode, 0, 120),
                'supplier' => $normalizedSupplier,
                'source_type' => substr($sourceType !== '' ? $sourceType : 'manual', 0, 40),
                'source_reference' => $sourceReference !== null && trim($sourceReference) !== '' ? substr(trim($sourceReference), 0, 120) : null,
                'quantity_initial_base' => $quantityBase,
                'quantity_remaining_base' => $quantityBase,
                'unit_cost_base' => max(0, round($unitCostBase, 6)),
                'expiration_date' => $expirationDate,
                'created_by' => $userId > 0 ? $userId : null,
            ]
        );

        $lotId = $this->db->lastInsertId();
        $this->db->execute(
            'INSERT INTO stock_lot_allocations (stock_movement_id, lot_id, quantity_base)
             VALUES (:stock_movement_id, :lot_id, :quantity_base)',
            [
                'stock_movement_id' => $movementId,
                'lot_id' => $lotId,
                'quantity_base' => $quantityBase,
            ]
        );
    }

    private function allocateOutflowToLots(int $companyId, int $productId, float $requiredQtyBase, int $movementId, int $userId): void
    {
        $requiredQtyBase = round($requiredQtyBase, 6);
        if ($requiredQtyBase <= 0) {
            return;
        }

        $openLots = $this->getOpenLots($companyId, $productId, 500);

        if ($openLots === []) {
            $existingLot = $this->db->fetchOne(
                'SELECT id
                 FROM stock_lots
                 WHERE company_id = :company_id
                   AND product_id = :product_id
                 LIMIT 1',
                [
                    'company_id' => $companyId,
                    'product_id' => $productId,
                ]
            );
            if ($existingLot !== null) {
                throw new \InvalidArgumentException('Lots perimes ou indisponibles pour ce produit.');
            }
            // Legacy fallback: synthesize one lot from historical stock if no lot exists yet.
            $product = $this->db->fetchOne(
                'SELECT quantity
                 FROM products
                 WHERE id = :id
                   AND company_id = :company_id
                 LIMIT 1',
                [
                    'id' => $productId,
                    'company_id' => $companyId,
                ]
            );

            $remaining = round((float) ($product['quantity'] ?? 0), 6);
            if ($remaining > 0) {
                $this->db->execute(
                    'INSERT INTO stock_lots (product_id, company_id, lot_code, source_type, source_reference, quantity_initial_base, quantity_remaining_base, created_by)
                     VALUES (:product_id, :company_id, :lot_code, :source_type, :source_reference, :quantity_initial_base, :quantity_remaining_base, :created_by)',
                    [
                        'product_id' => $productId,
                        'company_id' => $companyId,
                        'lot_code' => $this->generateLotCode($productId, 'legacy'),
                        'source_type' => 'legacy',
                        'source_reference' => 'Bootstrap lot legacy',
                        'quantity_initial_base' => $remaining,
                        'quantity_remaining_base' => $remaining,
                        'created_by' => $userId > 0 ? $userId : null,
                    ]
                );
                $openLots = $this->getOpenLots($companyId, $productId, 500);
            }
        }

        $remainingToAllocate = $requiredQtyBase;
        foreach ($openLots as $lot) {
            if ($remainingToAllocate <= 0) {
                break;
            }

            $lotId = (int) ($lot['id'] ?? 0);
            $lotRemaining = round((float) ($lot['quantity_remaining_base'] ?? 0), 6);
            if ($lotId <= 0 || $lotRemaining <= 0) {
                continue;
            }

            $used = min($lotRemaining, $remainingToAllocate);
            $newRemaining = round($lotRemaining - $used, 6);
            $exhaustedAt = $newRemaining <= 0 ? date('Y-m-d H:i:s') : null;

            $this->db->execute(
                'UPDATE stock_lots
                 SET quantity_remaining_base = :quantity_remaining_base,
                     exhausted_at = :exhausted_at,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                [
                    'quantity_remaining_base' => $newRemaining,
                    'exhausted_at' => $exhaustedAt,
                    'id' => $lotId,
                ]
            );

            $this->db->execute(
                'INSERT INTO stock_lot_allocations (stock_movement_id, lot_id, quantity_base)
                 VALUES (:stock_movement_id, :lot_id, :quantity_base)',
                [
                    'stock_movement_id' => $movementId,
                    'lot_id' => $lotId,
                    'quantity_base' => round(-$used, 6),
                ]
            );

            $remainingToAllocate = round($remainingToAllocate - $used, 6);
        }

        if ($remainingToAllocate > 0) {
            throw new \InvalidArgumentException('Stock insuffisant dans les lots.');
        }
    }

    private function ensureSkuAvailable(int $companyId, string $sku, ?int $exceptProductId): void
    {
        if ($sku === '') {
            return;
        }

        $params = [
            'company_id' => $companyId,
            'sku' => $sku,
        ];
        $sql = 'SELECT id
                FROM products
                WHERE company_id = :company_id
                  AND sku = :sku
                  AND is_active = 1';
        if ($exceptProductId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptProductId;
        }
        $sql .= ' LIMIT 1';

        $duplicate = $this->db->fetchOne($sql, $params);
        if ($duplicate !== null) {
            throw new \InvalidArgumentException('SKU deja utilise.');
        }
    }

    private function appendMovement(
        int $companyId,
        int $productId,
        string $type,
        float $change,
        float $before,
        float $after,
        string $reason,
        int $userId,
        ?string $reference
    ): int {
        $this->db->execute(
            'INSERT INTO stock_movements (product_id, company_id, movement_type, quantity_change, quantity_before, quantity_after, reason, reference, created_by)
             VALUES (:product_id, :company_id, :movement_type, :quantity_change, :quantity_before, :quantity_after, :reason, :reference, :created_by)',
            [
                'product_id' => $productId,
                'company_id' => $companyId,
                'movement_type' => $type,
                'quantity_change' => round($change, 6),
                'quantity_before' => round($before, 6),
                'quantity_after' => round($after, 6),
                'reason' => $reason !== '' ? substr($reason, 0, 255) : null,
                'reference' => ($reference ?? '') !== '' ? substr((string) $reference, 0, 120) : null,
                'created_by' => $userId > 0 ? $userId : null,
            ]
        );

        return $this->db->lastInsertId();
    }

    private function normalizeColorHex(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1) {
            return strtoupper($value);
        }

        return null;
    }

    private function normalizeShortText(string $value, int $maxLength): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return substr($value, 0, max(1, $maxLength));
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $date = date_create($value);
        if ($date === false) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private function generateNextSku(int $companyId, string $name): string
    {
        $slug = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', $name));
        if ($slug === '') {
            $slug = 'PRD';
        }
        $prefix = substr($slug, 0, 4);
        if (strlen($prefix) < 3) {
            $prefix = str_pad($prefix, 3, 'X');
        }

        $base = $prefix . '-' . date('ym') . '-';
        $row = $this->db->fetchOne(
            'SELECT sku
             FROM products
             WHERE company_id = :company_id
               AND sku LIKE :sku_like
             ORDER BY id DESC
             LIMIT 1',
            [
                'company_id' => $companyId,
                'sku_like' => $base . '%',
            ]
        );

        $next = 1;
        $lastSku = trim((string) ($row['sku'] ?? ''));
        if ($lastSku !== '' && preg_match('/^' . preg_quote($base, '/') . '(\d{4,})$/', $lastSku, $matches) === 1) {
            $next = ((int) $matches[1]) + 1;
        }

        return $base . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function normalizeUnitCode(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        $value = (string) preg_replace('/[^a-z0-9_\-]/', '', $value);
        if ($value === '') {
            return '';
        }

        return substr($value, 0, 30);
    }

    private function trimNumeric(float $value): string
    {
        $formatted = number_format($value, 6, '.', '');
        $formatted = rtrim($formatted, '0');
        return rtrim($formatted, '.');
    }

    private function generateLotCode(int $productId, string $sourceType): string
    {
        $prefix = strtoupper(substr((string) preg_replace('/[^A-Za-z0-9]+/', '', $sourceType), 0, 4));
        if ($prefix === '') {
            $prefix = 'LOT';
        }

        return sprintf('%s-P%d-%s', $prefix, $productId, date('ymdHis'));
    }
}
