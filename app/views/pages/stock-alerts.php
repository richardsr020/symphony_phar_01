<?php
$alerts = $alerts ?? [];
$summary = $summary ?? [
    'total' => count($alerts),
    'critical' => 0,
    'warning' => 0,
    'out_of_stock' => 0,
    'low_stock' => 0,
    'expiry' => 0,
];

$formatNumber = static function ($value, int $decimals = 2): string {
    $numeric = round((float) $value, $decimals);
    $formatted = number_format($numeric, $decimals, '.', ' ');
    if ($decimals > 0) {
        $formatted = rtrim(rtrim($formatted, '0'), '.');
    }
    return $formatted;
};

$severityLabels = [
    'critical' => 'Critique',
    'warning' => 'Attention',
    'info' => 'Info',
];

$statusLabels = [
    'out_of_stock' => 'Rupture',
    'low_stock' => 'Stock bas',
    'expiry' => 'Expiration',
];
?>

<div class="stock-alerts-page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Alertes stock</h1>
            <p class="page-subtitle">Toutes les alertes de stock et d'expiration dans une vue dediee.</p>
        </div>
        <div class="page-actions">
            <a href="/stock" class="btn btn-soft"><i class="fa-solid fa-arrow-left"></i> Retour stock</a>
        </div>
    </div>

    <div class="stock-alerts-summary">
        <article class="stock-alerts-kpi">
            <span class="label">Total</span>
            <strong><?= (int) ($summary['total'] ?? 0) ?></strong>
        </article>
        <article class="stock-alerts-kpi">
            <span class="label">Ruptures</span>
            <strong><?= (int) ($summary['out_of_stock'] ?? 0) ?></strong>
        </article>
        <article class="stock-alerts-kpi">
            <span class="label">Stock bas</span>
            <strong><?= (int) ($summary['low_stock'] ?? 0) ?></strong>
        </article>
        <article class="stock-alerts-kpi">
            <span class="label">Expirations</span>
            <strong><?= (int) ($summary['expiry'] ?? 0) ?></strong>
        </article>
    </div>

    <?php if ($alerts === []): ?>
    <div class="stock-alert-empty">
        <i class="fa-solid fa-circle-check"></i>
        <div>
            <h3>Aucune alerte active</h3>
            <p>Le stock est actuellement sous controle.</p>
        </div>
    </div>
    <?php else: ?>
    <div class="stock-alert-list">
        <?php foreach ($alerts as $alert): ?>
        <?php
        $severity = strtolower((string) ($alert['severity'] ?? 'warning'));
        $statusKey = (string) ($alert['status_key'] ?? '');
        $productId = (int) ($alert['product_id'] ?? 0);
        $daysLeft = isset($alert['days_left']) ? (int) $alert['days_left'] : null;
        $expirationDate = trim((string) ($alert['expiration_date'] ?? ''));
        ?>
        <article class="stock-alert-card stock-alert-card-<?= htmlspecialchars($severity, ENT_QUOTES, 'UTF-8') ?>">
            <div class="stock-alert-card-head">
                <div>
                    <div class="stock-alert-title"><?= htmlspecialchars((string) ($alert['title'] ?? 'Alerte stock'), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="stock-alert-message"><?= htmlspecialchars((string) ($alert['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="stock-alert-badges">
                    <span class="stock-alert-badge stock-alert-badge-<?= htmlspecialchars($severity, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($severityLabels[$severity] ?? ucfirst($severity), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <?php if ($statusKey !== ''): ?>
                    <span class="stock-alert-badge stock-alert-badge-neutral">
                        <?= htmlspecialchars($statusLabels[$statusKey] ?? $statusKey, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stock-alert-meta">
                <?php if (!empty($alert['product_name'])): ?>
                <div><span>Produit</span><strong><?= htmlspecialchars((string) $alert['product_name'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                <?php endif; ?>
                <?php if (array_key_exists('current_quantity', $alert)): ?>
                <div><span>Stock actuel</span><strong><?= htmlspecialchars($formatNumber((float) ($alert['current_quantity'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?></strong></div>
                <?php endif; ?>
                <?php if (array_key_exists('min_stock', $alert)): ?>
                <div><span>Seuil mini</span><strong><?= htmlspecialchars($formatNumber((float) ($alert['min_stock'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?></strong></div>
                <?php endif; ?>
                <?php if (!empty($alert['lot_code'])): ?>
                <div><span>Lot</span><strong><?= htmlspecialchars((string) $alert['lot_code'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                <?php endif; ?>
                <?php if ($expirationDate !== ''): ?>
                <div><span>Expiration</span><strong><?= htmlspecialchars(date('d/m/Y', strtotime($expirationDate)), ENT_QUOTES, 'UTF-8') ?></strong></div>
                <?php endif; ?>
                <?php if ($daysLeft !== null): ?>
                <div><span>Ecart</span><strong><?= $daysLeft < 0 ? 'Expire depuis ' . abs($daysLeft) . ' j' : 'Dans ' . $daysLeft . ' j' ?></strong></div>
                <?php endif; ?>
            </div>

            <div class="stock-alert-actions">
                <?php if ($productId > 0): ?>
                <a class="btn btn-soft" href="/stock?edit=<?= $productId ?>">
                    <i class="fa-solid fa-box-open"></i> Ouvrir le produit
                </a>
                <?php endif; ?>
                <a class="btn btn-soft" href="/stock">
                    <i class="fa-solid fa-warehouse"></i> Aller au stock
                </a>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.stock-alerts-page {
    display: grid;
    gap: 18px;
}
.stock-alerts-page .page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
}
.stock-alerts-page .page-title {
    margin: 0;
}
.stock-alerts-page .page-subtitle {
    margin: 6px 0 0;
    color: var(--text-secondary);
}
.stock-alerts-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
}
.stock-alerts-kpi {
    background: linear-gradient(180deg, var(--bg-surface) 0%, color-mix(in srgb, var(--bg-surface) 78%, var(--bg-primary)) 100%);
    border: 1px solid var(--border-light);
    border-radius: 16px;
    padding: 16px 18px;
    box-shadow: var(--shadow-md);
}
.stock-alerts-kpi .label {
    display: block;
    color: var(--text-secondary);
    font-size: 12px;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.stock-alerts-kpi strong {
    font-size: 28px;
    color: var(--text-primary);
}
.stock-alert-empty {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 22px;
    border-radius: 18px;
    background: linear-gradient(135deg, color-mix(in srgb, var(--success) 12%, var(--bg-surface)) 0%, color-mix(in srgb, var(--bg-surface) 88%, var(--bg-primary)) 100%);
    border: 1px solid color-mix(in srgb, var(--success) 28%, var(--border-light));
    color: var(--success);
    box-shadow: var(--shadow-sm);
}
.stock-alert-empty i {
    font-size: 28px;
}
.stock-alert-empty h3,
.stock-alert-empty p {
    margin: 0;
}
.stock-alert-empty h3 {
    color: var(--text-primary);
}
.stock-alert-empty p {
    color: var(--text-secondary);
}
.stock-alert-list {
    display: grid;
    gap: 16px;
}
.stock-alert-card {
    background: var(--bg-surface);
    border-radius: 18px;
    padding: 18px;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--border-light);
    display: grid;
    gap: 14px;
}
.stock-alert-card-critical {
    border-left: 5px solid var(--danger);
}
.stock-alert-card-warning {
    border-left: 5px solid var(--warning);
}
.stock-alert-card-head {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: flex-start;
}
.stock-alert-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-primary);
}
.stock-alert-message {
    margin-top: 6px;
    color: var(--text-secondary);
    line-height: 1.5;
}
.stock-alert-badges {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}
.stock-alert-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}
.stock-alert-badge-critical {
    background: color-mix(in srgb, var(--danger) 16%, transparent);
    color: var(--danger);
    border: 1px solid color-mix(in srgb, var(--danger) 28%, transparent);
}
.stock-alert-badge-warning {
    background: color-mix(in srgb, var(--warning) 16%, transparent);
    color: var(--warning);
    border: 1px solid color-mix(in srgb, var(--warning) 26%, transparent);
}
.stock-alert-badge-neutral {
    background: color-mix(in srgb, var(--text-secondary) 12%, transparent);
    color: var(--text-secondary);
    border: 1px solid color-mix(in srgb, var(--text-secondary) 20%, transparent);
}
.stock-alert-meta {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}
.stock-alert-meta div {
    background: color-mix(in srgb, var(--bg-surface) 70%, var(--bg-primary));
    border-radius: 12px;
    padding: 10px 12px;
    border: 1px solid var(--border-light);
}
.stock-alert-meta span {
    display: block;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-secondary);
    margin-bottom: 4px;
}
.stock-alert-meta strong {
    color: var(--text-primary);
}
.stock-alert-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

[data-theme="dark"] .stock-alerts-kpi {
    background: linear-gradient(180deg, color-mix(in srgb, var(--bg-surface) 94%, #ffffff 6%) 0%, color-mix(in srgb, var(--bg-surface) 82%, var(--bg-primary)) 100%);
}
[data-theme="dark"] .stock-alert-card {
    background: color-mix(in srgb, var(--bg-surface) 96%, #ffffff 4%);
}
[data-theme="dark"] .stock-alert-meta div {
    background: color-mix(in srgb, var(--bg-surface) 82%, var(--bg-primary));
}
[data-theme="dark"] .stock-alert-badge-neutral {
    color: var(--text-primary);
}
[data-theme="dark"] .stock-alert-empty {
    background: linear-gradient(135deg, color-mix(in srgb, var(--success) 16%, var(--bg-surface)) 0%, color-mix(in srgb, var(--bg-surface) 92%, var(--bg-primary)) 100%);
}

@media (max-width: 900px) {
    .stock-alerts-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .stock-alert-meta {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .stock-alerts-page .page-header {
        flex-direction: column;
    }
    .stock-alerts-summary,
    .stock-alert-meta {
        grid-template-columns: 1fr;
    }
    .stock-alert-card-head {
        flex-direction: column;
    }
    .stock-alert-badges {
        justify-content: flex-start;
    }
}
</style>
