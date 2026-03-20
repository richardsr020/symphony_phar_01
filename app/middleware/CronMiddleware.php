<?php

namespace App\Middleware;

class CronMiddleware
{
    public static function handle(): bool
    {
        $providedToken = (string) ($_GET['token'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? '');
        $expectedToken = (string) (\Config::CRON_TOKEN ?? '');

        if ($expectedToken !== '' && hash_equals($expectedToken, $providedToken)) {
            return true;
        }

        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Token cron invalide.'], JSON_UNESCAPED_UNICODE);
        return false;
    }
}
