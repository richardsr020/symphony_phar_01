<?php
$currentRoute = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$normalizedCurrentRoute = rtrim($currentRoute, '/');
if ($normalizedCurrentRoute === '') {
    $normalizedCurrentRoute = '/';
}
$sessionUser = $_SESSION['user'] ?? [];
$companyName = (string) ($sessionUser['company_name'] ?? 'Entreprise');
$firstName = (string) ($sessionUser['first_name'] ?? '');
$lastName = (string) ($sessionUser['last_name'] ?? '');
$fullName = trim($firstName . ' ' . $lastName);
$role = \App\Core\RolePermissions::normalizeRole((string) ($sessionUser['role'] ?? ''));
$roleLabel = \App\Core\RolePermissions::label($role);
$avatarPath = trim((string) ($sessionUser['avatar'] ?? ''));
$avatarUrl = $avatarPath !== ''
    ? $avatarPath
    : 'https://ui-avatars.com/api/?name=' . urlencode($fullName !== '' ? $fullName : 'Utilisateur') . '&background=0F9D58&color=fff&size=40';

$menuItems = [
    [
        'icon' => 'fa-solid fa-chart-line',
        'label' => 'Tableau de bord',
        'route' => '/dashboard',
        'aliases' => [],
        'visible' => \App\Core\RolePermissions::canAccessDashboard($role),
    ],
    [
        'icon' => 'fa-solid fa-money-bill-transfer',
        'label' => 'Transactions',
        'route' => '/transactions',
        'aliases' => [],
        'visible' => \App\Core\RolePermissions::canAccessTransactions($role),
    ],
    [
        'icon' => 'fa-regular fa-file-lines',
        'label' => 'Ventes',
        'route' => '/invoices',
        'aliases' => ['/invoice'],
        'visible' => \App\Core\RolePermissions::canAccessInvoices($role),
    ],
    [
        'icon' => 'fa-solid fa-boxes-stacked',
        'label' => 'Stock',
        'route' => '/stock',
        'aliases' => [],
        'visible' => \App\Core\RolePermissions::canAccessStock($role),
    ],
    [
        'icon' => 'fa-solid fa-chart-pie',
        'label' => 'Rapports',
        'route' => '/reports',
        'aliases' => [],
        'visible' => \App\Core\RolePermissions::canAccessReports($role),
    ],
    [
        'icon' => 'fa-solid fa-user-group',
        'label' => 'Suivi clients',
        'route' => '/suivi-clients',
        'aliases' => [],
        'visible' => \App\Core\RolePermissions::canAccessInvoices($role),
    ],
    [
        'icon' => 'fa-solid fa-truck',
        'label' => 'Fournisseurs',
        'route' => '/fournisseurs',
        'aliases' => [],
        'visible' => \App\Core\RolePermissions::canAccessStock($role),
    ],
    [
        'icon' => 'fa-solid fa-gear',
        'label' => 'Paramètres',
        'route' => '/settings',
        'aliases' => [],
        'visible' => \App\Core\RolePermissions::canAccessSettings($role),
    ],
];

$isRouteActive = static function (string $currentPath, string $baseRoute, array $aliases = []): bool {
    $candidates = array_values(array_filter(array_merge([$baseRoute], $aliases), static fn($value): bool => is_string($value) && trim($value) !== ''));
    foreach ($candidates as $candidate) {
        $normalizedCandidate = rtrim($candidate, '/');
        if ($normalizedCandidate === '') {
            $normalizedCandidate = '/';
        }

        if ($normalizedCandidate === '/') {
            if ($currentPath === '/') {
                return true;
            }
            continue;
        }

        if ($currentPath === $normalizedCandidate || strpos($currentPath, $normalizedCandidate . '/') === 0) {
            return true;
        }
    }

    return false;
};

$companyInfo = [
    'name' => $companyName,
    'plan' => \App\Core\RolePermissions::shortLabel($role),
    'alert_count' => 0
];
?>

