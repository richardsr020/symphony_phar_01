<?php

namespace App\Controllers;

use App\Core\AuditLogger;
use App\Core\ExcelExporter;
use App\Core\RolePermissions;
use App\Core\Session;
use App\Models\Account;
use App\Models\Dashboard;
use App\Models\Transaction;
use App\Models\Company;

class TransactionsController extends Controller
{
    private Transaction $transactionModel;

    public function __construct()
    {
        $this->transactionModel = new Transaction();
    }

    public function index(): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0) {
            $this->redirect('/login');
        }
        if (!RolePermissions::canAccessTransactions($role)) {
            $this->redirect('/dashboard?error=transactions_forbidden');
        }

        $filters = [
            'status' => (string) ($_GET['status'] ?? ''),
            'type' => (string) ($_GET['type'] ?? ''),
            'q' => (string) ($_GET['q'] ?? ''),
            'from_date' => (string) ($_GET['from_date'] ?? ''),
            'to_date' => (string) ($_GET['to_date'] ?? ''),
        ];

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $sortBy = (string) ($_GET['sort_by'] ?? 'transaction_date');
        $sortDir = (string) ($_GET['sort_dir'] ?? 'desc');
        $perPage = (int) (\Config::ITEMS_PER_PAGE ?? 20);
        $pagination = $this->transactionModel->getByCompanyPaginated(
            $companyId,
            $filters,
            $page,
            $perPage,
            $sortBy,
            $sortDir
        );
        $transactions = $pagination['rows'];

        $summary = [
            'total_debit' => 0.0,
            'total_credit' => 0.0,
            'total_count' => (int) ($pagination['total'] ?? count($transactions)),
        ];

        foreach ($transactions as $transaction) {
            $summary['total_debit'] += (float) ($transaction['debit_total'] ?? 0);
            $summary['total_credit'] += (float) ($transaction['credit_total'] ?? 0);
        }

        $summary['balance'] = $summary['total_debit'] - $summary['total_credit'];

        $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
        $editingTransaction = null;
        if ($editId > 0) {
            $editingTransaction = $this->transactionModel->findByIdForCompany($companyId, $editId);
        }
        $editingBlocked = false;
        if (is_array($editingTransaction) && in_array((string) ($editingTransaction['type'] ?? ''), ['debt_payment', 'transfer', 'journal'], true)) {
            $editingTransaction = null;
            $editingBlocked = true;
        }

        $accountModel = new Account();

        $this->renderMain('transactions', [
            'title' => 'Transactions',
            'transactions' => $transactions,
            'summary' => $summary,
            'filters' => $filters,
            'accounts' => $accountModel->getByCompany($companyId),
            'nextTransactionReference' => $this->transactionModel->generateNextReference($companyId, date('Y-m-d')),
            'showCreateForm' => (($_GET['mode'] ?? '') === 'create'),
            'editingTransaction' => $editingTransaction,
            'pagination' => $pagination,
            'flashSuccess' => $this->resolveSuccess((string) ($_GET['success'] ?? '')),
            'flashError' => $editingBlocked ? $this->resolveError('transaction_locked') : $this->resolveError((string) ($_GET['error'] ?? '')),
        ]);
    }

    public function create(): void
    {
        $sessionUser = Session::get('user', []);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));
        if (!RolePermissions::canAccessTransactions($role)) {
            $this->redirect('/dashboard?error=transactions_forbidden');
        }

        $this->redirect('/transactions?mode=create');
    }

    public function export(): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0) {
            $this->redirect('/login');
        }
        if (!RolePermissions::canAccessTransactions($role)) {
            $this->redirect('/dashboard?error=transactions_forbidden');
        }

        $filters = [
            'status' => (string) ($_GET['status'] ?? ''),
            'type' => (string) ($_GET['type'] ?? ''),
            'q' => (string) ($_GET['q'] ?? ''),
            'from_date' => (string) ($_GET['from_date'] ?? ''),
            'to_date' => (string) ($_GET['to_date'] ?? ''),
        ];

        $rows = $this->transactionModel->getByCompany($companyId, $filters);
        $typeLabels = [
            'income' => 'Revenu',
            'expense' => 'Depense',
            'transfer' => 'Transfert',
            'journal' => 'Journal',
            'billing' => 'Facturation',
            'debt_payment' => 'Remboursement de dettes',
        ];
        $statusLabels = [
            'draft' => 'Brouillon',
            'posted' => 'Validee',
            'void' => 'Annulee',
        ];
        $headers = [
            'Date',
            'Reference',
            'Description',
            'Type',
            'Debit',
            'Credit',
            'Statut',
            'Compte',
            'Cree par',
            'Source',
        ];

        $dataRows = [];
        foreach ($rows as $row) {
            $type = (string) ($row['type'] ?? '');
            $status = (string) ($row['status'] ?? '');
            $dataRows[] = [
                (string) ($row['transaction_date'] ?? ''),
                (string) ($row['reference'] ?? ''),
                (string) ($row['description'] ?? ''),
                $typeLabels[$type] ?? $type,
                number_format((float) ($row['debit_total'] ?? 0), 2, '.', ''),
                number_format((float) ($row['credit_total'] ?? 0), 2, '.', ''),
                $statusLabels[$status] ?? $status,
                (string) ($row['account_label'] ?? ''),
                (string) ($row['created_by_name'] ?? ''),
                (string) ($row['source'] ?? ''),
            ];
        }
        ExcelExporter::download('transactions', $headers, $dataRows);
    }

    public function store(): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userId = (int) ($sessionUser['id'] ?? 0);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $userId <= 0) {
            $this->redirect('/transactions?error=auth_required');
        }
        if (!RolePermissions::canManageTransactions($role)) {
            $this->redirect('/transactions?error=permission_denied');
        }

        try {
            $payloadType = trim((string) ($_POST['type'] ?? ''));
            if ($payloadType === 'debt_payment') {
                $transactionId = $this->transactionModel->createDebtPayment($companyId, $userId, $_POST);
            } else {
                $transactionId = $this->transactionModel->createManual($companyId, $userId, $_POST);
            }
            (new Dashboard())->invalidateDashboardCache($companyId);
            AuditLogger::log($userId, $payloadType === 'debt_payment' ? 'debt_payment_recorded' : 'transaction_created', 'transactions', $transactionId, null, [
                'description' => (string) ($_POST['description'] ?? ''),
                'amount' => (float) ($_POST['amount'] ?? 0),
                'type' => (string) ($_POST['type'] ?? ''),
            ]);
            $successCode = $payloadType === 'debt_payment' ? 'debt_payment_recorded' : 'transaction_created';
            $this->redirect('/transactions?success=' . $successCode);
        } catch (\InvalidArgumentException $exception) {
            $error = $this->resolveInvalidPayloadErrorCode($exception);
            $this->redirect('/transactions?mode=create&error=' . $error);
        } catch (\Throwable $exception) {
            $this->redirect('/transactions?mode=create&error=transaction_create_failed');
        }
    }

    public function edit($id): void
    {
        $sessionUser = Session::get('user', []);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));
        if (!RolePermissions::canAccessTransactions($role)) {
            $this->redirect('/dashboard?error=transactions_forbidden');
        }

        $this->redirect('/transactions?edit=' . urlencode((string) $id));
    }

    public function update($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userId = (int) ($sessionUser['id'] ?? 0);
        $transactionId = (int) $id;
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $transactionId <= 0) {
            $this->redirect('/transactions?error=auth_required');
        }
        if (!RolePermissions::canManageTransactions($role)) {
            $this->redirect('/transactions?error=permission_denied');
        }

        try {
            $this->transactionModel->updateManual($companyId, $transactionId, $_POST);
            (new Dashboard())->invalidateDashboardCache($companyId);
            AuditLogger::log($userId, 'transaction_updated', 'transactions', $transactionId, null, [
                'description' => (string) ($_POST['description'] ?? ''),
                'amount' => (float) ($_POST['amount'] ?? 0),
                'type' => (string) ($_POST['type'] ?? ''),
            ]);
            $this->redirect('/transactions?success=transaction_updated');
        } catch (\InvalidArgumentException $exception) {
            $error = $this->resolveInvalidPayloadErrorCode($exception);
            $this->redirect('/transactions?edit=' . $transactionId . '&error=' . $error);
        } catch (\Throwable $exception) {
            $this->redirect('/transactions?edit=' . $transactionId . '&error=transaction_update_failed');
        }
    }

    public function delete($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userId = (int) ($sessionUser['id'] ?? 0);
        $transactionId = (int) $id;
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $transactionId <= 0) {
            $this->redirect('/transactions?error=auth_required');
        }
        if (!RolePermissions::canManageTransactions($role)) {
            $this->redirect('/transactions?error=permission_denied');
        }

        $existing = $this->transactionModel->findByIdForCompany($companyId, $transactionId);
        if ($existing && (string) ($existing['type'] ?? '') === 'debt_payment') {
            $this->redirect('/transactions?error=transaction_locked');
        }

        if ($this->transactionModel->deleteForCompany($companyId, $transactionId)) {
            (new Dashboard())->invalidateDashboardCache($companyId);
            AuditLogger::log($userId, 'transaction_deleted', 'transactions', $transactionId);
            $this->redirect('/transactions?success=transaction_deleted');
        }

        $this->redirect('/transactions?error=transaction_not_found');
    }

    public function view($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));
        $transactionId = (int) $id;

        if ($companyId <= 0 || $transactionId <= 0) {
            $this->redirect('/transactions?error=receipt_not_found');
        }
        if (!RolePermissions::canAccessTransactions($role)) {
            $this->redirect('/dashboard?error=transactions_forbidden');
        }

        $transaction = $this->transactionModel->findByIdForCompany($companyId, $transactionId);
        if ($transaction === null) {
            $this->redirect('/transactions?error=receipt_not_found');
        }

        $previewPath = $this->resolvePreviewPath($transactionId, (string) ($transaction['type'] ?? ''));
        if ($previewPath === null) {
            $this->redirect('/transactions?error=preview_not_available');
        }

        $this->redirect($previewPath);
    }

    public function preview($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));
        $transactionId = (int) $id;

        if ($companyId <= 0 || $transactionId <= 0) {
            $this->redirect('/transactions?error=receipt_not_found');
        }
        if (!RolePermissions::canAccessTransactions($role)) {
            $this->redirect('/dashboard?error=transactions_forbidden');
        }

        $transaction = $this->transactionModel->findByIdForCompany($companyId, $transactionId);
        if ($transaction === null) {
            $this->redirect('/transactions?error=receipt_not_found');
        }

        // Only allow printable receipts for simple income/expense transactions
        $type = (string) ($transaction['type'] ?? '');
        if (!in_array($type, ['income', 'expense'], true)) {
            $this->redirect('/transactions?error=receipt_not_found');
        }

        $receipt = $this->buildPrintableReceipt($transactionId, $transaction);

        $company = (new Company())->findById($companyId) ?? [];

        $this->renderMain('transactions/preview', [
            'title' => ($type === 'expense' ? 'Bon de sortie caisse' : 'Bon d\'entree caisse') . ' ' . ($receipt['receipt_number'] ?? ''),
            'receipt' => $receipt,
            'company' => $company,
        ]);
    }

    public function generatePDF($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));
        $transactionId = (int) $id;

        if ($companyId <= 0 || $transactionId <= 0) {
            $this->redirect('/transactions?error=receipt_not_found');
        }
        if (!RolePermissions::canAccessTransactions($role)) {
            $this->redirect('/dashboard?error=transactions_forbidden');
        }

        $transaction = $this->transactionModel->findByIdForCompany($companyId, $transactionId);
        if ($transaction === null) {
            $this->redirect('/transactions?error=receipt_not_found');
        }

        $type = (string) ($transaction['type'] ?? '');
        if (!in_array($type, ['income', 'expense'], true)) {
            $this->redirect('/transactions?error=receipt_not_found');
        }

        $receipt = $this->buildPrintableReceipt($transactionId, $transaction);

        $company = (new Company())->findById($companyId) ?? [];

        $this->renderMain('transactions/preview', [
            'title' => ($type === 'expense' ? 'PDF bon de sortie caisse' : 'PDF bon d\'entree caisse') . ' ' . ($receipt['receipt_number'] ?? ''),
            'receipt' => $receipt,
            'company' => $company,
            'autoDownload' => true,
        ]);
    }

    private function resolveSuccess(string $code): string
    {
        $messages = [
            'transaction_created' => 'Transaction enregistree avec succes.',
            'transaction_updated' => 'Transaction mise a jour avec succes.',
            'transaction_deleted' => 'Transaction supprimee avec succes.',
            'debt_payment_recorded' => 'Remboursement enregistre avec succes.',
        ];

        return $messages[$code] ?? '';
    }

    private function resolveError(string $code): string
    {
        $messages = [
            'auth_required' => 'Session invalide. Reconnectez-vous.',
            'permission_denied' => 'Action refusee: permissions insuffisantes pour les transactions.',
            'invalid_payload' => 'Veuillez verifier les champs obligatoires de la transaction.',
            'insufficient_treasury' => 'Transaction refusee: tresorerie insuffisante.',
            'debt_client_missing' => 'Selectionnez un client endette valide.',
            'debt_no_invoices' => 'Aucune facture impayee pour ce client.',
            'debt_amount_exceeds' => 'Le montant depasse la dette totale du client.',
            'transaction_locked' => 'Cette transaction ne peut pas etre modifiee.',
            'preview_not_available' => 'Aucun apercu imprimable disponible pour ce type de transaction.',
            'transaction_create_failed' => 'Impossible d\'enregistrer la transaction pour le moment.',
            'transaction_update_failed' => 'Impossible de mettre a jour la transaction pour le moment.',
            'transaction_not_found' => 'Transaction introuvable.',
            'receipt_not_found' => 'Recu introuvable ou non disponible.',
        ];

        return $messages[$code] ?? '';
    }

    private function resolveInvalidPayloadErrorCode(\InvalidArgumentException $exception): string
    {
        $message = strtolower(trim($exception->getMessage()));
        if (str_contains($message, 'tresorerie insuffisante')) {
            return 'insufficient_treasury';
        }
        if (str_contains($message, 'client endette')) {
            return 'debt_client_missing';
        }
        if (str_contains($message, 'aucune facture impayee')) {
            return 'debt_no_invoices';
        }
        if (str_contains($message, 'montant superieur')) {
            return 'debt_amount_exceeds';
        }
        if (str_contains($message, 'non modifiable')) {
            return 'transaction_locked';
        }

        return 'invalid_payload';
    }

    private function resolvePreviewPath(int $transactionId, string $type): ?string
    {
        if ($type === 'debt_payment') {
            return '/receipts/preview/' . $transactionId;
        }

        if (in_array($type, ['income', 'expense'], true)) {
            return '/transactions/preview/' . $transactionId;
        }

        return null;
    }

    private function buildPrintableReceipt(int $transactionId, array $transaction): array
    {
        $entries = is_array($transaction['entries'] ?? null) ? $transaction['entries'] : [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($entries as $entry) {
            $totalDebit += (float) ($entry['debit'] ?? 0);
            $totalCredit += (float) ($entry['credit'] ?? 0);
        }

        $type = (string) ($transaction['type'] ?? '');
        $totalAmount = $type === 'income' ? $totalDebit : $totalCredit;

        return [
            'transaction_id' => $transactionId,
            'transaction_date' => (string) ($transaction['transaction_date'] ?? ''),
            'created_at' => (string) ($transaction['created_at'] ?? ''),
            'receipt_number' => (string) ($transaction['reference'] ?? ''),
            'type' => $type,
            'description' => (string) ($transaction['description'] ?? ''),
            'created_by_name' => (string) ($transaction['created_by_name'] ?? ''),
            'total_amount' => round($totalAmount, 2),
            'entries' => $entries,
        ];
    }
}
