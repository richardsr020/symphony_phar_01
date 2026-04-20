<?php
/**
 * Production configuration for Symphony.
 * Copy this file over config.php on the production server.
 */

if (!defined('SYMPHONY_ACCESS')) {
    die('Acces direct interdit');
}

class Config {
    const ENV = 'production';

    const SITE_URL = 'https://selleriewinner.ct.ws';
    const SITE_NAME = ' phar';

    // Database
    const DB_DRIVER = 'sqlite';
    const DB_HOST = '127.0.0.1';
    const DB_PORT = 3306;
    const DB_NAME = 'symphony_db';
    const DB_USER = 'root';
    const DB_PASS = '';
    const DB_CHARSET = 'utf8mb4';
    const DB_PATH = __DIR__ . '/database/symphony.sqlite';
    const DB_SCHEMA_FILE = __DIR__ . '/database/schema.sql';
    const DB_AUTO_INIT = false;
    const DB_AUTO_MIGRATE = true;
    const DB_MIGRATIONS_DIR = __DIR__ . '/database/migrations';
    const DB_MIGRATION_LOCK_FILE = __DIR__ . '/storage/cache/migrations.lock';

    // Provider bootstrap should be disabled in production after first setup.
    const PROVIDER_BOOTSTRAP_ENABLED = false;
    const PROVIDER_ADMIN_EMAIL = 'provider@selleriewinner.ct.ws';
    const PROVIDER_ADMIN_PASSWORD = 'CHANGE_ME_STRONG_PASSWORD';
    const PROVIDER_ADMIN_NAME = 'Sellerie Winner';
    const PROVIDER_API_KEY_NAME = 'Production API Key';
    const PROVIDER_API_KEY = 'CHANGE_ME_PROVIDER_API_KEY';
    const PROVIDER_WEBHOOK_SECRET = 'CHANGE_ME_WEBHOOK_SECRET';
    const SUBSCRIPTION_LICENSE_LEAD_DAYS = 15;
    const SUBSCRIPTION_REMINDER_START_DAYS = 5;
    const SUBSCRIPTION_DEFAULT_REMINDER_INTERVAL_DAYS = 1;
    const SUBSCRIPTION_RENEWAL_DAYS = 30;
    const CRON_TOKEN = 'CHANGE_ME_CRON_TOKEN';

    // Security
    const SESSION_NAME = 'symphony_session';
    const SESSION_LIFETIME = 7200;
    const CSRF_TOKEN_NAME = 'csrf_token';
    const PASSWORD_COST = 12;

    // App
    const TIMEZONE = 'Africa/Kinshasa';
    const LOCALE = 'fr_FR';
    const CURRENCY = 'USD';
    const CURRENCY_SYMBOL = '$';
    const DATE_FORMAT = 'd/m/Y';
    const DATETIME_FORMAT = 'd/m/Y H:i';

    const DEFAULT_VAT_RATE = 16;

    // Uploads
    const MAX_UPLOAD_SIZE = 5242880;
    const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf', 'xlsx'];

    // AI
    const AI_ENABLED = true;
    const AI_SURVEILLANCE_INTERVAL = 300;
    const AI_MAX_MEMORY_SUMMARY = 500;
    const AI_DEFAULT_PROVIDER = 'gemini';
    const AI_PROVIDERS = [
        'gemini' => [
            'api_key' => 'CHANGE_ME_GEMINI_API_KEY',
            'api_url' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent',
        ],
        'google_oauth' => [
            'client_id' => '',
            'client_secret' => '',
            'redirect_uri' => '',
        ],
    ];
    const AI_SYSTEM_PROMPTS = [
        'accountant_agent' => 'Tu es Symphony IA, agent comptable senior. Tu reponds en francais naturel, clair et professionnel. Tu es pragmatique, rigoureux et ferme: pas de flou, pas de promesses vagues, pas de speculation. Tu fournis des reponses factuelles, actionnables et explicables. Tu priorises la conformite comptable, la TVA, la tresorerie et la tracabilite.',
        'assistant_ux' => 'Tu guides pas a pas les utilisateurs qui ne maitrisent pas encore l interface. Ton style reste professionnel et direct: explique chaque action, propose des validations explicites et des prochaines etapes concretes.',
        'mcp_protocol' => 'Tu operes via mcp.v1 avec des tools. Avant toute action qui ecrit en base, demande une confirmation explicite. Retourne des containers structures: texte, stats, graphiques, actions. Si un input est incomplet, tu le dis clairement et tu demandes uniquement les informations manquantes.',
    ];

