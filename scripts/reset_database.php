<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script doit etre lance en CLI.\n");
    exit(1);
}

define('SYMPHONY_ACCESS', true);
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

require_once ROOT_PATH . '/config.php';

spl_autoload_register(static function (string $class): void {
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

$args = $argv ?? [];
$force = in_array('--force', $args, true);

if (!$force) {
    fwrite(STDOUT, "ATTENTION: cette operation va supprimer TOUTES les donnees.\n");
    fwrite(STDOUT, "Relancez avec --force pour confirmer.\n");
    fwrite(STDOUT, "Exemple: php scripts/reset_database.php --force\n");
    exit(1);
}

try {
    $database = \App\Core\Database::getInstance();
    $pdo = $database->getConnection();
    $driver = strtolower((string) Config::DB_DRIVER);

    $tables = [];
    if ($driver === 'sqlite') {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");
        $tables = array_map(
            static fn(array $row): string => (string) ($row['name'] ?? ''),
            $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : []
        );
        $tables = array_values(array_filter($tables, static fn(string $t): bool => $t !== ''));
    } elseif ($driver === 'mysql') {
        $stmt = $pdo->query('SHOW TABLES');
        $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_NUM) : [];
        foreach ($rows as $row) {
            $table = (string) ($row[0] ?? '');
            if ($table !== '') {
                $tables[] = $table;
            }
        }
    } else {
        throw new \RuntimeException('Driver non supporte: ' . $driver);
    }

    if ($tables === []) {
        fwrite(STDOUT, "Aucune table detectee. Base deja vide.\n");
        exit(0);
    }

    $pdo->beginTransaction();
    try {
        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = OFF');
            foreach ($tables as $table) {
                $quoted = '"' . str_replace('"', '""', $table) . '"';
                $pdo->exec('DELETE FROM ' . $quoted);
            }
            $pdo->exec('DELETE FROM sqlite_sequence');
            $pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($tables as $table) {
                $quoted = '`' . str_replace('`', '``', $table) . '`';
                $pdo->exec('TRUNCATE TABLE ' . $quoted);
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    fwrite(STDOUT, "Base nettoyee avec succes.\n");
    fwrite(STDOUT, "Tables purges: " . count($tables) . "\n");
} catch (\Throwable $exception) {
    fwrite(STDERR, "Echec du reset: " . $exception->getMessage() . "\n");
    exit(1);
}

