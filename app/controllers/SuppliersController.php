<?php

namespace App\Controllers;

use App\Core\ExcelExporter;
use App\Core\RolePermissions;
use App\Core\Session;
use App\Models\FiscalPeriod;
use App\Models\Supplier;

class SuppliersController extends Controller
{
    public function index(): void
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

        $fiscalModel = new FiscalPeriod();
        $currentPeriod = $fiscalModel->getCurrentPeriod($companyId, date('Y-m-d'));
        $periods = $fiscalModel->getByCompany($companyId);
        if ($periods === [] && is_array($currentPeriod)) {
            $periods = [$currentPeriod];
        }

        $selectedPeriodId = (int) ($_GET['period_id'] ?? 0);
        $selectedPeriod = null;
        foreach ($periods as $period) {
            if ((int) ($period['id'] ?? 0) === $selectedPeriodId) {
                $selectedPeriod = $period;
                break;
            }
        }
        if (!is_array($selectedPeriod)) {
            $selectedPeriod = $currentPeriod;
        }
        if (!is_array($selectedPeriod)) {
            $this->redirect('/settings?tab=fiscal&error=fiscal_required');
        }

        $periodStart = $this->normalizeDateInput((string) ($selectedPeriod['start_date'] ?? ''));
        $periodEnd = $this->normalizeDateInput((string) ($selectedPeriod['end_date'] ?? ''));

        $fromDate = $this->normalizeDateInput((string) ($_GET['from_date'] ?? '')) ?? $periodStart ?? date('Y-m-d');
        $toDate = $this->normalizeDateInput((string) ($_GET['to_date'] ?? '')) ?? $periodEnd ?? date('Y-m-d');

        if ($periodStart !== null && $fromDate < $periodStart) {
            $fromDate = $periodStart;
        }
        if ($periodEnd !== null && $toDate > $periodEnd) {
            $toDate = $periodEnd;
        }
        if ($fromDate > $toDate) {
            $swap = $fromDate;
            $fromDate = $toDate;
            $toDate = $swap;
        }

        $regularity = strtolower((string) ($_GET['regularity'] ?? 'all'));
        $allowedRegularity = ['all', 'regular', 'occasional'];
        if (!in_array($regularity, $allowedRegularity, true)) {
            $regularity = 'all';
        }

        $supplierModel = new Supplier();
        $lotRows = $supplierModel->getSupplierLotsByDateRange($companyId, $fromDate, $toDate);
        $regularThreshold = 2;
        $tracking = $this->buildSupplierTracking($lotRows, $regularity, $regularThreshold);

