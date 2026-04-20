<?php
/**
 * Exporte le schéma actuel de la base SQLite vers un fichier .sql
 *
 * Usage:
 *   php export_shema.php --out=database/schema_export.sql
 *   php export_shema.php --db=database/symphony.sqlite --out=/tmp/schema.sql
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script doit être exécuté en CLI.\n");
    exit(1);
}

define('SYMPHONY_ACCESS', true);

$root = __DIR__;
$configPath = $root . '/config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "config.php introuvable: {$configPath}\n");
    exit(1);
}

require_once $configPath;

$opts = getopt('', ['db::', 'out::', 'help']);
if (isset($opts['help'])) {
    fwrite(STDOUT, "Usage:\n");
    fwrite(STDOUT, "  php export_shema.php --out=database/schema_export.sql\n");
    fwrite(STDOUT, "  php export_shema.php --db=database/symphony.sqlite --out=/tmp/schema.sql\n");
    exit(0);
}

$driver = defined('Config::DB_DRIVER') ? (string) Config::DB_DRIVER : 'sqlite';
if (strtolower($driver) !== 'sqlite') {
    fwrite(STDERR, "DB_DRIVER n'est pas sqlite (actuel: {$driver}).\n");
    exit(1);
}

$defaultDbPath = defined('Config::DB_PATH') ? (string) Config::DB_PATH : ($root . '/database/symphony.sqlite');
$dbPathRaw = (string) ($opts['db'] ?? $defaultDbPath);
$dbPath = $dbPathRaw;
if ($dbPath !== '' && $dbPath[0] !== '/' && !preg_match('~^[A-Za-z]:[\\\\/]~', $dbPath)) {
    $dbPath = $root . '/' . $dbPath;
}

if (!is_file($dbPath)) {
    fwrite(STDERR, "Base SQLite introuvable: {$dbPath}\n");
    exit(1);
}

$defaultOutPath = $root . '/database/schema_export.sql';
$outPathRaw = (string) ($opts['out'] ?? $defaultOutPath);
$outPath = $outPathRaw;
if ($outPath !== '' && $outPath[0] !== '/' && !preg_match('~^[A-Za-z]:[\\\\/]~', $outPath)) {
    $outPath = $root . '/' . $outPath;
}

$outDir = dirname($outPath);
if (!is_dir($outDir)) {
    fwrite(STDERR, "Dossier de sortie introuvable: {$outDir}\n");
    exit(1);
}

try {
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $exception) {
    fwrite(STDERR, "Connexion SQLite impossible: " . $exception->getMessage() . "\n");
    exit(1);
}

$rows = [];
try {
    $stmt = $pdo->query(
        "SELECT type, name, tbl_name, sql
         FROM sqlite_master
         WHERE sql IS NOT NULL
           AND name NOT LIKE 'sqlite_%'
         ORDER BY
           CASE type
             WHEN 'table' THEN 1
             WHEN 'index' THEN 2
             WHEN 'trigger' THEN 3
             WHEN 'view' THEN 4
             ELSE 5
           END,
           name"
    );
    $rows = $stmt ? $stmt->fetchAll() : [];
} catch (Throwable $exception) {
    fwrite(STDERR, "Lecture du schéma impossible: " . $exception->getMessage() . "\n");
    exit(1);
}

$now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
$content = '';
$content .= "-- Export du schéma SQLite\n";
$content .= "-- Source DB: {$dbPath}\n";
$content .= "-- Généré le: {$now}\n\n";
$content .= "PRAGMA foreign_keys=OFF;\n";
$content .= "BEGIN TRANSACTION;\n\n";

foreach ($rows as $row) {
    $sql = trim((string) ($row['sql'] ?? ''));
    if ($sql === '') {
        continue;
    }
    $sql = rtrim($sql, ";\n\r\t ");
    $content .= $sql . ";\n\n";
}

$content .= "COMMIT;\n";

if (@file_put_contents($outPath, $content) === false) {
    fwrite(STDERR, "Écriture impossible: {$outPath}\n");
    exit(1);
}

fwrite(STDOUT, "Schéma exporté vers: {$outPath}\n");
