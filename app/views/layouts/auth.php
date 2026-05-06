<?php
$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
$basePath = rtrim(str_replace('/index.php', '', $scriptName), '/');
$asset = static function (string $path) use ($basePath): string {
    return ($basePath === '' ? '' : $basePath) . $path;
};

$pageTitle = trim((string) ($title ?? 'Connexion'));
$pageSubtitle = trim((string) ($subtitle ?? ''));
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars((string) \Config::SITE_NAME, ENT_QUOTES, 'UTF-8') ?></title>

    <link rel="stylesheet" href="<?= htmlspecialchars($asset('/public/css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($asset('/public/css/auth.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
</head>
<body class="auth-body">
    <div class="auth-shell">
        <div class="auth-layout">
            <aside class="auth-aside">
                <div class="auth-brand">
                    <div class="auth-mark" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></div>
                    <div>
                        <h1><?= htmlspecialchars((string) \Config::SITE_NAME, ENT_QUOTES, 'UTF-8') ?></h1>
                        <p><?= htmlspecialchars($pageSubtitle !== '' ? $pageSubtitle : 'Gestion & pilotage en temps réel', ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>

                <h2><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="auth-subtitle">Une authentification simple, sécurisée et prête pour vos anciennes bases.</p>

                <ul class="auth-points">
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>Connexion uniquement avec email et mot de passe</span></li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>Migrations automatiques au démarrage</span></li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>Interface moderne, responsive et rapide</span></li>
                </ul>

                <div class="auth-aside-footer">
                    <span>© <?= (int) date('Y') ?> <?= htmlspecialchars((string) \Config::SITE_NAME, ENT_QUOTES, 'UTF-8') ?></span>
                    <a href="/terms">Conditions</a>
                </div>
            </aside>

            <main class="auth-main">
                <div class="auth-card">
                    <?= $content ?>
                    <?php if (!empty($footer)): ?>
                        <div class="auth-bottom"><?= $footer ?></div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="<?= htmlspecialchars($asset('/public/js/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script>
        document.querySelectorAll('[data-toggle-password]').forEach((button) => {
            button.addEventListener('click', () => {
                const targetId = button.getAttribute('data-toggle-password');
                const input = targetId ? document.getElementById(targetId) : null;
                if (!input) return;
                input.type = input.type === 'password' ? 'text' : 'password';
                button.setAttribute('aria-pressed', input.type !== 'password' ? 'true' : 'false');
            });
        });
    </script>
</body>
</html>
