<?php

namespace App\Core;

class Security
{
    public static function generateCSRF(): string
    {
        Session::start();

        $tokenName = defined('Config::CSRF_TOKEN_NAME') ? \Config::CSRF_TOKEN_NAME : 'csrf_token';

        if (!Session::has($tokenName)) {
            Session::set($tokenName, bin2hex(random_bytes(32)));
        }

        return (string) Session::get($tokenName);
    }

    public static function validateCSRF(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if (strpos($uri, '/api/provider/') === 0) {
            return;
        }

        Session::start();

        $tokenName = defined('Config::CSRF_TOKEN_NAME') ? \Config::CSRF_TOKEN_NAME : 'csrf_token';
        $sessionToken = (string) Session::get($tokenName, '');

        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $postToken = $_POST[$tokenName] ?? '';
        $jsonToken = '';

        if ($headerToken === '' && $postToken === '') {
            $raw = file_get_contents('php://input');
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded[$tokenName])) {
                    $jsonToken = (string) $decoded[$tokenName];
                }
            }
        }

        $providedToken = (string) ($headerToken ?: $postToken ?: $jsonToken);

        if ($sessionToken === '' || $providedToken === '' || !hash_equals($sessionToken, $providedToken)) {
            http_response_code(419);
            header('Content-Type: text/html; charset=utf-8');
            echo 'Erreur CSRF: token invalide ou expiré.';
            exit;
        }
    }
}
