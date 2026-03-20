<?php
/**
 * Symphony - Point d'entrée unique
 * Toutes les requêtes passent par ici (URL rewriting)
 */

define('SYMPHONY_ACCESS', true);
define('ROOT_PATH', __DIR__);
define('APP_PATH', ROOT_PATH . '/app');
define('APP_REQUEST_START', microtime(true));

// Chargement de la configuration
require_once ROOT_PATH . '/config.php';

// Autoloader maison (pas de Composer)
spl_autoload_register(function ($class) {
    // Convertir App\Controleurs\X en app/controllers/X.php
    $prefix = 'App\\';
    $base_dir = APP_PATH . '/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);

    // 1) Tentative standard (App/Core/X.php)
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
        return;
    }

    // 2) Compatibilité Linux: premier segment en minuscules (app/core/X.php)
    $parts = explode('\\', $relative_class);
    if (!empty($parts)) {
        $parts[0] = strtolower($parts[0]);
        $file = $base_dir . implode('/', $parts) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});

// Chargement du routeur
require_once ROOT_PATH . '/router.php';

// Démarrage de la session
App\Core\Session::start();

if (class_exists('\\App\\Core\\AppLogger')) {
    App\Core\AppLogger::info('HTTP request start', [
        'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
        'uri' => (string) ($_SERVER['REQUEST_URI'] ?? '/'),
        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        'user_id' => (int) (App\Core\Session::get('user', [])['id'] ?? 0),
    ]);
}

// Protection CSRF automatique
App\Core\Security::validateCSRF();

// Routing
$router = new Router();
$router->dispatch($_SERVER['REQUEST_URI']);
