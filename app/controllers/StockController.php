<?php

namespace App\Controllers;

use App\Core\AuditLogger;
use App\Core\AppLogger;
use App\Core\RolePermissions;
use App\Core\ExcelExporter;
use App\Core\Session;
use App\Models\Company;
use App\Models\Dashboard;
use App\Models\Product;

class StockController extends Controller
{
    private Product $productModel;

    public function __construct()
    {
        $this->productModel = new Product();
    }

    public function index(): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));
        $canManageStock = RolePermissions::canManageStock($role);

        if ($companyId <= 0) {
            $this->redirect('/login');
        }

        $filters = [
            'q' => (string) ($_GET['q'] ?? ''),
            'stock_state' => (string) ($_GET['stock_state'] ?? ''),
            'expiration_date' => (string) ($_GET['expiration_date'] ?? ''),
            'supplier' => (string) ($_GET['supplier'] ?? ''),
        ];

        $editId = (int) ($_GET['edit'] ?? 0);
        $editingProduct = null;
        if ($editId > 0) {
            $editingProduct = $this->productModel->findByIdForCompany($companyId, $editId);
        }

        $this->renderMain('stocks', [
            'title' => 'Stock Produits',
            'filters' => $filters,
            'summary' => $this->productModel->getSummary($companyId),
            'products' => $this->productModel->getByCompany($companyId, $filters),
            'openLots' => $this->productModel->getOpenLotsByCompany($companyId, $filters, 150),
            'lotCatalog' => $this->productModel->getLotCatalog($companyId, 0),
            'recentMovements' => $this->productModel->getRecentMovements($companyId, 10),
            'alerts' => (new Dashboard())->getAlerts($companyId, 8),
            'editingProduct' => $editingProduct,
            'nextSkuPreview' => $this->productModel->previewNextSku($companyId),
            'canManageStock' => $canManageStock,
            'supplierOptions' => $this->productModel->getSuppliersByCompany($companyId),
            'flashSuccess' => $this->resolveSuccess((string) ($_GET['success'] ?? '')),
            'flashError' => $this->resolveError((string) ($_GET['error'] ?? '')),
        ]);
    }

    public function exportLots(): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0) {
            $this->redirect('/login');
        }
        if (!RolePermissions::canAccessStock($role)) {
            $this->redirect('/dashboard?error=stock_forbidden');
        }

        $filters = [
            'q' => (string) ($_GET['q'] ?? ''),
            'stock_state' => (string) ($_GET['stock_state'] ?? ''),
            'expiration_date' => (string) ($_GET['expiration_date'] ?? ''),
            'supplier' => (string) ($_GET['supplier'] ?? ''),
        ];

        $lots = $this->productModel->getOpenLotsByCompany($companyId, $filters, null);

        $headers = [
            'Produit',
            'SKU',
            'N° lot',
            'Fournisseur',
            'Qté initiale',
            'Qté restante',
            'Unite',
            'Cout unitaire',
            'Expiration',
            'Statut',
            'Ouverture',
        ];

        $rows = [];
        foreach ($lots as $lot) {
            $expirationRaw = trim((string) ($lot['expiration_date'] ?? ''));
            $openedAtRaw = trim((string) ($lot['opened_at'] ?? ''));
            $rows[] = [
                (string) ($lot['product_name'] ?? ''),
                (string) ($lot['product_sku'] ?? ''),
                (string) ($lot['lot_code'] ?? ''),
                (string) ($lot['supplier'] ?? ''),
                number_format((float) ($lot['quantity_initial_base'] ?? 0), 2, '.', ''),
                number_format((float) ($lot['quantity_remaining_base'] ?? 0), 2, '.', ''),
                (string) ($lot['base_unit'] ?? ''),
                number_format((float) ($lot['unit_cost_base'] ?? 0), 6, '.', ''),
                $expirationRaw !== '' ? date('d/m/Y', strtotime($expirationRaw)) : '',
                !empty($lot['is_expired']) ? 'Perime' : 'Actif',
                $openedAtRaw !== '' ? date('d/m/Y H:i', strtotime($openedAtRaw)) : '',
            ];
        }

        ExcelExporter::download('lots_stock', $headers, $rows);
    }

    public function store(): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userId = (int) ($sessionUser['id'] ?? 0);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $userId <= 0) {
            $this->redirect('/stock?error=auth_required');
        }
        if (!RolePermissions::canManageStock($role)) {
            $this->redirect('/stock?error=permission_denied');
        }

        try {
            $productId = $this->productModel->createFromPayload($companyId, $userId, $_POST);
            AuditLogger::log($userId, 'product_created', 'products', $productId, null, [
                'name' => (string) ($_POST['name'] ?? ''),
                'sku' => (string) ($_POST['sku'] ?? ''),
            ]);
            $initialQty = (float) ($_POST['quantity'] ?? 0);
            if ($initialQty > 0) {
                $lotId = $this->productModel->getLatestLotIdForProduct($companyId, $productId);
                if ($lotId > 0) {
                    AuditLogger::log($userId, 'stock_lot_added', 'stock_lots', $lotId, null, [
                        'product_id' => $productId,
                        'quantity' => $initialQty,
                        'unit_code' => (string) ($_POST['unit_code'] ?? ($_POST['base_unit_code'] ?? '')),
                        'lot_code' => (string) ($_POST['initial_lot_code'] ?? ''),
                    ]);
                }
            }
            $this->redirect('/stock?success=product_created');
        } catch (\InvalidArgumentException $exception) {
            $this->redirect('/stock?error=invalid_payload');
        } catch (\Throwable $exception) {
            AppLogger::error('Stock product create failed', [
                'company_id' => $companyId,
                'user_id' => $userId,
                'product_name' => (string) ($_POST['name'] ?? ''),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
            $this->redirect('/stock?error=product_create_failed');
        }
    }

    public function update($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userId = (int) ($sessionUser['id'] ?? 0);
        $productId = (int) $id;
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $productId <= 0) {
            $this->redirect('/stock?error=auth_required');
        }
        if (!RolePermissions::canManageStock($role)) {
            $this->redirect('/stock?error=permission_denied');
        }

        try {
            $existingProduct = $this->productModel->findByIdForCompany($companyId, $productId);
            $this->productModel->updateFromPayload($companyId, $productId, $_POST);
            AuditLogger::log($userId, 'product_updated', 'products', $productId, is_array($existingProduct) ? [
                'name' => (string) ($existingProduct['name'] ?? ''),
                'sku' => (string) ($existingProduct['sku'] ?? ''),
                'supplier' => (string) ($existingProduct['supplier'] ?? ''),
                'min_stock' => (float) ($existingProduct['min_stock'] ?? 0),
                'sale_price' => (float) ($existingProduct['sale_price'] ?? 0),
                'expiration_date' => (string) ($existingProduct['expiration_date'] ?? ''),
            ] : null, [
                'name' => (string) ($_POST['name'] ?? ''),
                'sku' => (string) ($_POST['sku'] ?? ''),
                'supplier' => (string) ($_POST['supplier'] ?? ''),
                'min_stock' => (float) ($_POST['min_stock'] ?? 0),
                'sale_price' => (float) ($_POST['sale_price'] ?? 0),
                'expiration_date' => (string) ($_POST['expiration_date'] ?? ''),
            ]);
            $this->redirect('/stock?success=product_updated');
        } catch (\InvalidArgumentException $exception) {
            $this->redirect('/stock?edit=' . $productId . '&error=invalid_payload');
        } catch (\Throwable $exception) {
            $this->redirect('/stock?edit=' . $productId . '&error=product_update_failed');
        }
    }

    public function adjust($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userId = (int) ($sessionUser['id'] ?? 0);
        $productId = (int) $id;
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $userId <= 0 || $productId <= 0) {
            $this->redirect('/stock?error=auth_required');
        }
        if (!RolePermissions::canManageStock($role)) {
            $this->redirect('/stock?error=permission_denied');
        }

        try {
            $this->productModel->adjustStock($companyId, $productId, $userId, $_POST);
            AuditLogger::log($userId, 'stock_adjusted', 'stock_movements', $productId, null, [
                'movement_type' => (string) ($_POST['movement_type'] ?? ''),
                'quantity' => (float) ($_POST['quantity'] ?? 0),
                'unit_code' => (string) ($_POST['unit_code'] ?? ''),
                'reason' => (string) ($_POST['reason'] ?? ''),
                'reference' => (string) ($_POST['reference'] ?? ''),
            ]);
            $this->redirect('/stock?success=stock_adjusted');
        } catch (\InvalidArgumentException $exception) {
            $this->redirect('/stock?error=invalid_stock_adjustment');
        } catch (\Throwable $exception) {
            $this->redirect('/stock?error=stock_adjust_failed');
        }
    }

    public function addLot(): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userId = (int) ($sessionUser['id'] ?? 0);
        $productId = (int) ($_POST['product_id'] ?? 0);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $userId <= 0 || $productId <= 0) {
            $this->redirect('/stock?error=auth_required');
        }
        if (!RolePermissions::canManageStock($role)) {
            $this->redirect('/stock?error=permission_denied');
        }

        try {
            $movementId = $this->productModel->adjustStock($companyId, $productId, $userId, [
                'movement_type' => 'in',
                'quantity' => (string) ($_POST['quantity'] ?? '0'),
                'unit_code' => (string) ($_POST['unit_code'] ?? ''),
                'purchase_unit_cost' => (string) ($_POST['purchase_unit_cost'] ?? '0'),
                'reason' => (string) ($_POST['reason'] ?? 'Ajout lot manuel'),
                'reference' => (string) ($_POST['reference'] ?? ''),
                'source_type' => 'restock_lot',
                'lot_code' => (string) ($_POST['lot_code'] ?? ''),
                'supplier' => (string) ($_POST['supplier'] ?? ''),
                'expiration_date' => (string) ($_POST['expiration_date'] ?? ''),
            ]);
            $lotId = $this->productModel->getLotIdByMovementId($companyId, $movementId);
            if ($lotId <= 0) {
                $lotId = $this->productModel->getLatestLotIdForProduct($companyId, $productId);
            }
            AuditLogger::log($userId, 'stock_lot_added', 'stock_lots', $lotId > 0 ? $lotId : $productId, null, [
                'product_id' => $productId,
                'quantity' => (float) ($_POST['quantity'] ?? 0),
                'unit_code' => (string) ($_POST['unit_code'] ?? ''),
                'lot_code' => (string) ($_POST['lot_code'] ?? ''),
                'supplier' => (string) ($_POST['supplier'] ?? ''),
                'expiration_date' => (string) ($_POST['expiration_date'] ?? ''),
                'purchase_unit_cost' => (float) ($_POST['purchase_unit_cost'] ?? 0),
            ]);
            $this->redirect('/stock?success=lot_added');
        } catch (\InvalidArgumentException $exception) {
            $this->redirect('/stock?error=invalid_lot_add');
        } catch (\Throwable $exception) {
            $this->redirect('/stock?error=lot_add_failed');
        }
    }

    public function delete($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userId = (int) ($sessionUser['id'] ?? 0);
        $productId = (int) $id;
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $userId <= 0 || $productId <= 0) {
            $this->redirect('/stock?error=auth_required');
        }
        if (!RolePermissions::canManageStock($role)) {
            $this->redirect('/stock?error=permission_denied');
        }

        try {
            $product = $this->productModel->findByIdForCompany($companyId, $productId);
            $this->productModel->deactivate($companyId, $productId);
            AuditLogger::log($userId, 'product_deleted', 'products', $productId, is_array($product) ? [
                'name' => (string) ($product['name'] ?? ''),
                'sku' => (string) ($product['sku'] ?? ''),
                'supplier' => (string) ($product['supplier'] ?? ''),
                'quantity' => (float) ($product['quantity'] ?? 0),
                'min_stock' => (float) ($product['min_stock'] ?? 0),
            ] : null, [
                'status' => 'deactivated',
            ]);
            $this->redirect('/stock?success=product_deleted');
        } catch (\InvalidArgumentException $exception) {
            $this->redirect('/stock?error=invalid_product_delete');
        } catch (\Throwable $exception) {
            $this->redirect('/stock?error=product_delete_failed');
        }
    }

    public function deleteBulk(): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userId = (int) ($sessionUser['id'] ?? 0);
        $ids = $_POST['product_ids'] ?? [];
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $userId <= 0) {
            $this->redirect('/stock?error=auth_required');
        }
        if (!RolePermissions::canManageStock($role)) {
            $this->redirect('/stock?error=permission_denied');
        }

        if (!is_array($ids) || $ids === []) {
            $this->redirect('/stock?error=invalid_product_delete');
        }

        $deleted = 0;
        foreach ($ids as $id) {
            $productId = (int) $id;
            if ($productId <= 0) {
                continue;
            }
            try {
                $product = $this->productModel->findByIdForCompany($companyId, $productId);
                if (!is_array($product)) {
                    continue;
                }
                $this->productModel->deactivate($companyId, $productId);
                AuditLogger::log($userId, 'product_deleted', 'products', $productId, null, [
                    'name' => (string) ($product['name'] ?? ''),
                    'sku' => (string) ($product['sku'] ?? ''),
                    'bulk' => true,
                    'supplier' => (string) ($product['supplier'] ?? ''),
                    'quantity' => (float) ($product['quantity'] ?? 0),
                ]);
                $deleted++;
            } catch (\Throwable $exception) {
                continue;
            }
        }

        if ($deleted <= 0) {
            $this->redirect('/stock?error=product_delete_failed');
        }

        $this->redirect('/stock?success=products_deleted_bulk');
    }

    public function deleteLotsBulk(): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userId = (int) ($sessionUser['id'] ?? 0);
        $ids = $_POST['lot_ids'] ?? [];
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $userId <= 0) {
            $this->redirect('/stock?error=auth_required');
        }
        if (!RolePermissions::canManageStock($role)) {
            $this->redirect('/stock?error=permission_denied');
        }

        if (!is_array($ids) || $ids === []) {
            $this->redirect('/stock?error=invalid_lot_delete');
        }

        $deleted = 0;
        foreach ($ids as $id) {
            $lotId = (int) $id;
            if ($lotId <= 0) {
                continue;
            }
            try {
                $this->productModel->deleteLot($companyId, $lotId, $userId);
                AuditLogger::log($userId, 'stock_lot_deleted', 'stock_lots', $lotId, null, [
                    'bulk' => true,
                ]);
                $deleted++;
            } catch (\Throwable $exception) {
                continue;
            }
        }

        if ($deleted <= 0) {
            $this->redirect('/stock?error=lot_delete_failed');
        }

        $this->redirect('/stock?success=lots_deleted_bulk');
    }

    public function updateLot($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userId = (int) ($sessionUser['id'] ?? 0);
        $lotId = (int) $id;
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $userId <= 0 || $lotId <= 0) {
            $this->redirect('/stock?error=auth_required');
        }
        if (!RolePermissions::canManageStock($role)) {
            $this->redirect('/stock?error=permission_denied');
        }

        try {
            $this->productModel->updateLotFromPayload($companyId, $lotId, $_POST, $userId);
            AuditLogger::log($userId, 'stock_lot_updated', 'stock_lots', $lotId, null, [
                'quantity_add' => (float) ($_POST['quantity_add'] ?? 0),
                'unit_code' => (string) ($_POST['unit_code'] ?? ''),
                'action' => 'quantity_added',
            ]);
            $this->redirect('/stock?success=lot_updated');
        } catch (\InvalidArgumentException $exception) {
            $this->redirect('/stock?error=invalid_lot_update');
        } catch (\Throwable $exception) {
            $this->redirect('/stock?error=lot_update_failed');
        }
    }

    public function declassLot($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userId = (int) ($sessionUser['id'] ?? 0);
        $lotId = (int) $id;
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $userId <= 0 || $lotId <= 0) {
            $this->redirect('/stock?error=auth_required');
        }
        if (!RolePermissions::canManageStock($role)) {
            $this->redirect('/stock?error=permission_denied');
        }

        try {
            $this->productModel->declassLot($companyId, $lotId, $userId);
            AuditLogger::log($userId, 'stock_lot_declassified', 'stock_lots', $lotId, null, [
                'action' => 'lot_declassified',
            ]);
            $this->redirect('/stock?success=lot_declassified');
        } catch (\InvalidArgumentException $exception) {
            $this->redirect('/stock?error=invalid_lot_declass');
        } catch (\Throwable $exception) {
            $this->redirect('/stock?error=lot_declass_failed');
        }
    }

    public function deleteLot($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userId = (int) ($sessionUser['id'] ?? 0);
        $lotId = (int) $id;
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $userId <= 0 || $lotId <= 0) {
            $this->redirect('/stock?error=auth_required');
        }
        if (!RolePermissions::canManageStock($role)) {
            $this->redirect('/stock?error=permission_denied');
        }

        try {
            $this->productModel->deleteLot($companyId, $lotId, $userId);
            AuditLogger::log($userId, 'stock_lot_deleted', 'stock_lots', $lotId, null, [
                'action' => 'lot_removed',
            ]);
            $this->redirect('/stock?success=lot_deleted');
        } catch (\InvalidArgumentException $exception) {
            $this->redirect('/stock?error=invalid_lot_delete');
        } catch (\Throwable $exception) {
            $this->redirect('/stock?error=lot_delete_failed');
        }
    }

    public function storePurchaseOrder(): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userId = (int) ($sessionUser['id'] ?? 0);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $userId <= 0) {
            $this->redirect('/stock?error=auth_required');
        }
        if (!RolePermissions::canManageStock($role)) {
            $this->redirect('/stock?error=permission_denied');
        }

        try {
            $orderId = $this->purchaseOrderModel->createManual($companyId, $userId, $_POST);
            AuditLogger::log($userId, 'purchase_order_created', 'purchase_orders', $orderId, null, [
                'supplier_name' => (string) ($_POST['supplier_name'] ?? ''),
            ]);
            $this->redirect('/stock?success=purchase_order_created');
        } catch (\InvalidArgumentException $exception) {
            $this->redirect('/stock?error=invalid_purchase_order');
        } catch (\Throwable $exception) {
            $this->redirect('/stock?error=purchase_order_create_failed');
        }
    }

    public function updatePurchaseOrderStatus($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userId = (int) ($sessionUser['id'] ?? 0);
        $orderId = (int) $id;
        $status = (string) ($_POST['status'] ?? '');
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $userId <= 0 || $orderId <= 0) {
            $this->redirect('/stock?error=auth_required');
        }
        if (!RolePermissions::canManageStock($role)) {
            $this->redirect('/stock?error=permission_denied');
        }

        try {
            $this->purchaseOrderModel->updateStatus($companyId, $orderId, $userId, $status);
            AuditLogger::log($userId, 'purchase_order_status_updated', 'purchase_orders', $orderId, null, [
                'status' => $status,
            ]);
            $this->redirect('/stock?success=purchase_order_updated');
        } catch (\InvalidArgumentException $exception) {
            $this->redirect('/stock?error=invalid_purchase_order_status');
        } catch (\Throwable $exception) {
            $this->redirect('/stock?error=purchase_order_update_failed');
        }
    }

    public function updatePurchaseOrder($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userId = (int) ($sessionUser['id'] ?? 0);
        $orderId = (int) $id;
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $userId <= 0 || $orderId <= 0) {
            $this->redirect('/stock?error=auth_required');
        }
        if (!RolePermissions::canManageStock($role)) {
            $this->redirect('/stock?error=permission_denied');
        }

        try {
            $this->purchaseOrderModel->updateFromPayload($companyId, $orderId, $_POST);
            AuditLogger::log($userId, 'purchase_order_updated', 'purchase_orders', $orderId, null, [
                'supplier_name' => (string) ($_POST['supplier_name'] ?? ''),
            ]);
            $this->redirect('/stock?success=purchase_order_edited');
        } catch (\InvalidArgumentException $exception) {
            $this->redirect('/stock?po_edit=' . $orderId . '&error=invalid_purchase_order_edit');
        } catch (\Throwable $exception) {
            $this->redirect('/stock?po_edit=' . $orderId . '&error=purchase_order_update_failed');
        }
    }

    public function deletePurchaseOrder($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userId = (int) ($sessionUser['id'] ?? 0);
        $orderId = (int) $id;
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $userId <= 0 || $orderId <= 0) {
            $this->redirect('/stock?error=auth_required');
        }
        if (!RolePermissions::canManageStock($role)) {
            $this->redirect('/stock?error=permission_denied');
        }

        try {
            $deleted = $this->purchaseOrderModel->deleteForCompany($companyId, $orderId);
            if (!$deleted) {
                $this->redirect('/stock?error=purchase_order_not_found');
            }
            AuditLogger::log($userId, 'purchase_order_deleted', 'purchase_orders', $orderId);
            $this->redirect('/stock?success=purchase_order_deleted');
        } catch (\InvalidArgumentException $exception) {
            $this->redirect('/stock?error=purchase_order_delete_not_allowed');
        } catch (\Throwable $exception) {
            $this->redirect('/stock?error=purchase_order_delete_failed');
        }
    }

    public function previewPurchaseOrder($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $orderId = (int) $id;

        if ($companyId <= 0 || $orderId <= 0) {
            $this->redirect('/stock?error=purchase_order_not_found');
        }

        $order = $this->purchaseOrderModel->findByIdForCompany($companyId, $orderId);
        if (!is_array($order)) {
            $this->redirect('/stock?error=purchase_order_not_found');
        }

        $this->renderMain('purchase-orders/preview', [
            'title' => 'Apercu BC ' . (string) ($order['order_number'] ?? ''),
            'purchaseOrder' => $order,
            'company' => (new Company())->findById($companyId) ?? [],
            'autoPrint' => ((string) ($_GET['print'] ?? '')) === '1',
        ]);
    }

    private function resolveSuccess(string $code): string
    {
        $messages = [
            'product_created' => 'Lot cree avec succes.',
            'product_updated' => 'Produit mis a jour.',
            'product_deleted' => 'Produit supprime du stock actif.',
            'products_deleted_bulk' => 'Produits selectionnes supprimes du stock actif.',
            'lot_added' => 'Nouveau lot ajoute.',
            'lot_updated' => 'Quantite ajoutee au lot.',
            'lot_deleted' => 'Lot supprime et stock ajuste.',
            'lot_declassified' => 'Lot declasse et retire du stock.',
            'lots_deleted_bulk' => 'Lots selectionnes supprimes et stock ajuste.',
            'stock_adjusted' => 'Stock mis a jour.',
            'purchase_order_created' => 'Bon de commande cree.',
            'purchase_order_updated' => 'Statut du bon de commande mis a jour.',
            'purchase_order_edited' => 'Bon de commande modifie.',
            'purchase_order_deleted' => 'Bon de commande supprime.',
        ];

        return $messages[$code] ?? '';
    }

    private function resolveError(string $code): string
    {
        $messages = [
            'auth_required' => 'Session invalide. Reconnectez-vous.',
            'invalid_payload' => 'Veuillez verifier les informations du produit.',
            'permission_denied' => 'Action refusee: acces en ecriture au stock non autorise.',
            'product_create_failed' => 'Impossible de creer le produit pour le moment.',
            'product_update_failed' => 'Impossible de modifier le produit pour le moment.',
            'invalid_product_delete' => 'Produit introuvable ou suppression invalide.',
            'product_delete_failed' => 'Impossible de supprimer le produit pour le moment.',
            'invalid_lot_add' => 'Ajout de lot invalide. Verifiez produit, unite, quantite et numero de lot.',
            'lot_add_failed' => 'Impossible d ajouter le lot pour le moment.',
            'invalid_lot_update' => 'Ajout invalide: seules des quantites positives sont autorisees sur un lot existant.',
            'lot_update_failed' => 'Impossible d ajouter la quantite au lot pour le moment.',
            'invalid_lot_delete' => 'Suppression du lot invalide.',
            'lot_delete_failed' => 'Impossible de supprimer le lot pour le moment.',
            'invalid_lot_declass' => 'Declassement du lot invalide.',
            'lot_declass_failed' => 'Impossible de declasser le lot pour le moment.',
            'invalid_stock_adjustment' => 'Ajustement de stock invalide (quantite insuffisante ou champs invalides).',
            'stock_adjust_failed' => 'Impossible de mettre a jour le stock pour le moment.',
            'invalid_purchase_order' => 'Bon de commande invalide. Verifiez fournisseur et lignes.',
            'invalid_purchase_order_edit' => 'Modification de bon de commande invalide.',
            'purchase_order_create_failed' => 'Impossible de creer le bon de commande.',
            'invalid_purchase_order_status' => 'Statut de bon de commande invalide.',
            'purchase_order_update_failed' => 'Impossible de mettre a jour le bon de commande.',
            'purchase_order_not_found' => 'Bon de commande introuvable.',
            'purchase_order_delete_not_allowed' => 'Suppression refusee: bon deja receptionne.',
            'purchase_order_delete_failed' => 'Impossible de supprimer le bon de commande.',
        ];

        return $messages[$code] ?? '';
    }
}
