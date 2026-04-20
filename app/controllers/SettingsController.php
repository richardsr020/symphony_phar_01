<?php

namespace App\Controllers;

use App\Core\AuditLogger;
use App\Core\ExcelExporter;
use App\Core\RolePermissions;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\AiResource;
use App\Models\Company;
use App\Models\Dashboard;
use App\Models\FiscalPeriod;
use App\Models\ProductFormSettings;
use App\Models\User;

class SettingsController extends Controller
{
    public function index(): void
    {
        $sessionUser = Session::get('user', []);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));
        if (!RolePermissions::canAccessSettings($role)) {
            $this->redirect('/dashboard?error=admin_required');
        }

        $companyUsers = [];
        $company = [];
        $aiPrompts = [];
        $aiKnowledge = [];
        $auditLogs = [];
        $fiscalPeriods = [];
        $productFormConfig = [];
        $logFilters = [
            'period_id' => 0,
            'date_from' => '',
            'date_to' => '',
            'user_id' => 0,
        ];
        $logExportUrl = '/settings/logs/export';

        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        if ($companyId > 0) {
            $companyModel = new Company();
            $company = $companyModel->findById($companyId) ?? [];
        }

        if (RolePermissions::canAccessSettings($role) && $companyId > 0) {
            $userModel = new User();
            $companyUsers = $userModel->getUsersByCompany($companyId);
            $aiResourceModel = new AiResource();
            $aiResourceModel->ensureDefaultsForCompany($companyId);
            $aiPrompts = $aiResourceModel->getByType($companyId, 'prompt');
            $aiKnowledge = $aiResourceModel->getByType($companyId, 'knowledge');
            $fiscalPeriods = (new FiscalPeriod())->getByCompany($companyId);
            [$logFilters, $effectiveFrom, $effectiveTo] = $this->resolveAuditLogFilters($companyId);
            $auditLogs = (new AuditLog())->getByCompanyFiltered($companyId, [
                'date_from' => $effectiveFrom,
                'date_to' => $effectiveTo,
                'user_id' => (int) ($logFilters['user_id'] ?? 0),
            ], 200);
            $productFormConfig = (new ProductFormSettings())->getForCompany($companyId);
            $logExportUrl = '/settings/logs/export';
            $exportQuery = array_filter([
                'tab' => 'users',
                'log_period_id' => (string) ($logFilters['period_id'] ?? ''),
                'log_date_from' => (string) ($logFilters['date_from'] ?? ''),
                'log_date_to' => (string) ($logFilters['date_to'] ?? ''),
                'log_user_id' => (string) ($logFilters['user_id'] ?? ''),
            ], static fn(string $value): bool => $value !== '');
            if ($exportQuery !== []) {
                $logExportUrl .= '?' . http_build_query($exportQuery);
            }
        }

        $this->renderMain('settings', [
            'title' => 'Paramètres',
            'currentUser' => $sessionUser,
            'company' => $company,
            'companyUsers' => $companyUsers,
            'aiPrompts' => $aiPrompts,
            'aiKnowledge' => $aiKnowledge,
            'auditLogs' => $auditLogs,
            'fiscalPeriods' => $fiscalPeriods,
            'productFormConfig' => $productFormConfig,
            'logFilters' => $logFilters,
            'logExportUrl' => $logExportUrl,
        ]);
    }

    public function exportLogs(): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0) {
            $this->redirect('/settings?tab=users&error=company_required');
        }
        if (!RolePermissions::canAccessSettings($role)) {
            $this->redirect('/dashboard?error=admin_required');
        }

        [$logFilters, $effectiveFrom, $effectiveTo] = $this->resolveAuditLogFilters($companyId);
        $logs = (new AuditLog())->getByCompanyFiltered($companyId, [
            'date_from' => $effectiveFrom,
            'date_to' => $effectiveTo,
            'user_id' => (int) ($logFilters['user_id'] ?? 0),
        ], null);

        $headers = [
            'Date',
            'Utilisateur',
            'Action',
            'Table',
            'ID',
            'IP',
            'Details avant',
            'Details apres',
            'User agent',
        ];

        $rows = [];
        foreach ($logs as $log) {
            $fullName = trim((string) (($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')));
            if ($fullName === '') {
                $fullName = (string) ($log['matricule'] ?? ($log['email'] ?? 'Utilisateur supprime'));
            }
            $rows[] = [
                (string) ($log['created_at'] ?? ''),
                $fullName,
                (string) ($log['action'] ?? ''),
                (string) ($log['table_name'] ?? ''),
                (string) ($log['record_id'] ?? ''),
                (string) ($log['ip_address'] ?? ''),
                (string) ($log['old_data'] ?? ''),
                (string) ($log['new_data'] ?? ''),
                (string) ($log['user_agent'] ?? ''),
            ];
        }

        ExcelExporter::download('logs_activites', $headers, $rows);
    }

    private function resolveAuditLogFilters(int $companyId): array
    {
        $filters = [
            'period_id' => (int) ($_GET['log_period_id'] ?? 0),
            'date_from' => (string) ($_GET['log_date_from'] ?? ''),
            'date_to' => (string) ($_GET['log_date_to'] ?? ''),
            'user_id' => (int) ($_GET['log_user_id'] ?? 0),
        ];

        $effectiveFrom = $this->normalizeDateInput($filters['date_from'] ?? '');
        $effectiveTo = $this->normalizeDateInput($filters['date_to'] ?? '');

        if ($filters['period_id'] > 0) {
            $period = (new FiscalPeriod())->findByIdForCompany($companyId, $filters['period_id']);
            if (is_array($period)) {
                $periodStart = $this->normalizeDateInput((string) ($period['start_date'] ?? ''));
                $periodEnd = $this->normalizeDateInput((string) ($period['end_date'] ?? ''));
                if ($periodStart !== null && ($effectiveFrom === null || $effectiveFrom < $periodStart)) {
                    $effectiveFrom = $periodStart;
                }
                if ($periodEnd !== null && ($effectiveTo === null || $effectiveTo > $periodEnd)) {
                    $effectiveTo = $periodEnd;
                }
            }
        }

        return [$filters, $effectiveFrom, $effectiveTo];
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

    public function updateCompany(): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);

        if ($companyId <= 0) {
            $this->redirect('/settings?tab=company&error=company_required');
        }

        if (!RolePermissions::canAccessSettings((string) ($sessionUser['role'] ?? ''))) {
            $this->redirect('/settings?tab=company&error=admin_required');
        }

        try {
            $logoPath = $this->handleImageUpload($_FILES['invoice_logo_file'] ?? null, 'company-logos', 'company-' . $companyId);
            if ($logoPath !== null) {
                $_POST['invoice_logo_url'] = $logoPath;
            }

            $companyModel = new Company();
            $companyModel->updateSettings($companyId, $_POST);
            AuditLogger::log((int) ($sessionUser['id'] ?? 0), 'company_updated', 'companies', $companyId);

            $updatedCompany = $companyModel->findById($companyId);
            if ($updatedCompany !== null) {
                $sessionUser['company_name'] = (string) ($updatedCompany['name'] ?? ($sessionUser['company_name'] ?? ''));
                Session::set('user', $sessionUser);
            }
        } catch (\InvalidArgumentException $exception) {
            $this->redirect('/settings?tab=company&error=invalid_company_payload');
        } catch (\Throwable $exception) {
            $this->redirect('/settings?tab=company&error=company_update_failed');
        }

        $this->redirect('/settings?tab=company&saved=company');
    }

    public function updateProfile(): void
    {
        $sessionUser = Session::get('user', []);
        $userId = (int) ($sessionUser['id'] ?? 0);

        if ($userId <= 0) {
            $this->redirect('/settings?tab=profile&error=auth_required');
        }

        try {
            $avatarPath = $this->handleImageUpload($_FILES['avatar_file'] ?? null, 'avatars', 'user-' . $userId);
            if ($avatarPath !== null) {
                $_POST['avatar'] = $avatarPath;
            }

            $userModel = new User();
            $userModel->updateProfile($userId, $_POST);
            AuditLogger::log($userId, 'profile_updated', 'users', $userId);
            $updatedUser = $userModel->findById($userId);

            if ($updatedUser !== null) {
                $sessionUser['first_name'] = (string) ($updatedUser['first_name'] ?? '');
                $sessionUser['last_name'] = (string) ($updatedUser['last_name'] ?? '');
                $sessionUser['matricule'] = (string) ($updatedUser['matricule'] ?? $updatedUser['email'] ?? '');
                $sessionUser['language'] = (string) ($updatedUser['language'] ?? ($sessionUser['language'] ?? 'fr'));
                $sessionUser['avatar'] = (string) ($updatedUser['avatar'] ?? '');
                Session::set('user', $sessionUser);

                if (isset($updatedUser['theme'])) {
                    Session::set('theme', (string) $updatedUser['theme']);
                }
            }
        } catch (\InvalidArgumentException $exception) {
            $this->redirect('/settings?tab=profile&error=invalid_profile_payload');
        } catch (\Throwable $exception) {
            $this->redirect('/settings?tab=profile&error=profile_update_failed');
        }

        $this->redirect('/settings?tab=profile&saved=profile');
    }

    private function handleImageUpload($file, string $subDir, string $prefix): ?string
    {
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Upload image invalide.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new \InvalidArgumentException('Fichier image introuvable.');
        }

        $maxSize = (int) (\Config::MAX_UPLOAD_SIZE ?? 5242880);
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxSize) {
            throw new \InvalidArgumentException('Taille image invalide.');
        }

        $mime = mime_content_type($tmpName) ?: '';
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
        ];
        if (!isset($allowedMimes[$mime])) {
            throw new \InvalidArgumentException('Format image non supporte.');
        }

        $ext = $allowedMimes[$mime];
        $baseDir = ROOT_PATH . '/public/uploads/' . $subDir;
        if (!is_dir($baseDir) && !@mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
            throw new \RuntimeException('Impossible de creer le dossier upload.');
        }

        $filename = $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $targetPath = $baseDir . '/' . $filename;
        if (!move_uploaded_file($tmpName, $targetPath)) {
            throw new \RuntimeException('Echec enregistrement image.');
        }

        return '/public/uploads/' . $subDir . '/' . $filename;
    }

    public function updateFiscal(): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);

        if ($companyId <= 0) {
            $this->redirect('/settings?tab=fiscal&error=company_required');
        }

        if (!RolePermissions::canAccessSettings((string) ($sessionUser['role'] ?? ''))) {
            $this->redirect('/settings?tab=fiscal&error=admin_required');
        }

        $startDate = trim((string) ($_POST['fiscal_year_start'] ?? ''));
        $duration = (int) ($_POST['fiscal_period_duration_months'] ?? 12);

        try {
            $fiscalModel = new FiscalPeriod();
            $fiscalModel->configureCompany($companyId, $startDate, $duration);
            (new Dashboard())->invalidateDashboardCache($companyId);
            AuditLogger::log((int) ($sessionUser['id'] ?? 0), 'fiscal_period_updated', 'companies', $companyId, null, [
                'fiscal_year_start' => $startDate,
                'duration_months' => $duration,
            ]);
        } catch (\InvalidArgumentException $exception) {
            $this->redirect('/settings?tab=fiscal&error=invalid_fiscal_payload');
        } catch (\Throwable $exception) {
            $this->redirect('/settings?tab=fiscal&error=fiscal_update_failed');
        }

        $this->redirect('/settings?tab=fiscal&saved=fiscal');
    }

    public function createUser(): void
    {
        $sessionUser = Session::get('user', []);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if (!RolePermissions::canAccessSettings($role)) {
            $this->redirect('/settings?tab=team&error=admin_required');
        }

        $matricule = trim((string) ($_POST['matricule'] ?? ''));
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $newUserRoleRaw = strtolower(trim((string) ($_POST['role'] ?? RolePermissions::defaultRole())));
        if ($newUserRoleRaw === RolePermissions::LEGACY_ROLE_USER) {
            $newUserRoleRaw = RolePermissions::ROLE_CASHIER;
        }
        if (!in_array($newUserRoleRaw, RolePermissions::allowedRoles(), true)) {
            $this->redirect('/settings?tab=team&error=invalid_role');
        }
        $newUserRole = $newUserRoleRaw;

        if ($matricule === '' || $firstName === '' || $lastName === '' || $password === '') {
            $this->redirect('/settings?tab=team&error=missing_fields');
        }

        if (strlen($password) < 8) {
            $this->redirect('/settings?tab=team&error=weak_password');
        }

        if ($password !== $confirmPassword) {
            $this->redirect('/settings?tab=team&error=password_mismatch');
        }

        if (!isset($sessionUser['company_id'])) {
            $this->redirect('/settings?tab=team&error=company_required');
        }

        $userModel = new User();
        if ($userModel->findByMatricule($matricule) !== null) {
            $this->redirect('/settings?tab=team&error=matricule_taken');
        }

        try {
            $newUserId = $userModel->createUserForCompany((int) $sessionUser['company_id'], [
                'matricule' => $matricule,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'password' => $password,
                'role' => $newUserRole,
            ]);
            AuditLogger::log((int) ($sessionUser['id'] ?? 0), 'user_created', 'users', $newUserId, null, [
                'matricule' => $matricule,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'role' => $newUserRole,
            ]);
        } catch (\Throwable $exception) {
            $this->redirect('/settings?tab=team&error=user_create_failed');
        }

        $this->redirect('/settings?tab=team&saved=user_created');
    }

    public function updateUserStatus($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $currentUserId = (int) ($sessionUser['id'] ?? 0);
        $targetUserId = (int) $id;
        $isAdmin = RolePermissions::canAccessSettings((string) ($sessionUser['role'] ?? ''));

        if (!$isAdmin || $companyId <= 0 || $targetUserId <= 0) {
            $this->redirect('/settings?tab=users&error=admin_required');
        }

        $userModel = new User();
        $targetUser = $userModel->findById($targetUserId);
        if ($targetUser === null || (int) ($targetUser['company_id'] ?? 0) !== $companyId) {
            $this->redirect('/settings?tab=users&error=user_not_found');
        }

        $targetIsActive = isset($_POST['is_active']) && (string) $_POST['is_active'] === '1';
        $targetRole = RolePermissions::normalizeRole((string) ($targetUser['role'] ?? ''));
        if (!$targetIsActive && $targetRole === RolePermissions::ROLE_ADMIN) {
            $activeAdmins = $userModel->countActiveAdminsByCompany($companyId);
            if ($activeAdmins <= 1) {
                $this->redirect('/settings?tab=users&error=last_admin_required');
            }
        }
        if ($targetUserId === $currentUserId && !$targetIsActive && $targetRole !== RolePermissions::ROLE_ADMIN) {
            $this->redirect('/settings?tab=users&error=cannot_disable_self');
        }

        $userModel->updateStatusForCompany($companyId, $targetUserId, $targetIsActive);
        AuditLogger::log($currentUserId, 'user_status_updated', 'users', $targetUserId, null, [
            'is_active' => $targetIsActive ? 1 : 0,
        ]);
        $this->redirect('/settings?tab=users&saved=user_status_updated');
    }

    public function updateUserRole($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $currentUserId = (int) ($sessionUser['id'] ?? 0);
        $targetUserId = (int) $id;
        $isAdmin = RolePermissions::canAccessSettings((string) ($sessionUser['role'] ?? ''));

        if (!$isAdmin || $companyId <= 0 || $targetUserId <= 0) {
            $this->redirect('/settings?tab=users&error=admin_required');
        }

        $newRoleRaw = strtolower(trim((string) ($_POST['role'] ?? RolePermissions::defaultRole())));
        if ($newRoleRaw === RolePermissions::LEGACY_ROLE_USER) {
            $newRoleRaw = RolePermissions::ROLE_CASHIER;
        }
        $allowedRoles = RolePermissions::allowedRoles();
        if (!in_array($newRoleRaw, $allowedRoles, true)) {
            $this->redirect('/settings?tab=users&error=invalid_role');
        }
        $newRole = $newRoleRaw;

        if ($targetUserId === $currentUserId && $newRole !== RolePermissions::ROLE_ADMIN) {
            $this->redirect('/settings?tab=users&error=cannot_downgrade_self');
        }

        $userModel = new User();
        $targetUser = $userModel->findById($targetUserId);
        if ($targetUser === null || (int) ($targetUser['company_id'] ?? 0) !== $companyId) {
            $this->redirect('/settings?tab=users&error=user_not_found');
        }

        $currentRole = RolePermissions::normalizeRole((string) ($targetUser['role'] ?? ''));
        if ($currentRole === RolePermissions::ROLE_ADMIN && $newRole !== RolePermissions::ROLE_ADMIN) {
            $activeAdmins = $userModel->countActiveAdminsByCompany($companyId);
            if (((int) ($targetUser['is_active'] ?? 0) === 1) && $activeAdmins <= 1) {
                $this->redirect('/settings?tab=users&error=last_admin_required');
            }
        }

        $userModel->updateRoleForCompany($companyId, $targetUserId, $newRole);
        AuditLogger::log($currentUserId, 'user_role_updated', 'users', $targetUserId, null, [
            'role' => $newRole,
        ]);
        $this->redirect('/settings?tab=users&saved=user_role_updated');
    }

    public function resetUserPassword($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $targetUserId = (int) $id;
        $isAdmin = RolePermissions::canAccessSettings((string) ($sessionUser['role'] ?? ''));

        if (!$isAdmin || $companyId <= 0 || $targetUserId <= 0) {
            $this->redirect('/settings?tab=users&error=admin_required');
        }

        $password = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if (strlen($password) < 8) {
            $this->redirect('/settings?tab=users&error=weak_password');
        }
        if ($password !== $confirm) {
            $this->redirect('/settings?tab=users&error=password_mismatch');
        }

        $userModel = new User();
        $targetUser = $userModel->findById($targetUserId);
        if ($targetUser === null || (int) ($targetUser['company_id'] ?? 0) !== $companyId) {
            $this->redirect('/settings?tab=users&error=user_not_found');
        }

        $userModel->resetPasswordForCompany($companyId, $targetUserId, $password);
        AuditLogger::log((int) ($sessionUser['id'] ?? 0), 'user_password_reset', 'users', $targetUserId);
        $this->redirect('/settings?tab=users&saved=user_password_reset');
    }

    public function deleteUser($id): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $currentUserId = (int) ($sessionUser['id'] ?? 0);
        $targetUserId = (int) $id;
        $isAdmin = RolePermissions::canAccessSettings((string) ($sessionUser['role'] ?? ''));
        $returnTab = trim((string) ($_POST['return_tab'] ?? 'users'));
        if (!in_array($returnTab, ['team', 'users'], true)) {
            $returnTab = 'users';
        }

        if (!$isAdmin || $companyId <= 0 || $targetUserId <= 0) {
            $this->redirect('/settings?tab=' . $returnTab . '&error=admin_required');
        }

        $userModel = new User();
        $targetUser = $userModel->findById($targetUserId);
        if ($targetUser === null || (int) ($targetUser['company_id'] ?? 0) !== $companyId) {
            $this->redirect('/settings?tab=' . $returnTab . '&error=user_not_found');
        }

        $targetRole = RolePermissions::normalizeRole((string) ($targetUser['role'] ?? ''));
        if ($targetRole === RolePermissions::ROLE_ADMIN) {
            $this->redirect('/settings?tab=' . $returnTab . '&error=admin_delete_forbidden');
        }
        if ($targetUserId === $currentUserId && $targetRole !== RolePermissions::ROLE_ADMIN) {
            $this->redirect('/settings?tab=' . $returnTab . '&error=cannot_delete_self');
        }

        try {
            $userModel->deleteForCompany($companyId, $targetUserId);
            AuditLogger::log($currentUserId, 'user_deleted', 'users', $targetUserId);
        } catch (\Throwable $exception) {
            $this->redirect('/settings?tab=' . $returnTab . '&error=user_delete_failed');
        }

        $this->redirect('/settings?tab=' . $returnTab . '&saved=user_deleted');
    }

    public function updateAi(): void
    {
        $this->redirect('/settings?tab=security');
    }

    public function updateStockForm(): void
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));

        if ($companyId <= 0) {
            $this->redirect('/settings?tab=stock&error=company_required');
        }
        if (!RolePermissions::canAccessSettings($role)) {
            $this->redirect('/settings?tab=stock&error=admin_required');
        }

        try {
            $config = ProductFormSettings::fromPost($_POST);
            (new ProductFormSettings())->saveForCompany($companyId, $config);
            AuditLogger::log((int) ($sessionUser['id'] ?? 0), 'stock_form_updated', 'company_product_form_settings', $companyId);
        } catch (\InvalidArgumentException $exception) {
            $this->redirect('/settings?tab=stock&error=invalid_stock_form_payload');
        } catch (\Throwable $exception) {
            $this->redirect('/settings?tab=stock&error=stock_form_update_failed');
        }

        $this->redirect('/settings?tab=stock&saved=stock_form');
    }
}
