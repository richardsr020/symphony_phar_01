<?php

namespace App\Controllers;

use App\Core\RolePermissions;
use App\Core\Session;

class ChatController extends Controller
{
    public function index(): void
    {
        if (!defined('Config::AI_ENABLED') || \Config::AI_ENABLED !== true) {
            $this->redirect('/dashboard?error=feature_disabled');
        }

        $sessionUser = Session::get('user', []);
        $role = RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));
        if (!RolePermissions::canAccessChat($role)) {
            $this->redirect('/dashboard?error=permission_denied');
        }

        $this->renderMain('chat', [
            'title' => 'Symphony IA',
        ]);
    }
}
