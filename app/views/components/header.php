<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/dashboard', PHP_URL_PATH) ?: '/dashboard';
$sessionUser = $_SESSION['user'] ?? [];
$role = \App\Core\RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));
$roleLabel = \App\Core\RolePermissions::label($role);

$normalizedPath = str_replace('/index.php', '', $currentPath);
$isDashboard = strpos($normalizedPath, '/dashboard') !== false;
$currentPeriod = $currentPeriod ?? null;
?>

<header class="topbar">
    <div class="topbar-main">
        <div style="display: flex; align-items: center; gap: 12px;">
            <button id="menu-toggle" class="btn-icon" aria-label="Ouvrir le menu"><i class="fa-solid fa-bars"></i></button>
            <div>
                <h2 class="topbar-title"><?= htmlspecialchars($title ?? 'Symphony', ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="topbar-subtitle">Connecté en tant que <?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>

        <div class="topbar-actions">

            <?php if (!$isDashboard): ?>
            <a class="btn btn-soft" href="/dashboard">Aller sur le tableau de bord</a>
            <?php endif; ?>

            <a class="btn btn-danger btn-logout" href="/logout">
                <i class="fa-solid fa-right-from-bracket"></i> Déconnexion
            </a>
        </div>
    </div>

</header>

<style>
.topbar {
    padding: 18px 24px;
    border-bottom: 1px solid var(--border-light);
    background: var(--bg-surface);
    position: sticky;
    top: 0;
    z-index: 20;
}

.topbar-main {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.topbar-title {
    font-size: 18px;
    margin: 0;
}

.topbar-subtitle {
    margin: 2px 0 0;
    font-size: 12px;
    color: var(--text-secondary);
}

.topbar-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.demo-steps {
    margin-top: 14px;
    display: flex;
    gap: 10px;
    overflow: auto;
    padding-bottom: 4px;
}

.period-chip {
    background: var(--accent-soft);
    color: var(--accent);
    border-radius: 999px;
    padding: 8px 12px;
    font-size: 12px;
    font-weight: 600;
}

.demo-step {
    text-decoration: none;
    color: var(--text-secondary);
    background: var(--bg-primary);
    border-radius: 999px;
    padding: 7px 12px;
    font-size: 12px;
    white-space: nowrap;
}

.demo-step.active {
    background: var(--accent-soft);
    color: var(--accent);
    font-weight: 600;
}

@media (max-width: 1024px) {
    .topbar {
        padding: 14px 16px;
    }

    .topbar-main {
        flex-direction: column;
        align-items: flex-start;
    }

    .topbar-actions {
        width: 100%;
    }
}
</style>
