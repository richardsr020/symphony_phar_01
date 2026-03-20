<?php
$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
$basePath = rtrim(str_replace('/index.php', '', $scriptName), '/');
$asset = static function (string $path) use ($basePath): string {
    return ($basePath === '' ? '' : $basePath) . $path;
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $csrfToken ?? '' ?>">
    <title><?= htmlspecialchars($title ?? 'Provider', ENT_QUOTES, 'UTF-8') ?> - NestCorporation</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($asset('/public/css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script>
        window.tailwind = window.tailwind || {};
        window.tailwind.config = { corePlugins: { preflight: false } };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            margin: 0;
            background: #f3f5fa;
            font-family: Inter, sans-serif;
            color: #111827;
        }
        .provider-shell {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px 16px 36px;
        }
        .provider-topbar {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .provider-topbar h1 {
            margin: 0 0 4px;
            font-size: 26px;
        }
        .provider-topbar p {
            margin: 0;
            color: #6b7280;
        }
        .provider-user {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        .provider-user a {
            text-decoration: none;
            background: #111827;
            color: #fff;
            padding: 8px 11px;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="provider-shell">
        <header class="provider-topbar">
            <div>
                <h1><?= htmlspecialchars($title ?? 'Pilotage fournisseur', ENT_QUOTES, 'UTF-8') ?></h1>
                <p><?= htmlspecialchars($subtitle ?? '', ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="provider-user">
                <span><?= htmlspecialchars((string) (($providerUser['full_name'] ?? '') !== '' ? $providerUser['full_name'] : 'Opérateur'), ENT_QUOTES, 'UTF-8') ?></span>
                <a href="/provider/logout">Déconnexion</a>
            </div>
        </header>

        <?= $content ?>
    </div>
    <script src="<?= htmlspecialchars($asset('/public/js/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
