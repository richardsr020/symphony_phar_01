<?php

namespace App\Controllers;

use App\Core\RolePermissions;
use App\Core\Session;
use App\Models\Dashboard;
use App\Models\Invoice;
use App\Services\Ai\ChatAgent;

class ApiController extends Controller
{
    public function dashboard(): void
    {
        $companyId = $this->resolveCompanyId();
        if ($companyId <= 0) {
            $this->json(['error' => 'Authentification requise'], 401);
            return;
        }

        $dashboard = new Dashboard();
        $forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';
        $payload = $dashboard->getDashboardPayloadCached($companyId, 60, $forceRefresh, [
            'cashflow_period' => (string) ($_GET['cashflow_period'] ?? '30d'),
            'expenses_period' => (string) ($_GET['expenses_period'] ?? 'month'),
        ]);
        $sessionUser = Session::get('user', []);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));
        if (!RolePermissions::canViewTransactionHistory($role)) {
            $payload['recentTransactions'] = [];
            $payload['cashReconciliation'] = [];
        }

        $this->json($payload);
    }

    public function chat(): void
    {
        if (!defined('Config::AI_ENABLED') || \Config::AI_ENABLED !== true) {
            $this->json(['error' => 'Fonctionnalite IA desactivee.'], 410);
            return;
        }

        $companyId = $this->resolveCompanyId();
        $userId = $this->resolveUserId();
        if ($companyId <= 0 || $userId <= 0) {
            $this->json(['error' => 'Authentification requise'], 401);
            return;
        }

        $sessionUser = Session::get('user', []);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));
        if (!RolePermissions::canAccessChat($role)) {
            $this->json(['error' => 'Acces refuse'], 403);
            return;
        }

        $raw = file_get_contents('php://input');
        $payload = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($payload)) {
            $payload = $_POST ?? [];
        }

        try {
            $agent = new ChatAgent();
            $response = $agent->handleMessage($companyId, $userId, $payload);
            $this->json($response);
        } catch (\InvalidArgumentException $exception) {
            $this->json(['error' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            $this->json(['error' => 'Erreur IA: ' . $exception->getMessage()], 500);
        }
    }

    public function chatConversations(): void
    {
        if (!defined('Config::AI_ENABLED') || \Config::AI_ENABLED !== true) {
            $this->json(['error' => 'Fonctionnalite IA desactivee.'], 410);
            return;
        }

        $companyId = $this->resolveCompanyId();
        $userId = $this->resolveUserId();
        if ($companyId <= 0 || $userId <= 0) {
            $this->json(['error' => 'Authentification requise'], 401);
            return;
        }

        $sessionUser = Session::get('user', []);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));
        if (!RolePermissions::canAccessChat($role)) {
            $this->json(['error' => 'Acces refuse'], 403);
            return;
        }

        try {
            $agent = new ChatAgent();
            $this->json([
                'conversations' => $agent->listConversations($companyId, $userId),
            ]);
        } catch (\Throwable $exception) {
            $this->json(['error' => 'Erreur IA: ' . $exception->getMessage()], 500);
        }
    }

    public function chatHistory($id): void
    {
        if (!defined('Config::AI_ENABLED') || \Config::AI_ENABLED !== true) {
            $this->json(['error' => 'Fonctionnalite IA desactivee.'], 410);
            return;
        }

        $companyId = $this->resolveCompanyId();
        $userId = $this->resolveUserId();
        if ($companyId <= 0 || $userId <= 0) {
            $this->json(['error' => 'Authentification requise'], 401);
            return;
        }

        $sessionUser = Session::get('user', []);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));
        if (!RolePermissions::canAccessChat($role)) {
            $this->json(['error' => 'Acces refuse'], 403);
            return;
        }

        $conversationId = (int) $id;
        if ($conversationId <= 0) {
            $this->json(['conversation' => null, 'messages' => []]);
            return;
        }

        try {
            $agent = new ChatAgent();
            $this->json($agent->getConversationHistory($companyId, $userId, $conversationId));
        } catch (\Throwable $exception) {
            $this->json(['error' => 'Erreur IA: ' . $exception->getMessage()], 500);
        }
    }

    public function stats(): void
    {
        $companyId = $this->resolveCompanyId();
        if ($companyId <= 0) {
            $this->json(['error' => 'Authentification requise'], 401);
            return;
        }

        $dashboard = new Dashboard();
        $forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';
        $payload = $dashboard->getDashboardPayloadCached($companyId, 60, $forceRefresh, [
            'cashflow_period' => (string) ($_GET['cashflow_period'] ?? '30d'),
            'expenses_period' => (string) ($_GET['expenses_period'] ?? 'month'),
        ]);
        $stats = $payload['stats'] ?? [];
        $cashflow = $payload['cashflow'] ?? ['labels' => [], 'values' => []];
        $expenseBreakdown = $payload['expenseBreakdown'] ?? ['labels' => [], 'values' => []];

        $this->json([
            'cash' => round((float) ($stats['cash'] ?? 0), 2),
            'revenue' => round((float) ($stats['revenue'] ?? 0), 2),
            'expenses' => round((float) ($stats['expenses'] ?? 0), 2),
            'vat_due' => round((float) ($stats['vat_due'] ?? 0), 2),
            'revenue_trend' => round((float) ($stats['revenue_trend'] ?? 0), 2),
            'expenses_trend' => round((float) ($stats['expenses_trend'] ?? 0), 2),
            'cashflow' => $cashflow,
            'expense_breakdown' => $expenseBreakdown,
        ]);
    }

    public function alerts(): void
    {
        $companyId = $this->resolveCompanyId();
        if ($companyId <= 0) {
            $this->json(['error' => 'Authentification requise'], 401);
            return;
        }

        $dashboard = new Dashboard();
        $forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';
        $payload = $dashboard->getDashboardPayloadCached($companyId, 60, $forceRefresh, [
            'cashflow_period' => (string) ($_GET['cashflow_period'] ?? '30d'),
            'expenses_period' => (string) ($_GET['expenses_period'] ?? 'month'),
        ]);
        $alerts = $payload['alerts'] ?? [];
        $this->json(array_slice($alerts, 0, 10));
    }

    public function clientSearch(): void
    {
        $companyId = $this->resolveCompanyId();
        if ($companyId <= 0) {
            $this->json(['error' => 'Authentification requise'], 401);
            return;
        }

        $query = trim((string) ($_GET['q'] ?? $_GET['phone'] ?? ''));
        $limit = max(1, min(12, (int) ($_GET['limit'] ?? 8)));
        $onlyDebtors = in_array((string) ($_GET['only_debtors'] ?? '0'), ['1', 'true', 'yes'], true);

        if ($query === '') {
            $this->json([
                'query' => '',
                'items' => [],
                'exact' => null,
            ]);
            return;
        }

        $invoiceModel = new Invoice();
        $items = $invoiceModel->searchClientsForAutocomplete($companyId, $query, $limit, $onlyDebtors);
        $phoneOnly = preg_replace('/[^0-9]+/', '', $query);
        $exact = is_string($phoneOnly) && strlen($phoneOnly) >= 6
            ? $invoiceModel->findClientSummaryByPhone($companyId, $phoneOnly)
            : null;

        $this->json([
            'query' => $query,
            'items' => $items,
            'exact' => $exact,
        ]);
    }

    public function clientOutstanding(): void
    {
        $companyId = $this->resolveCompanyId();
        if ($companyId <= 0) {
            $this->json(['error' => 'Authentification requise'], 401);
            return;
        }

        $clientName = trim((string) ($_GET['name'] ?? ''));
        $clientPhone = trim((string) ($_GET['phone'] ?? ''));
        if ($clientName === '' && $clientPhone === '') {
            $this->json([
                'items' => [],
                'client' => null,
            ]);
            return;
        }

        $invoiceModel = new Invoice();
        $rows = $invoiceModel->listOutstandingInvoicesForClient($companyId, $clientName, $clientPhone);

        $items = [];
        $totalDebt = 0.0;
        foreach ($rows as $row) {
            $total = round((float) ($row['total'] ?? 0), 2);
            $paid = round((float) ($row['paid_amount'] ?? 0), 2);
            $remaining = max($total - $paid, 0);
            $totalDebt += $remaining;
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'invoice_number' => (string) ($row['invoice_number'] ?? ''),
                'invoice_date' => (string) ($row['invoice_date'] ?? ''),
                'due_date' => (string) ($row['due_date'] ?? ''),
                'total' => $total,
                'paid_amount' => $paid,
                'remaining' => round($remaining, 2),
            ];
        }

        $this->json([
            'client' => [
                'name' => $clientName,
                'phone' => $clientPhone,
                'total_debt' => round($totalDebt, 2),
            ],
            'items' => $items,
        ]);
    }

    public function userContext(): void
    {
        $companyId = $this->resolveCompanyId();
        if ($companyId <= 0) {
            $this->json(['error' => 'Authentification requise'], 401);
            return;
        }

        $dashboard = new Dashboard();
        $forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';
        $payload = $dashboard->getDashboardPayloadCached($companyId, 60, $forceRefresh, [
            'cashflow_period' => (string) ($_GET['cashflow_period'] ?? '30d'),
            'expenses_period' => (string) ($_GET['expenses_period'] ?? 'month'),
        ]);
        $alerts = array_slice($payload['alerts'] ?? [], 0, 3);

        $alertMessages = [];
        foreach ($alerts as $alert) {
            $alertMessages[] = [
                'message' => (string) ($alert['title'] ?? 'Alerte') . ': ' . (string) ($alert['message'] ?? ''),
            ];
        }

        $this->json([
            'alerts' => $alertMessages,
            'has_alerts' => count($alerts) > 0,
        ]);
    }

    public function surveillance(): void
    {
        $this->json([
            'status' => 'disabled',
            'message' => 'Fonctionnalite IA desactivee en mode local.',
            'timestamp' => date('c'),
        ]);
    }

    private function resolveCompanyId(): int
    {
        $sessionUser = Session::get('user', []);
        return (int) ($sessionUser['company_id'] ?? 0);
    }

    private function resolveUserId(): int
    {
        $sessionUser = Session::get('user', []);
        return (int) ($sessionUser['id'] ?? 0);
    }
}
