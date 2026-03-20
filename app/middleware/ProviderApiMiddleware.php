<?php

namespace App\Middleware;

use App\Models\ProviderApiKey;

class ProviderApiMiddleware
{
    public static function handle(): bool
    {
        $providedKey = self::resolveApiKey();

        if ($providedKey === '') {
            self::deny('Clé API fournisseur manquante.');
            return false;
        }

        $model = new ProviderApiKey();
        $apiKey = $model->findActiveByRawKey($providedKey);

        if ($apiKey === null) {
            self::deny('Clé API fournisseur invalide.');
            return false;
        }

        $model->touchLastUsed((int) $apiKey['id']);
        $_SERVER['PROVIDER_API_KEY_ID'] = (string) $apiKey['id'];

        return true;
    }

    private static function resolveApiKey(): string
    {
        $header = trim((string) ($_SERVER['HTTP_X_PROVIDER_KEY'] ?? ''));
        if ($header !== '') {
            return $header;
        }

        $authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
        if (stripos($authorization, 'Bearer ') === 0) {
            return trim(substr($authorization, 7));
        }

        return '';
    }

    private static function deny(string $message): void
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    }
}
