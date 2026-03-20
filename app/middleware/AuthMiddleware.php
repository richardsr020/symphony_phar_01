<?php

namespace App\Middleware;

use App\Core\RolePermissions;
use App\Core\Session;
use App\Models\Company;
use App\Models\User;

class AuthMiddleware
{
    public static function handle(): bool
    {
        Session::start();

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $isApiRoute = strpos($uri, '/api/') === 0;
        $sessionUser = Session::get('user');
        if (Session::get('is_authenticated') === true && is_array($sessionUser)) {
            $userId = (int) ($sessionUser['id'] ?? 0);
            $companyId = (int) ($sessionUser['company_id'] ?? 0);
            if ($userId <= 0 || $companyId <= 0) {
                self::clearSession();
                return self::denyAccess($isApiRoute, 'session_expired');
            }

            if ($uri === '/logout') {
                return true;
            }

            $userModel = new User();
            $currentUser = $userModel->findById($userId);
            if (
                $currentUser === null
                || (int) ($currentUser['is_active'] ?? 0) !== 1
                || (int) ($currentUser['company_id'] ?? 0) !== $companyId
            ) {
                self::clearSession();
                return self::denyAccess($isApiRoute, 'session_expired');
            }

            $normalizedRole = RolePermissions::normalizeRole((string) ($currentUser['role'] ?? ''));
            if ((string) ($sessionUser['role'] ?? '') !== $normalizedRole) {
                $sessionUser['role'] = $normalizedRole;
                Session::set('user', $sessionUser);
            }

            $companyModel = new Company();
            $company = $companyModel->findById($companyId);
            if ($company === null) {
                self::clearSession();
                return self::denyAccess($isApiRoute, 'session_expired');
            }

            $isLocked = ((int) ($company['app_locked'] ?? 0)) === 1
                || ((string) ($company['subscription_status'] ?? '') === 'suspended');
            if ($isLocked) {
                if ($isApiRoute) {
                    http_response_code(423);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['error' => 'Application verrouillée pour cette entreprise.']);
                    return false;
                }

                http_response_code(423);
                include APP_PATH . '/views/pages/errors/locked.php';
                return false;
            }

            return true;
        }

        return self::denyAccess($isApiRoute, 'auth_required');
    }

    private static function denyAccess(bool $isApiRoute, string $reason): bool
    {
        if ($isApiRoute) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            $message = $reason === 'session_expired'
                ? 'Session expirée. Reconnectez-vous.'
                : 'Authentification requise';
            echo json_encode(['error' => $message]);
            return false;
        }

        if ($reason === 'session_expired') {
            header('Location: /login?error=session_expired');
            return false;
        }

        header('Location: /login');
        return false;
    }

    private static function clearSession(): void
    {
        Session::destroy();
    }
}
