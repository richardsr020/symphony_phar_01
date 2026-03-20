<?php
$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
$basePath = rtrim(str_replace('/index.php', '', $scriptName), '/');
$asset = static function (string $path) use ($basePath): string {
    return ($basePath === '' ? '' : $basePath) . $path;
};
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= $_SESSION['theme'] ?? 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= App\Core\Security::generateCSRF() ?>">
    <title><?= $title ?? 'Kombiphar' ?> - <?= Config::SITE_NAME ?></title>
    
    <!-- Fonts CDN -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Styles -->
    <link rel="stylesheet" href="<?= htmlspecialchars($asset('/public/css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($asset('/public/css/theme-light.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($asset('/public/css/theme-dark.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">

    <!-- Tailwind CDN (utilitaires sans reset global) -->
    <script>
        window.tailwind = window.tailwind || {};
        window.tailwind.config = { corePlugins: { preflight: false } };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Chart.js CDN (seule dépendance externe) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <div id="page-loader" class="page-loader is-visible" aria-hidden="true">
        <div class="loader-water-scene" role="status" aria-label="Chargement en cours">
            <span class="loader-drop"></span>
            <span class="loader-splash"></span>
            <span class="loader-water-surface"></span>
            <span class="loader-ripple loader-ripple-1"></span>
            <span class="loader-ripple loader-ripple-2"></span>
            <span class="loader-ripple loader-ripple-3"></span>
        </div>
    </div>
    <div class="app">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../components/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="main">
            <!-- Header -->
            <?php include __DIR__ . '/../components/header.php'; ?>
            
            <!-- View Container -->
            <div id="view-container" class="view-container">
                <?= $content ?>
            </div>
        </main>
    </div>
    <!-- Scripts -->
    <script src="<?= htmlspecialchars($asset('/public/js/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    
    <!-- Page spécifique -->
    <?php if (isset($pageScript)): ?>
    <script src="<?= htmlspecialchars($asset('/public/js/pages/' . $pageScript . '.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endif; ?>
</body>
</html>
