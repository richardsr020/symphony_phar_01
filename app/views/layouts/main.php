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
    <title><?= $title ?? ' phar' ?> - <?= Config::SITE_NAME ?></title>
    
    <!-- Local fonts: using system font stack by default. To add local webfonts, place files under public/fonts and update public/css/style.css -->
    
    <!-- Styles -->
    <link rel="stylesheet" href="<?= htmlspecialchars($asset('/public/css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($asset('/public/css/theme-light.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($asset('/public/css/theme-dark.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">

    <!-- Tailwind (local copy for offline) -->
    <script>
        window.tailwind = window.tailwind || {};
        window.tailwind.config = { corePlugins: { preflight: false } };
    </script>
    <script src="<?= htmlspecialchars($asset('/public/js/libs/tailwind.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    
    <!-- Chart.js (CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="<?= htmlspecialchars($asset('/public/js/modules/pdf-export.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
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
