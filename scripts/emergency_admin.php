<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Ce script doit etre execute en CLI.\n";
    exit(1);
}

define('SYMPHONY_ACCESS', true);
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('APP_REQUEST_START', microtime(true));

require_once ROOT_PATH . '/config.php';

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = APP_PATH . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require $file;
        return;
    }

    $parts = explode('\\', $relativeClass);
    if ($parts !== []) {
        $parts[0] = strtolower($parts[0]);
        $file = $baseDir . implode('/', $parts) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});

use App\Core\Database;

function usage(): void
{
    $message = <<<TXT
Usage:
  php scripts/emergency_admin.php --matricule=MAT-001 --password='Secret123!' [options]

Options:
  --matricule=MAT-001
  --first=Prenom
  --last=Nom
  --company-id=ID
  --company-name="Nom de societe" (utilise si aucune societe n'existe)
  --phone="Telephone"
  --help

Comportement:
  - Si le matricule existe, le compte est passe admin et le mot de passe est remis.
  - Sinon, un nouvel admin est cree.
  - Si --company-id n'est pas fourni:
      * 1 seule societe -> utilisee automatiquement.
      * 0 societe -> une societe est creee (company-name requis).
      * >1 societe -> erreur et stop.
TXT;

    echo $message . "\n";
}

$options = getopt('', [
    'matricule:',
    'password:',
    'first::',
    'last::',
    'company-id::',
    'company-name::',
    'phone::',
    'help',
]);

if (isset($options['help'])) {
    usage();
    exit(0);
}

$matricule = trim((string) ($options['matricule'] ?? ''));
$password = (string) ($options['password'] ?? '');
$firstName = trim((string) ($options['first'] ?? ''));
$lastName = trim((string) ($options['last'] ?? ''));
$companyId = (int) ($options['company-id'] ?? 0);
$companyName = trim((string) ($options['company-name'] ?? ''));
$phone = trim((string) ($options['phone'] ?? ''));

if ($matricule === '' || $password === '') {
    usage();
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Mot de passe trop court (min 8 caracteres).\n");
    exit(1);
}

$passwordHash = password_hash(
    $password,
    PASSWORD_BCRYPT,
    ['cost' => \Config::PASSWORD_COST]
);

if ($passwordHash === false) {
    fwrite(STDERR, "Impossible de generer le hash du mot de passe.\n");
    exit(1);
}

$db = Database::getInstance();
$db->beginTransaction();

try {
    if ($companyId > 0) {
        $company = $db->fetchOne(
            'SELECT id, name FROM companies WHERE id = :id LIMIT 1',
            ['id' => $companyId]
        );
        if ($company === null) {
            throw new RuntimeException('Societe introuvable pour company-id=' . $companyId);
        }
    } else {
        $companies = $db->fetchAll('SELECT id, name FROM companies ORDER BY id ASC');
        $count = count($companies);
        if ($count === 1) {
            $companyId = (int) $companies[0]['id'];
        } elseif ($count === 0) {
            if ($companyName === '') {
                throw new RuntimeException('Aucune societe existante. Fournissez --company-name.');
            }
            $db->execute(
                'INSERT INTO companies (name, legal_name, email, phone, country, currency)
                 VALUES (:name, :legal_name, :email, :phone, :country, :currency)',
                [
                    'name' => $companyName,
                    'legal_name' => $companyName,
                    'email' => null,
                    'phone' => $phone !== '' ? $phone : null,
                    'country' => 'RDC',
                    'currency' => \Config::CURRENCY,
                ]
            );
            $companyId = $db->lastInsertId();
        } else {
            throw new RuntimeException('Plusieurs societes detectees. Fournissez --company-id.');
        }
    }

    $existing = $db->fetchOne(
        'SELECT id FROM users WHERE email = :email LIMIT 1',
        ['email' => $matricule]
    );

    if ($existing !== null) {
        $db->execute(
            'UPDATE users
             SET password_hash = :password_hash,
                 role = :role,
                 is_active = 1
             WHERE id = :id',
            [
                'password_hash' => $passwordHash,
                'role' => 'admin',
                'id' => (int) $existing['id'],
            ]
        );
        $userId = (int) $existing['id'];
        $action = 'updated';
    } else {
        $db->execute(
            'INSERT INTO users (company_id, email, password_hash, first_name, last_name, role, language, theme, is_active)
             VALUES (:company_id, :email, :password_hash, :first_name, :last_name, :role, :language, :theme, :is_active)',
            [
                'company_id' => $companyId,
                'email' => $matricule,
                'password_hash' => $passwordHash,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'role' => 'admin',
                'language' => 'fr',
                'theme' => 'light',
                'is_active' => 1,
            ]
        );
        $userId = $db->lastInsertId();
        $action = 'created';
    }

    $db->commit();

    echo "OK: admin {$action}. user_id={$userId}, company_id={$companyId}\n";
} catch (Throwable $exception) {
    $db->rollback();
    fwrite(STDERR, "Erreur: " . $exception->getMessage() . "\n");
    exit(1);
}
