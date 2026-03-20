<?php

namespace App\Controllers;

use App\Core\ExcelExporter;
use App\Core\RolePermissions;
use App\Core\Session;
use App\Models\Dashboard;
use App\Models\FiscalPeriod;
use App\Models\Invoice;

class DashboardController extends Controller
{
    public function index(): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userName = trim((string) ($sessionUser['first_name'] ?? ''));
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));
        $canAccessTransactions = RolePermissions::canAccessTransactions($role);
        $canViewTransactionHistory = RolePermissions::canViewTransactionHistory($role);
        $canManageTransactions = RolePermissions::canManageTransactions($role);
        $canManageInvoices = RolePermissions::canManageInvoices($role);
        $canAccessSettings = RolePermissions::canAccessSettings($role);

        if ($companyId <= 0) {
            $this->redirect('/login');
        }

        $dashboardModel = new Dashboard();
        $forceRefresh = true;
        $payload = $dashboardModel->getDashboardPayloadCached($companyId, 60, $forceRefresh);
        if (!$canViewTransactionHistory) {
            $payload['recentTransactions'] = [];
            $payload['cashReconciliation'] = [];
        }

        $this->renderMain('dashboard', [
            'title' => 'Dashboard',
            'userName' => $userName !== '' ? $userName : 'Utilisateur',
            'currentPeriod' => $payload['currentPeriod'] ?? null,
            'stats' => $payload['stats'],
            'recentTransactions' => $payload['recentTransactions'],
            'insights' => $payload['insights'],
            'alerts' => $payload['alerts'],
            'cashflow' => $payload['cashflow'],
            'expenseBreakdown' => $payload['expenseBreakdown'],
            'cashReconciliation' => $payload['cashReconciliation'] ?? [],
            'canAccessTransactions' => $canAccessTransactions,
            'canViewTransactionHistory' => $canViewTransactionHistory,
            'canManageTransactions' => $canManageTransactions,
            'canManageInvoices' => $canManageInvoices,
            'canAccessSettings' => $canAccessSettings,
            'flashError' => $this->resolveError((string) ($_GET['error'] ?? '')),
        ]);
    }

    public function accounting(): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userName = trim((string) ($sessionUser['first_name'] ?? ''));
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0) {
            $this->redirect('/login');
        }

        if ($role !== RolePermissions::ROLE_ADMIN) {
            $this->redirect('/dashboard?error=admin_required');
        }

        $dashboardModel = new Dashboard();
        $forceRefresh = true;
        $payload = $dashboardModel->getDashboardPayloadCached($companyId, 60, $forceRefresh);

        $this->renderMain('comptabilite', [
            'title' => 'Comptabilité',
            'userName' => $userName !== '' ? $userName : 'Utilisateur',
            'stats' => $payload['stats'],
        ]);
    }

    public function clientTracking(): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0) {
            $this->redirect('/login');
        }

        if (!RolePermissions::canAccessInvoices($role)) {
            $this->redirect('/dashboard?error=clients_forbidden');
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

        $groupBy = strtolower((string) ($_GET['group_by'] ?? 'month'));
        $allowedGroupBy = ['quarter', 'month', 'week', 'day'];
        if (!in_array($groupBy, $allowedGroupBy, true)) {
            $groupBy = 'month';
        }

        $segment = strtolower((string) ($_GET['segment'] ?? 'all'));
        $allowedSegments = ['all', 'regular', 'debt', 'known', 'anonymous'];
        if (!in_array($segment, $allowedSegments, true)) {
            $segment = 'all';
        }

        $fromDate = (string) ($selectedPeriod['start_date'] ?? date('Y-m-d'));
        $toDate = (string) ($selectedPeriod['end_date'] ?? date('Y-m-d'));
        $invoiceModel = new Invoice();
        $invoiceRows = $invoiceModel->getByCompanyDateRange($companyId, $fromDate, $toDate);

        $regularThreshold = 2;
        $tracking = $this->buildClientTracking($invoiceRows, $groupBy, $segment, $regularThreshold);
        $selectedClientName = trim((string) ($_GET['client_name'] ?? ''));
        $selectedClientPhone = trim((string) ($_GET['client_phone'] ?? ''));
        $selectedClientLedger = null;
        if ($selectedClientName !== '' || $selectedClientPhone !== '') {
            $selectedClientLedger = $invoiceModel->getClientLedger($companyId, $selectedClientName, $selectedClientPhone, [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ]);
        }

        $this->renderMain('suivi-clients', [
            'title' => 'Suivi clients',
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'groupBy' => $groupBy,
            'segment' => $segment,
            'tracking' => $tracking,
            'regularThreshold' => $regularThreshold,
            'selectedClientLedger' => $selectedClientLedger,
            'selectedClientName' => $selectedClientName,
            'selectedClientPhone' => $selectedClientPhone,
        ]);
    }

    public function exportClientLedger(): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0) {
            $this->redirect('/login');
        }

        if (!RolePermissions::canAccessInvoices($role)) {
            $this->redirect('/dashboard?error=clients_forbidden');
        }

        $clientName = trim((string) ($_GET['client_name'] ?? ''));
        $clientPhone = trim((string) ($_GET['client_phone'] ?? ''));
        if ($clientName === '' && $clientPhone === '') {
            $this->redirect('/suivi-clients');
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

        $invoiceModel = new Invoice();
        $ledger = $invoiceModel->getClientLedger(
            $companyId,
            $clientName,
            $clientPhone,
            [
                'from_date' => (string) ($selectedPeriod['start_date'] ?? date('Y-m-d')),
                'to_date' => (string) ($selectedPeriod['end_date'] ?? date('Y-m-d')),
            ]
        );

        $client = is_array($ledger['client'] ?? null) ? $ledger['client'] : null;
        $summary = is_array($ledger['summary'] ?? null) ? $ledger['summary'] : [];
        $rows = is_array($ledger['rows'] ?? null) ? $ledger['rows'] : [];
        if ($client === null || $rows === []) {
            $query = http_build_query(array_filter([
                'period_id' => $selectedPeriodId > 0 ? $selectedPeriodId : null,
                'client_name' => $clientName,
                'client_phone' => $clientPhone,
            ], static fn($value) => $value !== null && $value !== ''));
            $this->redirect('/suivi-clients' . ($query !== '' ? '?' . $query : ''));
        }

        $headers = [
            'Client',
            'Telephone',
            'Facture',
            'Date emission',
            'Date echeance',
            'Montant',
            'Paye',
            'Dette',
            'Statut',
            'Notes',
            'Nb factures client',
            'Dette totale client',
            'Client regulier',
        ];

        $dataRows = [];
        foreach ($rows as $row) {
            $dataRows[] = [
                (string) ($client['name'] ?? ''),
                (string) ($client['phone'] ?? ''),
                (string) ($row['invoice_number'] ?? ''),
                (string) ($row['invoice_date'] ?? ''),
                (string) ($row['due_date'] ?? ''),
                number_format((float) ($row['total'] ?? 0), 2, '.', ''),
                number_format((float) ($row['paid_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($row['debt_amount'] ?? 0), 2, '.', ''),
                (string) ($row['status'] ?? ''),
                (string) ($row['notes'] ?? ''),
                (string) (int) ($summary['invoice_count'] ?? 0),
                number_format((float) ($summary['debt'] ?? 0), 2, '.', ''),
                !empty($summary['is_regular']) ? 'Oui' : 'Non',
            ];
        }

        $slug = preg_replace('/[^a-z0-9]+/i', '_', strtolower((string) ($client['name'] ?? 'client'))) ?: 'client';
        ExcelExporter::download('compte_client_' . $slug, $headers, $dataRows);
    }

    private function buildClientTracking(array $rows, string $groupBy, string $segment, int $regularThreshold): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $invoiceDate = (string) ($row['invoice_date'] ?? '');
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $invoiceDate);
            if ($date === false) {
                continue;
            }

            $bucket = $this->resolveClientTrackingBucket($date, $groupBy);
            $bucketKey = (string) ($bucket['key'] ?? $date->format('Y-m-d'));
            if (!isset($groups[$bucketKey])) {
                $groups[$bucketKey] = [
                    'label' => (string) ($bucket['label'] ?? $date->format('d/m/Y')),
                    'period_start' => (string) ($bucket['start'] ?? $date->format('Y-m-d')),
                    'period_end' => (string) ($bucket['end'] ?? $date->format('Y-m-d')),
                    'clients' => [],
                ];
            }

            $rawName = trim((string) ($row['customer_name'] ?? ''));
            $displayName = $rawName !== '' ? $rawName : 'Client';
            $phone = trim((string) ($row['customer_phone'] ?? ''));
            $clientKey = strtolower($displayName . '|' . preg_replace('/[^0-9]+/', '', $phone));
            if (!isset($groups[$bucketKey]['clients'][$clientKey])) {
                $groups[$bucketKey]['clients'][$clientKey] = [
                    'name' => $displayName,
                    'phone' => $phone,
                    'invoice_count' => 0,
                    'total' => 0.0,
                    'paid' => 0.0,
                    'debt' => 0.0,
                    'is_regular' => false,
                    'has_debt' => false,
                    'is_known' => true,
                    'is_anonymous' => false,
                ];
            }

            $total = (float) ($row['total'] ?? 0);
            $paid = (float) ($row['paid_amount'] ?? 0);
            $debt = max($total - $paid, 0);

            $clientRef = &$groups[$bucketKey]['clients'][$clientKey];
            $clientRef['invoice_count'] += 1;
            $clientRef['total'] += $total;
            $clientRef['paid'] += $paid;
            $clientRef['debt'] += $debt;
            if ($clientRef['phone'] === '' && $phone !== '') {
                $clientRef['phone'] = $phone;
            }
            unset($clientRef);
        }

        $summary = [
            'clients' => 0,
            'regular' => 0,
            'debt' => 0,
            'known' => 0,
            'anonymous' => 0,
            'invoices' => 0,
            'total' => 0.0,
            'paid' => 0.0,
            'debt_amount' => 0.0,
        ];

        foreach ($groups as $bucketKey => $group) {
            $clients = [];
            foreach ($group['clients'] as $client) {
                $nameNormalized = strtolower(trim((string) ($client['name'] ?? '')));
                $isAnonymous = $nameNormalized === ''
                    || $nameNormalized === 'client anonyme'
                    || $nameNormalized === 'client'
                    || $nameNormalized === 'client facture'
                    || $nameNormalized === 'anonyme';

                $client['is_anonymous'] = $isAnonymous;
                $client['is_known'] = !$isAnonymous;
                $client['has_debt'] = $client['debt'] > 0.01;
                $client['is_regular'] = $client['invoice_count'] >= $regularThreshold;

                if (!$this->clientMatchesSegment($client, $segment)) {
                    continue;
                }

                $summary['clients'] += 1;
                $summary['invoices'] += $client['invoice_count'];
                $summary['total'] += $client['total'];
                $summary['paid'] += $client['paid'];
                $summary['debt_amount'] += $client['debt'];
                if ($client['is_regular']) {
                    $summary['regular'] += 1;
                }
                if ($client['has_debt']) {
                    $summary['debt'] += 1;
                }
                if ($client['is_known']) {
                    $summary['known'] += 1;
                }
                if ($client['is_anonymous']) {
                    $summary['anonymous'] += 1;
                }

                $clients[] = $client;
            }

            usort($clients, static function (array $a, array $b): int {
                return ($b['total'] <=> $a['total']);
            });

            $group['clients'] = $clients;
            $group['client_count'] = count($clients);
            $group['invoice_count'] = array_sum(array_column($clients, 'invoice_count'));
            $group['total_amount'] = array_sum(array_column($clients, 'total'));
            $group['paid_amount'] = array_sum(array_column($clients, 'paid'));
            $group['debt_amount'] = array_sum(array_column($clients, 'debt'));

            $groups[$bucketKey] = $group;
        }

        $groups = array_values($groups);
        usort($groups, static function (array $a, array $b): int {
            return strcmp((string) ($b['period_start'] ?? ''), (string) ($a['period_start'] ?? ''));
        });

        return [
            'groups' => $groups,
            'summary' => $summary,
        ];
    }

    private function resolveClientTrackingBucket(\DateTimeImmutable $date, string $groupBy): array
    {
        if ($groupBy === 'quarter') {
            $month = (int) $date->format('n');
            $quarter = (int) ceil($month / 3);
            $quarterStartMonth = (($quarter - 1) * 3) + 1;
            $start = new \DateTimeImmutable($date->format('Y') . '-' . sprintf('%02d', $quarterStartMonth) . '-01');
            $end = $start->modify('+2 months')->modify('last day of this month');
            return [
                'key' => $start->format('Y-m-d'),
                'label' => 'T' . $quarter . ' ' . $date->format('Y'),
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ];
        }

        if ($groupBy === 'week') {
            $start = $date->modify('monday this week');
            $end = $start->modify('+6 days');
            $week = $date->format('W');
            $year = $date->format('o');
            return [
                'key' => $start->format('Y-m-d'),
                'label' => 'S' . $week . ' ' . $year,
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ];
        }

        if ($groupBy === 'day') {
            return [
                'key' => $date->format('Y-m-d'),
                'label' => $date->format('d/m/Y'),
                'start' => $date->format('Y-m-d'),
                'end' => $date->format('Y-m-d'),
            ];
        }

        $start = $date->modify('first day of this month');
        $end = $date->modify('last day of this month');
        return [
            'key' => $start->format('Y-m-d'),
            'label' => 'Mois ' . $date->format('m/Y'),
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ];
    }

    private function clientMatchesSegment(array $client, string $segment): bool
    {
        if ($segment === 'regular') {
            return (bool) ($client['is_regular'] ?? false);
        }
        if ($segment === 'debt') {
            return (bool) ($client['has_debt'] ?? false);
        }
        if ($segment === 'known') {
            return (bool) ($client['is_known'] ?? false);
        }
        if ($segment === 'anonymous') {
            return (bool) ($client['is_anonymous'] ?? false);
        }
        return true;
    }

    private function resolveError(string $code): string
    {
        $messages = [
            'transactions_forbidden' => 'Acces refuse: le role magasinier ne peut pas consulter l historique des transactions.',
            'admin_required' => 'Acces reserve aux administrateurs.',
        ];

        return $messages[$code] ?? '';
    }
}
