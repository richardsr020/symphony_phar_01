<?php

namespace App\Core;

class AppLogger
{
    public static function debug(string $message, array $context = []): void
    {
        self::write('DEBUG', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    private static function write(string $level, string $message, array $context = []): void
    {
        $logFile = defined('Config::LOG_FILE') ? (string) \Config::LOG_FILE : '';
        if ($logFile === '') {
            return;
        }

        $directory = dirname($logFile);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $normalizedContext = self::sanitizeContext($context);
        $contextJson = $normalizedContext !== [] ? json_encode($normalizedContext, JSON_UNESCAPED_UNICODE) : '{}';
        if ($contextJson === false) {
            $contextJson = '{}';
        }

        $line = sprintf(
            "[%s] [%s] %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            trim($message),
            $contextJson
        );

        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    private static function sanitizeContext(array $context): array
    {
        $sanitized = [];
        foreach ($context as $key => $value) {
            $normalizedKey = (string) $key;
            if (is_array($value)) {
                $sanitized[$normalizedKey] = self::sanitizeContext($value);
                continue;
            }

            if (is_object($value)) {
                $sanitized[$normalizedKey] = '[object:' . get_class($value) . ']';
                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $sanitized[$normalizedKey] = $value;
                continue;
            }

            $stringValue = (string) $value;
            if (stripos($normalizedKey, 'key') !== false || stripos($normalizedKey, 'token') !== false || stripos($normalizedKey, 'password') !== false) {
                $sanitized[$normalizedKey] = self::maskSecret($stringValue);
                continue;
            }
            $sanitized[$normalizedKey] = substr($stringValue, 0, 1000);
        }

        return $sanitized;
    }

    private static function maskSecret(string $secret): string
    {
        $length = strlen($secret);
        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        return substr($secret, 0, 3) . str_repeat('*', max(2, $length - 6)) . substr($secret, -3);
    }
}
