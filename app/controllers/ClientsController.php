<?php

namespace App\Controllers;

use App\Core\AppLogger;
use App\Core\RolePermissions;
use App\Core\Session;
use App\Models\Client;

class ClientsController extends Controller
{
    public function store(): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $userId = (int) ($sessionUser['id'] ?? 0);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0 || $userId <= 0) {
            $this->redirect('/login');
        }
        if (!RolePermissions::canAccessInvoices($role)) {
            $this->redirect('/suivi-clients?error=clients_forbidden');
        }

        try {
            $clientId = (new Client())->createFromPayload($companyId, $userId, $_POST);
            $this->redirect('/suivi-clients?success=client_created');
        } catch (\InvalidArgumentException $exception) {
            $this->redirect('/suivi-clients?error=client_invalid');
        } catch (\Throwable $exception) {
            AppLogger::error('Client creation failed', [
                'company_id' => $companyId,
                'user_id' => $userId,
                'message' => $exception->getMessage(),
            ]);
            $this->redirect('/suivi-clients?error=client_create_failed');
        }
    }
}
