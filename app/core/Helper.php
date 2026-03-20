<?php

namespace App\Core;

class Helper
{
    public static function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }

    public static function routeStartsWith(string $prefix): bool
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        return strpos($uri, $prefix) === 0;
    }
}