    const ITEMS_PER_PAGE = 20;

    // Logging reduced for production.
    const LOG_LEVEL = 'error';
    const LOG_FILE = __DIR__ . '/logs/app.log';

    const CACHE_ENABLED = true;
    const CACHE_DIR = __DIR__ . '/storage/cache/';

    public static function get($key, $default = null) {
        $constant = 'self::' . strtoupper($key);
        if (defined($constant)) {
            return constant($constant);
        }
        return $default;
    }

    public static function isDev() {
        return self::ENV === 'development';
    }

    public static function isProd() {
        return self::ENV === 'production';
    }

    public static function init() {
        if (defined('ROOT_PATH') && is_file(ROOT_PATH . '/app/core/AppLogger.php')) {
            require_once ROOT_PATH . '/app/core/AppLogger.php';
        }

        date_default_timezone_set(self::TIMEZONE);

        if (self::isDev()) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        } else {
            error_reporting(0);
            ini_set('display_errors', 0);
        }

        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $sessionPath = __DIR__ . '/storage/sessions';
        if (!is_dir($sessionPath)) {
            @mkdir($sessionPath, 0775, true);
        }

        if (is_dir($sessionPath) && is_writable($sessionPath)) {
            ini_set('session.save_path', $sessionPath);
        } else {
            ini_set('session.save_path', '/tmp');
        }

        ini_set('session.name', self::SESSION_NAME);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', $isHttps ? '1' : '0');
        ini_set('session.use_strict_mode', 1);
        ini_set('session.gc_maxlifetime', self::SESSION_LIFETIME);

        setlocale(LC_ALL, self::LOCALE . '.utf8', self::LOCALE);

        if (class_exists('\\App\\Core\\AppLogger')) {
            set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
                if (!(error_reporting() & $severity)) {
                    return false;
                }
                \App\Core\AppLogger::error('PHP error', [
                    'severity' => $severity,
                    'message' => $message,
                    'file' => $file,
                    'line' => $line,
                ]);
                return false;
            });

            set_exception_handler(static function (\Throwable $exception): void {
                \App\Core\AppLogger::error('Unhandled exception', [
                    'class' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => substr($exception->getTraceAsString(), 0, 4000),
                ]);
                if (PHP_SAPI === 'cli') {
                    fwrite(STDERR, 'Unhandled exception: ' . $exception->getMessage() . PHP_EOL);
                    exit(1);
                }
                http_response_code(500);
                echo 'Une erreur interne est survenue.';
                exit;
            });

            register_shutdown_function(static function (): void {
                if (PHP_SAPI === 'cli') {
                    return;
                }
                $userId = 0;
                if (class_exists('\\App\\Core\\Session')) {
                    $userId = (int) (\App\Core\Session::get('user', [])['id'] ?? 0);
                }
                \App\Core\AppLogger::info('HTTP request complete', [
                    'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
                    'uri' => (string) ($_SERVER['REQUEST_URI'] ?? '/'),
                    'status_code' => http_response_code(),
                    'duration_ms' => defined('APP_REQUEST_START') ? (int) round((microtime(true) - APP_REQUEST_START) * 1000) : null,
                    'user_id' => $userId,
                ]);

                $error = error_get_last();
                if (!is_array($error)) {
                    return;
                }
                $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
                if (!in_array((int) ($error['type'] ?? 0), $fatalTypes, true)) {
                    return;
                }
                \App\Core\AppLogger::error('Fatal shutdown error', [
                    'type' => (int) ($error['type'] ?? 0),
                    'message' => (string) ($error['message'] ?? ''),
                    'file' => (string) ($error['file'] ?? ''),
                    'line' => (int) ($error['line'] ?? 0),
                ]);
            });
        }

        if ((self::DB_AUTO_INIT || self::DB_AUTO_MIGRATE) && defined('APP_PATH')) {
            try {
                require_once APP_PATH . '/core/Database.php';
                if (self::DB_AUTO_INIT) {
                    \App\Core\Database::initializeSchema();
                    \App\Core\Database::bootstrapProviderAccess();
                }

                if (self::DB_AUTO_MIGRATE) {
                    require_once APP_PATH . '/core/MigrationContext.php';
                    require_once APP_PATH . '/core/MigrationRunner.php';
                    (new \App\Core\MigrationRunner())->runPending();
                }
            } catch (\Throwable $exception) {
                error_log('[Symphony] Erreur initialisation/migration DB: ' . $exception->getMessage());
                die('Erreur de connexion a la base de donnees.');
            }
        }
    }
}

Config::init();
