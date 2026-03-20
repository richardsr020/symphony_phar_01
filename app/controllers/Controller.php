<?php

namespace App\Controllers;

use App\Core\Security;

abstract class Controller
{
    protected function renderMain(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);

        ob_start();
        include APP_PATH . '/views/pages/' . $view . '.php';
        $content = ob_get_clean();

        if (!isset($title) || $title === '') {
            $title = 'Kombiphar';
        }

        include APP_PATH . '/views/layouts/main.php';
    }

    protected function renderAuth(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $csrfToken = Security::generateCSRF();

        ob_start();
        include APP_PATH . '/views/pages/auth/' . $view . '.php';
        $content = ob_get_clean();

        include APP_PATH . '/views/layouts/auth.php';
    }

    protected function renderProviderAuth(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $csrfToken = Security::generateCSRF();

        ob_start();
        include APP_PATH . '/views/pages/provider/' . $view . '.php';
        $content = ob_get_clean();

        include APP_PATH . '/views/layouts/provider-auth.php';
    }

    protected function renderProvider(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $csrfToken = Security::generateCSRF();

        ob_start();
        include APP_PATH . '/views/pages/provider/' . $view . '.php';
        $content = ob_get_clean();

        include APP_PATH . '/views/layouts/provider-main.php';
    }

    protected function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }
}
