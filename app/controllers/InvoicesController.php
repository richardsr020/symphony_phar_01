<?php

namespace App\Controllers;

use App\Core\AuditLogger;
use App\Core\AppLogger;
use App\Core\ExcelExporter;
use App\Core\RolePermissions;
use App\Core\Session;
use App\Models\Company;
use App\Models\Dashboard;
use App\Models\Invoice;
use App\Models\Product;

class InvoicesController extends Controller
{
    private Invoice $invoiceModel;

    public function __construct()
    {
        $this->invoiceModel = new Invoice();
    }

    public function index(): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));
        $canManageInvoices = RolePermissions::canManageInvoices($role);

        if ($companyId <= 0) {
            $this->redirect('/login');
        }

        $filters = [
            'status' => (string) ($_GET['status'] ?? ''),
            'q' => (string) ($_GET['q'] ?? ''),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $sortBy = (string) ($_GET['sort_by'] ?? 'invoice_date');
        $sortDir = (string) ($_GET['sort_dir'] ?? 'desc');
        $perPage = (int) (\Config::ITEMS_PER_PAGE ?? 20);
        $pagination = $this->invoiceModel->getByCompanyPaginated(
            $companyId,
            $filters,
            $page,
            $perPage,
            $sortBy,
            $sortDir
        );

        $selectedInvoice = null;
        $invoiceId = isset($_GET['view']) ? (int) $_GET['view'] : 0;
        if ($invoiceId > 0) {
            $selectedInvoice = $this->invoiceModel->findByIdForCompany($companyId, $invoiceId);
        }

        $this->renderMain('invoices', [
            'title' => 'Ventes',
            'invoices' => $pagination['rows'],
            'stats' => $this->invoiceModel->getStatsByCompany($companyId),
            'filters' => $filters,
            'pagination' => $pagination,
            'selectedInvoice' => $selectedInvoice,
            'canManageInvoices' => $canManageInvoices,
            'flashSuccess' => $this->resolveSuccess((string) ($_GET['success'] ?? '')),
            'flashError' => $this->resolveError((string) ($_GET['error'] ?? '')),
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
        if (!RolePermissions::canAccessInvoices($role)) {
            $this->redirect('/dashboard?error=invoices_forbidden');
        }

        $filters = [
            'status' => (string) ($_GET['status'] ?? ''),
            'q' => (string) ($_GET['q'] ?? ''),
        ];
        $sortBy = (string) ($_GET['sort_by'] ?? 'invoice_date');
        $sortDir = (string) ($_GET['sort_dir'] ?? 'desc');

        $rows = $this->invoiceModel->getByCompanyFiltered($companyId, $filters, $sortBy, $sortDir);
        $statusLabels = [
            'draft' => 'Brouillon',
            'sent' => 'Envoyee',
            'paid' => 'Payee',
            'overdue' => 'En retard',
            'cancelled' => 'Annulee',
        ];
        $headers = [
            'Date',
            'Echeance',
            'Numero',
            'Client',
            'Subtotal',
            'TVA (%)',
            'TVA',
            'Total',
            'Statut',
            'Cree par',
        ];

        $dataRows = [];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            $dataRows[] = [
                (string) ($row['invoice_date'] ?? ''),
                (string) ($row['due_date'] ?? ''),
                (string) ($row['invoice_number'] ?? ''),
                (string) ($row['customer_name'] ?? ''),
                number_format((float) ($row['subtotal'] ?? 0), 2, '.', ''),
                number_format((float) ($row['tax_rate'] ?? 0), 2, '.', ''),
                number_format((float) ($row['tax_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($row['total'] ?? 0), 2, '.', ''),
                $statusLabels[$status] ?? $status,
                (string) ($row['created_by_name'] ?? ''),
            ];
        }
        ExcelExporter::download('factures', $headers, $dataRows);
    }

    public function create(): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0) {
            $this->redirect('/login');
        }
        if (!RolePermissions::canManageInvoices($role)) {
            $this->redirect('/invoices?error=permission_denied');
        }

        $today = date('Y-m-d');
        $defaultDueDate = date('Y-m-d', strtotime('+15 days'));
        $company = (new Company())->findById($companyId) ?? [];
        $defaultTaxRate = round((float) ($company['default_tax_rate'] ?? 0), 2);

        $this->renderMain('invoices/create', [
            'title' => 'Enregistrer une vente',
            'isEditMode' => false,
            'formAction' => '/invoices/store',
            'submitLabel' => 'Enregistrer la vente',
            'today' => $today,
            'dueDate' => $defaultDueDate,
            'isTemplateMode' => isset($_GET['template']) && $_GET['template'] === '1',
            'invoiceNumber' => $this->invoiceModel->generateNextNumber($companyId),
            'invoiceProducts' => (new Product())->getInvoiceOptions($companyId),
            'defaultTaxRate' => $defaultTaxRate,
            'canSaveDraft' => true,
            'flashError' => $this->resolveError((string) ($_GET['error'] ?? '')),
        ]);
    }

    public function edit($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $invoiceId = (int) $id;
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $invoiceId <= 0) {
            $this->redirect('/invoices?error=auth_required');
        }
        if (!RolePermissions::canManageInvoices($role)) {
            $this->redirect('/invoices?error=permission_denied');
        }

        $invoice = $this->invoiceModel->findByIdForCompany($companyId, $invoiceId);
        if ($invoice === null) {
            $this->redirect('/invoices?error=invoice_not_found');
        }

        $status = (string) ($invoice['status'] ?? '');
        $isEditable = $status === 'draft';
        if (!$isEditable) {
            $this->redirect('/invoices?error=editable_only');
        }
        $company = (new Company())->findById($companyId) ?? [];
        $defaultTaxRate = round((float) ($company['default_tax_rate'] ?? 0), 2);

        $this->renderMain('invoices/create', [
            'title' => 'Modifier vente',
            'isEditMode' => true,
            'invoiceToEdit' => $invoice,
            'formAction' => '/invoices/update/' . $invoiceId,
            'submitLabel' => 'Mettre a jour et envoyer',
            'canSaveDraft' => true,
            'today' => (string) ($invoice['invoice_date'] ?? date('Y-m-d')),
            'dueDate' => (string) ($invoice['due_date'] ?? date('Y-m-d')),
            'isTemplateMode' => false,
            'invoiceNumber' => (string) ($invoice['invoice_number'] ?? ''),
            'invoiceProducts' => (new Product())->getInvoiceOptions($companyId),
            'defaultTaxRate' => $defaultTaxRate,
            'flashError' => $this->resolveError((string) ($_GET['error'] ?? '')),
        ]);
    }

    public function store(): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userId = (int) ($sessionUser['id'] ?? 0);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $userId <= 0) {
            $this->redirect('/invoices/create?error=auth_required');
        }
        if (!RolePermissions::canManageInvoices($role)) {
            $this->redirect('/invoices?error=permission_denied');
        }

        try {
            $invoiceId = $this->invoiceModel->createFromPayload($companyId, $userId, $_POST);
            (new Dashboard())->invalidateDashboardCache($companyId);
            AuditLogger::log($userId, 'invoice_created', 'invoices', $invoiceId, null, [
                'invoice_number' => (string) ($_POST['invoice_number'] ?? ''),
                'customer_name' => (string) ($_POST['client_name'] ?? ''),
            ]);
            $this->redirect('/invoices?success=invoice_created&view=' . $invoiceId);
        } catch (\InvalidArgumentException $exception) {
            AppLogger::info('Invoice payload invalid', [
                'company_id' => $companyId,
                'user_id' => $userId,
                'message' => $exception->getMessage(),
                'invoice_number' => (string) ($_POST['invoice_number'] ?? ''),
                'client_name' => (string) ($_POST['client_name'] ?? ''),
                'client_phone' => (string) ($_POST['client_phone'] ?? ''),
                'invoice_type' => (string) ($_POST['invoice_type'] ?? ''),
            ]);
            $error = $this->resolveInvalidPayloadErrorCode($exception);
            $this->redirect('/invoices/create?error=' . $error);
        } catch (\Throwable $exception) {
            // generate a short error id to correlate UI message with logs
            $errId = substr(sha1($exception->getMessage() . microtime(true)), 0, 8);
            AppLogger::error('Invoice create failed', [
                'company_id' => $companyId,
                'user_id' => $userId,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'err_id' => $errId,
                'trace' => $exception->getTraceAsString(),
            ]);
            // include err id in redirect so it can be reported for debugging
            $this->redirect('/invoices/create?error=invoice_create_failed&err=' . $errId);
        }
    }

    public function update($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $invoiceId = (int) $id;
        $userId = (int) ($sessionUser['id'] ?? 0);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $invoiceId <= 0 || $userId <= 0) {
            $this->redirect('/invoices?error=auth_required');
        }
        if (!RolePermissions::canManageInvoices($role)) {
            $this->redirect('/invoices?error=permission_denied');
        }

        try {
            $this->invoiceModel->updateDraftFromPayload($companyId, $invoiceId, $userId, $_POST);
            (new Dashboard())->invalidateDashboardCache($companyId);
            AuditLogger::log($userId, 'invoice_updated', 'invoices', $invoiceId, null, [
                'invoice_number' => (string) ($_POST['invoice_number'] ?? ''),
                'customer_name' => (string) ($_POST['client_name'] ?? ''),
            ]);
            $this->redirect('/invoices?success=invoice_updated&view=' . $invoiceId);
        } catch (\InvalidArgumentException $exception) {
            AppLogger::info('Invoice update payload invalid', [
                'company_id' => $companyId,
                'user_id' => $userId,
                'invoice_id' => $invoiceId,
                'message' => $exception->getMessage(),
                'invoice_number' => (string) ($_POST['invoice_number'] ?? ''),
                'client_name' => (string) ($_POST['client_name'] ?? ''),
                'client_phone' => (string) ($_POST['client_phone'] ?? ''),
                'invoice_type' => (string) ($_POST['invoice_type'] ?? ''),
            ]);
            $error = $this->resolveInvalidPayloadErrorCode($exception);
            $this->redirect('/invoices/edit/' . $invoiceId . '?error=' . $error);
        } catch (\Throwable $exception) {
            AppLogger::error('Invoice update failed', [
                'company_id' => $companyId,
                'user_id' => $userId,
                'invoice_id' => $invoiceId,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
            $this->redirect('/invoices/edit/' . $invoiceId . '?error=invoice_update_failed');
        }
    }

    public function merge(): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userId = (int) ($sessionUser['id'] ?? 0);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $userId <= 0) {
            $this->redirect('/invoices?error=auth_required');
        }
        if (!RolePermissions::canManageInvoices($role)) {
            $this->redirect('/invoices?error=permission_denied');
        }

        try {
            $invoiceIds = $_POST['invoice_ids'] ?? [];
            $mergedId = $this->invoiceModel->mergeDraftInvoices($companyId, $userId, is_array($invoiceIds) ? $invoiceIds : []);
            (new Dashboard())->invalidateDashboardCache($companyId);
            AuditLogger::log($userId, 'invoice_merged', 'invoices', $mergedId, null, [
                'source_ids' => $invoiceIds,
            ]);
            $this->redirect('/invoices?success=invoices_merged&view=' . $mergedId);
        } catch (\InvalidArgumentException $exception) {
            $error = $this->resolveInvalidPayloadErrorCode($exception);
            if ($error === 'invalid_payload') {
                $error = 'invalid_merge_selection';
            }
            $this->redirect('/invoices?error=' . $error);
        } catch (\Throwable $exception) {
            AppLogger::error('Invoice merge failed', [
                'company_id' => $companyId,
                'user_id' => $userId,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
            $this->redirect('/invoices?error=invoice_merge_failed');
        }
    }

    public function send($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userId = (int) ($sessionUser['id'] ?? 0);
        $invoiceId = (int) $id;
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $invoiceId <= 0) {
            $this->redirect('/invoices?error=auth_required');
        }
        if (!RolePermissions::canManageInvoices($role)) {
            $this->redirect('/invoices?error=permission_denied');
        }

        if ($this->invoiceModel->markSent($companyId, $invoiceId)) {
            (new Dashboard())->invalidateDashboardCache($companyId);
            AuditLogger::log($userId, 'invoice_sent', 'invoices', $invoiceId);
            $this->redirect('/invoices?success=invoice_sent&view=' . $invoiceId);
        }

        $this->redirect('/invoices?error=draft_only');
    }

    public function cancel($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $invoiceId = (int) $id;
        $userId = (int) ($sessionUser['id'] ?? 0);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $invoiceId <= 0 || $userId <= 0) {
            $this->redirect('/invoices?error=auth_required');
        }
        if (!RolePermissions::canManageInvoices($role)) {
            $this->redirect('/invoices?error=permission_denied');
        }

        if ($this->invoiceModel->cancelDraft($companyId, $invoiceId, $userId)) {
            (new Dashboard())->invalidateDashboardCache($companyId);
            AuditLogger::log($userId, 'invoice_cancelled', 'invoices', $invoiceId);
            $this->redirect('/invoices?success=invoice_cancelled');
        }

        $this->redirect('/invoices?error=cancel_not_allowed');
    }

    public function delete($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $invoiceId = (int) $id;
        $userId = (int) ($sessionUser['id'] ?? 0);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $invoiceId <= 0 || $userId <= 0) {
            $this->redirect('/invoices?error=auth_required');
        }
        if (!RolePermissions::canManageInvoices($role)) {
            $this->redirect('/invoices?error=permission_denied');
        }

        if ($this->invoiceModel->deleteForCompany($companyId, $invoiceId, $userId)) {
            (new Dashboard())->invalidateDashboardCache($companyId);
            AuditLogger::log($userId, 'invoice_deleted', 'invoices', $invoiceId);
            $this->redirect('/invoices?success=invoice_deleted');
        }

        $this->redirect('/invoices?error=invoice_delete_not_allowed');
    }

    public function registerPayment($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userId = (int) ($sessionUser['id'] ?? 0);
        $invoiceId = (int) $id;
        $amount = (float) ($_POST['amount'] ?? 0);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $invoiceId <= 0) {
            $this->redirect('/invoices?error=auth_required');
        }
        if (!RolePermissions::canManageInvoices($role)) {
            $this->redirect('/invoices?error=permission_denied');
        }

        try {
            (new \App\Models\Transaction())->createInvoicePaymentForInvoice(
                $companyId,
                $userId,
                $invoiceId,
                $amount,
                date('Y-m-d')
            );
        } catch (\Throwable $exception) {
            $this->redirect('/invoices?error=invalid_payment');
        }

        (new Dashboard())->invalidateDashboardCache($companyId);
        AuditLogger::log($userId, 'invoice_payment_recorded', 'invoices', $invoiceId, null, [
            'amount' => $amount,
        ]);
        $this->redirect('/invoices?success=payment_recorded&view=' . $invoiceId);
    }

    public function view($id): void
    {
        $this->redirect('/invoices?view=' . urlencode((string) $id));
    }

    public function preview($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $invoiceId = (int) $id;

        if ($companyId <= 0 || $invoiceId <= 0) {
            $this->redirect('/invoices?error=invoice_not_found');
        }

        $invoice = $this->invoiceModel->findByIdForCompany($companyId, $invoiceId);
        if ($invoice === null) {
            $this->redirect('/invoices?error=invoice_not_found');
        }

        $this->renderMain('invoices/preview', [
            'title' => 'Apercu vente ' . (string) ($invoice['invoice_number'] ?? ''),
            'invoice' => $invoice,
            'company' => (new Company())->findById($companyId) ?? [],
        ]);
    }

    public function markDownloaded($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userId = (int) ($sessionUser['id'] ?? 0);
        $invoiceId = (int) $id;

        if ($companyId <= 0 || $invoiceId <= 0 || $userId <= 0) {
            $this->json(['success' => false, 'message' => 'auth_required'], 403);
            return;
        }

        $invoice = $this->invoiceModel->findByIdForCompany($companyId, $invoiceId);
        if ($invoice === null) {
            $this->json(['success' => false, 'message' => 'invoice_not_found'], 404);
            return;
        }

        if ($this->invoiceModel->markPdfDownloaded($companyId, $invoiceId)) {
            AuditLogger::log($userId, 'invoice_pdf_downloaded', 'invoices', $invoiceId, null, [
                'invoice_number' => (string) ($invoice['invoice_number'] ?? ''),
                'downloaded_at' => date('c'),
            ]);
            $this->json(['success' => true]);
            return;
        }

        $this->json(['success' => false, 'message' => 'mark_failed'], 500);
    }

    public function generatePDF($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userId = (int) ($sessionUser['id'] ?? 0);
        $invoiceId = (int) $id;

        if ($companyId <= 0 || $invoiceId <= 0) {
            $this->redirect('/invoices?error=invoice_not_found');
        }

        $invoice = $this->invoiceModel->findByIdForCompany($companyId, $invoiceId);
        if ($invoice === null) {
            $this->redirect('/invoices?error=invoice_not_found');
        }

        if ($this->invoiceModel->markPdfDownloaded($companyId, $invoiceId) && $userId > 0) {
            AuditLogger::log($userId, 'invoice_pdf_downloaded', 'invoices', $invoiceId, null, [
                'invoice_number' => (string) ($invoice['invoice_number'] ?? ''),
                'downloaded_at' => date('c'),
            ]);
        }

        $this->renderMain('invoices/preview', [
            'title' => 'PDF vente ' . (string) ($invoice['invoice_number'] ?? ''),
            'invoice' => $invoice,
            'company' => (new Company())->findById($companyId) ?? [],
            'autoDownload' => true,
        ]);
    }

    private function resolveSuccess(string $code): string
    {
        $messages = [
            'invoice_created' => 'Facture creee avec succes.',
            'invoice_updated' => 'Brouillon mis a jour.',
            'invoice_sent' => 'Facture envoyee avec succes.',
            'invoice_cancelled' => 'Facture annulee.',
            'invoice_deleted' => 'Facture supprimee avec succes.',
            'payment_recorded' => 'Paiement enregistre.',
            'invoices_merged' => 'Factures fusionnees avec succes.',
        ];

        return $messages[$code] ?? '';
    }

    private function resolveError(string $code): string
    {
        $messages = [
            'auth_required' => 'Session invalide. Reconnectez-vous.',
            'permission_denied' => 'Action refusee: permissions insuffisantes sur les ventes.',
            'invalid_payload' => 'Veuillez verifier les champs de la facture.',
            'client_phone_required' => 'Nom ou numero de telephone obligatoire pour un client connu.',
            'out_of_stock' => 'Facture refusee: un ou plusieurs produits sont en rupture ou insuffisants.',
            'invoice_create_failed' => 'Impossible de creer la facture pour le moment.',
            'invoice_update_failed' => 'Impossible de mettre a jour la facture.',
            'draft_only' => 'Action reservee aux brouillons.',
            'editable_only' => 'Action reservee aux brouillons.',
            'cancel_not_allowed' => 'Annulation non autorisee pour cette facture.',
            'invalid_payment' => 'Paiement invalide pour cette facture.',
            'invoice_not_found' => 'Facture introuvable.',
            'invoice_delete_not_allowed' => 'Suppression non autorisee: supprimez uniquement un brouillon non encaisse.',
            'invalid_merge_selection' => 'Selection invalide: choisissez au moins deux factures eligibles.',
            'invoice_merge_failed' => 'Impossible de fusionner les factures pour le moment.',
        ];

        return $messages[$code] ?? '';
    }

    private function resolveInvalidPayloadErrorCode(\InvalidArgumentException $exception): string
    {
        $message = strtolower(trim($exception->getMessage()));
        if (str_contains($message, 'numero de telephone') || str_contains($message, 'nom ou numero')) {
            return 'client_phone_required';
        }
        if (str_contains($message, 'rupture de stock') || str_contains($message, 'stock insuffisant')) {
            return 'out_of_stock';
        }

        return 'invalid_payload';
    }
}
