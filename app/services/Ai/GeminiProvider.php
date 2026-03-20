<?php

namespace App\Services\Ai;

use App\Core\AppLogger;

class GeminiProvider
{
    private ?array $lastError = null;

    public function getLastError(): ?array
    {
        return $this->lastError;
    }

    public function generateText(string $systemPrompt, string $userPrompt): ?string
    {
        $this->lastError = null;
        $providers = \Config::AI_PROVIDERS ?? [];
        $gemini = is_array($providers['gemini'] ?? null) ? $providers['gemini'] : [];
        $apiKey = trim((string) ($gemini['api_key'] ?? ''));
        $apiUrl = trim((string) ($gemini['api_url'] ?? ''));

        if ($apiKey === '' || $apiUrl === '' || !function_exists('curl_init')) {
            $this->lastError = [
                'code' => 'provider_prerequisites_missing',
                'http_code' => null,
            ];
            AppLogger::warning('Gemini unavailable due to local prerequisites', [
                'has_api_key' => $apiKey !== '',
                'has_api_url' => $apiUrl !== '',
                'has_curl' => function_exists('curl_init'),
            ]);
            return null;
        }

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $systemPrompt . "\n\n" . $userPrompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'topP' => 0.8,
                'maxOutputTokens' => 500,
            ],
        ];

        $url = $apiUrl . (strpos($apiUrl, '?') !== false ? '&' : '?') . 'key=' . urlencode($apiKey);
        $ch = curl_init($url);
        if ($ch === false) {
            $this->lastError = [
                'code' => 'curl_init_failed',
                'http_code' => null,
            ];
            AppLogger::error('Gemini curl_init failed');
            return null;
        }

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

$raw = curl_exec($ch);

if (!is_string($raw) || $raw === '') {
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);

    // curl_close() déprécié → on l'appelle avec @ pour supprimer l'avertissement
    @curl_close($ch);

    $this->lastError = [
        'code' => 'request_failed_before_response',
        'http_code' => null,
    ];
    AppLogger::error('Gemini request failed before response', [
        'curl_errno' => $curlErrno,
        'curl_error' => $curlError,
    ]);
    return null;
}

$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Fermeture sécurisée et sans avertissement
@curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            $this->lastError = [
                'code' => 'http_non_2xx',
                'http_code' => $httpCode,
            ];
            AppLogger::error('Gemini API returned non-2xx', [
                'http_code' => $httpCode,
                'response_snippet' => substr($raw, 0, 1000),
                'api_url' => $apiUrl,
                'api_key' => $apiKey,
            ]);
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $this->lastError = [
                'code' => 'invalid_json_response',
                'http_code' => $httpCode,
            ];
            AppLogger::error('Gemini response is not valid JSON', [
                'response_snippet' => substr($raw, 0, 1000),
            ]);
            return null;
        }

        $text = (string) ($decoded['candidates'][0]['content']['parts'][0]['text'] ?? '');
        $text = trim($text);
        if ($text === '') {
            $this->lastError = [
                'code' => 'empty_text_response',
                'http_code' => $httpCode,
            ];
            AppLogger::warning('Gemini returned empty text', [
                'response_snippet' => substr($raw, 0, 1000),
            ]);
            return null;
        }

        AppLogger::info('Gemini response generated', [
            'http_code' => $httpCode,
            'text_length' => strlen($text),
        ]);

        return $text;
    }
}