        $this->renderMain('fournisseurs', [
            'title' => 'Fournisseurs',
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'regularity' => $regularity,
            'regularThreshold' => $regularThreshold,
            'tracking' => $tracking,
        ]);
    }

    public function export(): void
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

        $fiscalModel = new FiscalPeriod();
        $currentPeriod = $fiscalModel->getCurrentPeriod($companyId, date('Y-m-d'));
        $periods = $fiscalModel->getByCompany($companyId);
        if ($periods === [] && is_array($currentPeriod)) {
            $periods = [$currentPeriod];
        }

        $selectedPeriodId = (int) ($_GET['period_id'] ?? 0);
        $selectedPeriod = null;
        foreach ($periods as $period) {
            if ((int) ($period['id'] ?? 0) === $selectedPeriodId) {
                $selectedPeriod = $period;
                break;
            }
        }
        if (!is_array($selectedPeriod)) {
            $selectedPeriod = $currentPeriod;
        }
        if (!is_array($selectedPeriod)) {
            $this->redirect('/settings?tab=fiscal&error=fiscal_required');
        }

        $periodStart = $this->normalizeDateInput((string) ($selectedPeriod['start_date'] ?? ''));
        $periodEnd = $this->normalizeDateInput((string) ($selectedPeriod['end_date'] ?? ''));

        $fromDate = $this->normalizeDateInput((string) ($_GET['from_date'] ?? '')) ?? $periodStart ?? date('Y-m-d');
        $toDate = $this->normalizeDateInput((string) ($_GET['to_date'] ?? '')) ?? $periodEnd ?? date('Y-m-d');

        if ($periodStart !== null && $fromDate < $periodStart) {
            $fromDate = $periodStart;
        }
        if ($periodEnd !== null && $toDate > $periodEnd) {
            $toDate = $periodEnd;
        }
        if ($fromDate > $toDate) {
            $swap = $fromDate;
            $fromDate = $toDate;
            $toDate = $swap;
        }

        $regularity = strtolower((string) ($_GET['regularity'] ?? 'all'));
        $allowedRegularity = ['all', 'regular', 'occasional'];
        if (!in_array($regularity, $allowedRegularity, true)) {
            $regularity = 'all';
        }

        $supplierModel = new Supplier();
        $lotRows = $supplierModel->getSupplierLotsByDateRange($companyId, $fromDate, $toDate);
        $regularThreshold = 2;
        $tracking = $this->buildSupplierTracking($lotRows, $regularity, $regularThreshold);

        $headers = [
            'Fournisseur',
            'Lots',
            'Quantite totale',
            'Quantite restante',
            'Valeur achat',
            'Produits',
            'Premier lot',
            'Dernier lot',
            'Regulier',
        ];

        $rows = [];
        foreach (($tracking['suppliers'] ?? []) as $supplier) {
            $rows[] = [
                (string) ($supplier['name'] ?? ''),
                (string) (int) ($supplier['lot_count'] ?? 0),
                number_format((float) ($supplier['total_qty'] ?? 0), 2, '.', ''),
                number_format((float) ($supplier['remaining_qty'] ?? 0), 2, '.', ''),
                number_format((float) ($supplier['total_value'] ?? 0), 2, '.', ''),
                (string) (int) ($supplier['product_count'] ?? 0),
                (string) ($supplier['first_supply'] ?? ''),
                (string) ($supplier['last_supply'] ?? ''),
                !empty($supplier['is_regular']) ? 'Oui' : 'Non',
            ];
        }

        ExcelExporter::download('fournisseurs', $headers, $rows);
    }

    private function buildSupplierTracking(array $rows, string $regularity, int $regularThreshold): array
    {
        $suppliers = [];
        foreach ($rows as $row) {
            $supplierName = trim((string) ($row['supplier'] ?? ''));
            if ($supplierName === '') {
                continue;
            }

            $supplierKey = strtolower(preg_replace('/\s+/', '', $supplierName));
            if (!isset($suppliers[$supplierKey])) {
                $suppliers[$supplierKey] = [
                    'key' => $supplierKey,
                    'name' => $supplierName,
                    'lot_count' => 0,
                    'total_qty' => 0.0,
                    'remaining_qty' => 0.0,
                    'total_value' => 0.0,
                    'product_ids' => [],
                    'first_supply' => null,
                    'last_supply' => null,
                    'lots' => [],
                ];
            }

            $qtyInitial = (float) ($row['quantity_initial_base'] ?? 0);
            $qtyRemaining = (float) ($row['quantity_remaining_base'] ?? 0);
            $unitCost = (float) ($row['unit_cost_base'] ?? 0);
            $openedAt = (string) ($row['opened_at'] ?? '');

            $suppliers[$supplierKey]['lot_count'] += 1;
            $suppliers[$supplierKey]['total_qty'] += $qtyInitial;
            $suppliers[$supplierKey]['remaining_qty'] += $qtyRemaining;
            $suppliers[$supplierKey]['total_value'] += $qtyInitial * $unitCost;
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId > 0) {
                $suppliers[$supplierKey]['product_ids'][$productId] = true;
            }

            if ($openedAt !== '') {
                if ($suppliers[$supplierKey]['first_supply'] === null || $openedAt < $suppliers[$supplierKey]['first_supply']) {
                    $suppliers[$supplierKey]['first_supply'] = $openedAt;
                }
                if ($suppliers[$supplierKey]['last_supply'] === null || $openedAt > $suppliers[$supplierKey]['last_supply']) {
                    $suppliers[$supplierKey]['last_supply'] = $openedAt;
                }
            }

            $suppliers[$supplierKey]['lots'][] = [
                'lot_code' => (string) ($row['lot_code'] ?? ''),
                'product_name' => (string) ($row['product_name'] ?? ''),
                'quantity_initial_base' => $qtyInitial,
                'quantity_remaining_base' => $qtyRemaining,
                'unit_cost_base' => $unitCost,
                'expiration_date' => (string) ($row['expiration_date'] ?? ''),
                'opened_at' => $openedAt,
                'exhausted_at' => (string) ($row['exhausted_at'] ?? ''),
                'is_declassified' => (int) ($row['is_declassified'] ?? 0),
            ];
        }

        $summary = [
            'suppliers' => 0,
            'regular' => 0,
            'lots' => 0,
            'total_qty' => 0.0,
            'remaining_qty' => 0.0,
            'total_value' => 0.0,
        ];

        $result = [];
        foreach ($suppliers as $supplier) {
            $supplier['product_count'] = count($supplier['product_ids']);
            unset($supplier['product_ids']);

            $supplier['is_regular'] = $supplier['lot_count'] >= $regularThreshold;
            if (!$this->supplierMatchesRegularity($supplier, $regularity)) {
                continue;
            }

            usort($supplier['lots'], static function (array $a, array $b): int {
                return strcmp((string) ($b['opened_at'] ?? ''), (string) ($a['opened_at'] ?? ''));
            });
            $supplier['lots_preview'] = array_slice($supplier['lots'], 0, 8);
            unset($supplier['lots']);

            $summary['suppliers'] += 1;
            $summary['lots'] += $supplier['lot_count'];
            $summary['total_qty'] += $supplier['total_qty'];
            $summary['remaining_qty'] += $supplier['remaining_qty'];
            $summary['total_value'] += $supplier['total_value'];
            if ($supplier['is_regular']) {
                $summary['regular'] += 1;
            }

            $result[] = $supplier;
        }

        usort($result, static function (array $a, array $b): int {
            return ($b['lot_count'] <=> $a['lot_count']);
        });

        return [
            'suppliers' => $result,
            'summary' => $summary,
        ];
    }

    private function supplierMatchesRegularity(array $supplier, string $regularity): bool
    {
        if ($regularity === 'regular') {
            return (bool) ($supplier['is_regular'] ?? false);
        }
        if ($regularity === 'occasional') {
            return !(bool) ($supplier['is_regular'] ?? false);
        }
        return true;
    }

    private function normalizeDateInput(string $value): ?string
    {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }
        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return null;
        }
        return date('Y-m-d', $timestamp);
    }
}
