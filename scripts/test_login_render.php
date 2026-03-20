<?php

declare(strict_types=1);

define('SYMPHONY_ACCESS', true);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

require_once __DIR__ . '/../config.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = APP_PATH . '/';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($file)) {
        require_once $file;
        return;
    }

    $parts = explode('\\', $relativeClass);
    if ($parts !== []) {
        $parts[0] = strtolower($parts[0]);
        $fallback = $baseDir . implode('/', $parts) . '.php';
        if (is_file($fallback)) {
            require_once $fallback;
        }
    }
});

$controller = new App\Controllers\AuthController();
$controller->showLogin();
