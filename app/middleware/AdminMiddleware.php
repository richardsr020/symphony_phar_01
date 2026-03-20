<?php

namespace App\Middleware;

use App\Core\RolePermissions;
use App\Core\Session;

class AdminMiddleware
{
    public static function handle(): bool
    {
        Session::start();

        $user = Session::get('user');
        $isAdmin = is_array($user) && (RolePermissions::normalizeRole((string) ($user['role'] ?? '')) === RolePermissions::ROLE_ADMIN);

        if ($isAdmin) {
            return true;
        }

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (strpos((string) $uri, '/api/') === 0) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Accès administrateur requis']);
            return false;
        }

        header('Location: /dashboard?error=admin_required');
        return false;
    }
}
