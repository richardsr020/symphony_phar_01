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
            background: linear-gradient(145deg, #101726, #1b2b4a);
            min-height: 100vh;
            font-family: Inter, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
        }
        .provider-auth-card {
            width: 100%;
            max-width: 430px;
            background: #ffffff;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 22px 60px rgba(0, 0, 0, 0.25);
        }
        .provider-auth-title {
            margin: 0 0 6px;
            font-size: 24px;
        }
        .provider-auth-subtitle {
            margin: 0 0 24px;
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="provider-auth-card">
        <h1 class="provider-auth-title"><?= htmlspecialchars($title ?? 'Connexion fournisseur', ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="provider-auth-subtitle"><?= htmlspecialchars($subtitle ?? '', ENT_QUOTES, 'UTF-8') ?></p>
        <?= $content ?>
    </div>
    <script src="<?= htmlspecialchars($asset('/public/js/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