<aside class="sidebar" id="sidebar">
    <!-- Logo -->
    <div class="sidebar-logo">
        <div class="logo-icon">S</div>
        <div class="logo-text">
            <strong>Symphony Business</strong>
            <span>Gestion Comptable </span>
        </div>
    </div>
    
    <!-- Company Info -->
    <div class="company-badge">
        <div class="company-name"><?= $companyInfo['name'] ?></div>
        <div class="company-plan">
            <span class="plan-badge"><?= $companyInfo['plan'] ?></span>
            <?php if ($companyInfo['alert_count'] > 0): ?>
            <span class="alert-badge"><?= $companyInfo['alert_count'] ?></span>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Navigation -->
    <nav class="sidebar-nav">
        <?php foreach ($menuItems as $item): ?>
        <?php
            $itemRoute = (string) ($item['route'] ?? '/');
            $itemAliases = is_array($item['aliases'] ?? null) ? $item['aliases'] : [];
            if (!((bool) ($item['visible'] ?? true))) {
                continue;
            }
            $isActive = $isRouteActive($normalizedCurrentRoute, $itemRoute, $itemAliases);
        ?>
        <a href="<?= $item['route'] ?>" 
           class="nav-item <?= $isActive ? 'active' : '' ?>"
           data-nav-route="<?= htmlspecialchars($itemRoute, ENT_QUOTES, 'UTF-8') ?>"
           data-nav-aliases="<?= htmlspecialchars(implode(',', $itemAliases), ENT_QUOTES, 'UTF-8') ?>">
            <span class="nav-icon"><i class="<?= htmlspecialchars((string) $item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
            <span class="nav-label"><?= $item['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </nav>
    
    <div class="ai-status">
        <div class="ai-indicator">
            <span class="pulse-dot"></span>
            <span><?= (defined('Config::AI_ENABLED') && \Config::AI_ENABLED) ? 'Symphony IA actif' : 'IA desactivee' ?></span>
        </div>
        <div class="ai-memory">
            <small><?= (defined('Config::AI_ENABLED') && \Config::AI_ENABLED) ? 'Cliquez sur le bouton flottant pour discuter.' : 'Activez Config::AI_ENABLED pour utiliser l agent.' ?></small>
        </div>
    </div>
    
    <!-- User Info -->
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
            </div>
            <div class="user-details">
                <div class="user-name"><?= htmlspecialchars($fullName !== '' ? $fullName : 'Utilisateur', ENT_QUOTES, 'UTF-8') ?></div>
                <div class="user-role"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <button class="btn-icon" id="theme-toggle">
                <span class="theme-icon-light"><i class="fa-regular fa-sun"></i></span>
                <span class="theme-icon-dark"><i class="fa-regular fa-moon"></i></span>
            </button>
        </div>
    </div>
</aside>

<style>
.sidebar-logo {
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid var(--border-light);
    margin-bottom: 20px;
}

.logo-icon {
    width: 40px;
    height: 40px;
    background: var(--accent);
    color: white;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: bold;
}

.logo-text {
    display: flex;
    flex-direction: column;
}

.logo-text strong {
    font-size: 18px;
    color: var(--text-primary);
}

.logo-text span {
    font-size: 12px;
    color: var(--text-secondary);
}

.company-badge {
    padding: 0 20px 20px;
    border-bottom: 1px solid var(--border-light);
    margin-bottom: 20px;
}

.company-name {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 8px;
}

.company-plan {
    display: flex;
    align-items: center;
    gap: 10px;
}

.plan-badge {
    background: var(--accent-soft);
    color: var(--accent);
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.alert-badge {
    background: var(--danger);
    color: white;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 11px;
}

.sidebar-nav {
    flex: 1;
    padding: 0 12px;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: var(--radius-md);
    color: var(--text-secondary);
    text-decoration: none;
    transition: all 0.2s;
    margin-bottom: 4px;
}

.nav-item:hover {
    background: var(--accent-soft);
    color: var(--accent);
}

.nav-item.active {
    background: var(--accent-soft);
    color: var(--accent);
    font-weight: 500;
}

.nav-icon {
    width: 20px;
    text-align: center;
    font-size: 16px;
}

.ai-status {
    padding: 20px;
    margin: 20px 12px;
    background: var(--accent-soft);
    border-radius: var(--radius-lg);
}

.ai-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--accent);
    font-weight: 500;
    margin-bottom: 8px;
}

.pulse-dot {
    width: 8px;
    height: 8px;
    background: var(--accent);
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(15, 157, 88, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(15, 157, 88, 0); }
    100% { box-shadow: 0 0 0 0 rgba(15, 157, 88, 0); }
}

.ai-memory {
    font-size: 12px;
    color: var(--text-secondary);
}

.sidebar-footer {
    padding: 20px;
    border-top: 1px solid var(--border-light);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-avatar img {
    width: 40px;
    height: 40px;
    border-radius: 10px;
}

.user-details {
    flex: 1;
}

.user-name {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 14px;
}

.user-role {
    font-size: 12px;
    color: var(--text-secondary);
}

#theme-toggle {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: 1px solid var(--border-light);
    background: var(--bg-surface);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

[data-theme="light"] .theme-icon-dark,
[data-theme="dark"] .theme-icon-light {
    display: none;
}
</style>
