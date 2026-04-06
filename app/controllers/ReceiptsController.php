<?php

namespace App\Controllers;

use App\Core\RolePermissions;
use App\Core\Session;
use App\Models\Company;
use App\Models\Transaction;

class ReceiptsController extends Controller
{
    private Transaction $transactionModel;

    public function __construct()
    {
        $this->transactionModel = new Transaction();
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

        $receipt = $this->transactionModel->getDebtPaymentReceipt($companyId, $transactionId);
        if ($receipt === null) {
            $this->redirect('/transactions?error=receipt_not_found');
        }

        $this->renderMain('receipts/preview', [
            'title' => 'Recu ' . (string) ($receipt['receipt_number'] ?? ''),
            'receipt' => $receipt,
            'company' => (new Company())->findById($companyId) ?? [],
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

        $receipt = $this->transactionModel->getDebtPaymentReceipt($companyId, $transactionId);
        if ($receipt === null) {
            $this->redirect('/transactions?error=receipt_not_found');
        }

        $this->renderMain('receipts/preview', [
            'title' => 'PDF recu ' . (string) ($receipt['receipt_number'] ?? ''),
            'receipt' => $receipt,
            'company' => (new Company())->findById($companyId) ?? [],
            'autoDownload' => true,
        ]);
    }
}
