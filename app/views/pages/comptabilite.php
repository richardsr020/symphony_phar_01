<?php
$userName = $userName ?? 'Utilisateur';
$stats = $stats ?? [];

$statCards = [
    [
        'label' => 'Tresorerie',
        'value' => (float) ($stats['cash'] ?? 0),
        'parts' => [
            'Factures encaissees: $' . number_format((float) ($stats['cash_invoices'] ?? 0), 2),
            'Transactions nettes: $' . number_format((float) ($stats['cash_transactions'] ?? 0), 2),
        ],
    ],
    [
        'label' => "Chiffre d'Affaire",
        'value' => (float) ($stats['revenue'] ?? 0),
        'parts' => [
            'Factures: $' . number_format((float) ($stats['revenue_invoices'] ?? 0), 2),
            'Occasionnels: $' . number_format((float) ($stats['revenue_occasional'] ?? 0), 2),
        ],
    ],
    [
        'label' => 'Depenses',
        'value' => (float) ($stats['expenses'] ?? 0),
        'parts' => [
            'Transactions depenses: $' . number_format((float) ($stats['expenses_transactions'] ?? 0), 2),
        ],
    ],
    [
        'label' => 'TVA a payer',
        'value' => (float) ($stats['vat_due'] ?? 0),
        'parts' => [
            'TVA factures: $' . number_format((float) ($stats['vat_from_invoices'] ?? 0), 2),
            'TVA versee: -$' . number_format((float) ($stats['vat_paid'] ?? 0), 2),
        ],
    ],
];
?>

<div class="comptabilite-page">
    <div class="page-header card">
        <div>
            <h1 class="page-title">Comptabilité</h1>
            <p class="page-subtitle">Tableau de bord financier administrateur</p>
        </div>
        <div class="header-actions">
            <span class="badge badge-primary">Accès Admin</span>
            <span class="badge badge-info">Mis à jour : <?= date('d/m/Y H:i') ?></span>
        </div>
    </div>

    <div class="kpi-grid">
        <?php foreach ($statCards as $idx => $stat): ?>
        <?php $gradientClass = 'gradient-' . ((int) ($idx % 4) + 1); ?>
        <div class="kpi-card <?= htmlspecialchars($gradientClass, ENT_QUOTES, 'UTF-8') ?>">
            <div class="kpi-content">
                <h3><?= htmlspecialchars($stat['label'], ENT_QUOTES, 'UTF-8') ?></h3>
                <div class="kpi-value">$<?= number_format((float) $stat['value'], 2) ?></div>
                <?php if (!empty($stat['parts']) && is_array($stat['parts'])): ?>
                <ul class="kpi-details">
                    <?php foreach ($stat['parts'] as $part): ?>
                    <li><?= htmlspecialchars($part, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="comptabilite-main-grid">
        <section class="card overview-panel">
            <h2>Résumé comptable</h2>
            <table class="overview-table">
                <tbody>
                    <tr>
                        <td>Trésorerie</td>
                        <td>$<?= number_format((float) ($stats['cash'] ?? 0), 2) ?></td>
                    </tr>
                    <tr>
                        <td>Chiffre d'Affaire</td>
                        <td>$<?= number_format((float) ($stats['revenue'] ?? 0), 2) ?></td>
                    </tr>
                    <tr>
                        <td>Dépenses</td>
                        <td>$<?= number_format((float) ($stats['expenses'] ?? 0), 2) ?></td>
                    </tr>
                    <tr>
                        <td>TVA à payer</td>
                        <td>$<?= number_format((float) ($stats['vat_due'] ?? 0), 2) ?></td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="card insights-panel">
            <h2>Indicateurs clés</h2>
            <ul class="insights-list">
                <li>Factures encaissées : $<?= number_format((float) ($stats['cash_invoices'] ?? 0), 2) ?></li>
                <li>Transactions nettes : $<?= number_format((float) ($stats['cash_transactions'] ?? 0), 2) ?></li>
                <li>Revenus factures : $<?= number_format((float) ($stats['revenue_invoices'] ?? 0), 2) ?></li>
                <li>Revenus occasionnels : $<?= number_format((float) ($stats['revenue_occasional'] ?? 0), 2) ?></li>
                <li>TVA factures : $<?= number_format((float) ($stats['vat_from_invoices'] ?? 0), 2) ?></li>
                <li>TVA versée : -$<?= number_format((float) ($stats['vat_paid'] ?? 0), 2) ?></li>
            </ul>
        </section>
    </div>
</div>

<style>
.comptabilite-page {
    display: grid;
    gap: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-radius: var(--radius-lg);
    background: var(--bg-surface);
    box-shadow: var(--shadow-sm);
}

.page-header .page-title {
    margin: 0;
    font-size: 1.5rem;
}

.page-header .page-subtitle {
    margin: 4px 0 0;
    color: var(--text-secondary);
}

.header-actions {
    display: flex;
    gap: 10px;
}

.badge {
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 600;
}

.badge-primary { background: #2d6aff; color: #fff; }
.badge-info { background: #1e9ebf; color: #fff; }

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
}

.kpi-card {
    color: var(--text-primary);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-md);
    padding: 18px;
    min-height: 150px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.kpi-card h3 {
    margin: 0 0 8px;
    font-size: 1rem;
    letter-spacing: 0.02em;
}

.kpi-card.gradient-1,
.kpi-card.gradient-2,
.kpi-card.gradient-3,
.kpi-card.gradient-4 {
    color: var(--text-primary);
}

[data-theme="dark"] .kpi-card,
[data-theme="dark"] .kpi-card.gradient-1,
[data-theme="dark"] .kpi-card.gradient-2,
[data-theme="dark"] .kpi-card.gradient-3,
[data-theme="dark"] .kpi-card.gradient-4 {
    color: #fff;
}

.kpi-value {
    font-size: 2.2rem;
    font-weight: 700;
}

.kpi-details {
    margin: 10px 0 0;
    list-style: none;
    padding: 0;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.kpi-details li { margin-bottom: 4px; }

[data-theme="dark"] .kpi-details {
    color: rgba(255, 255, 255, 0.9);
}

.comptabilite-main-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 18px;
}

.overview-panel,
.insights-panel {
    background: var(--bg-surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    padding: 18px;
}

.overview-panel h2,
.insights-panel h2 {
    margin: 0 0 12px;
    font-size: 1.1rem;
}

.overview-table {
    width: 100%;
    border-collapse: collapse;
}

.overview-table td {
    padding: 10px 8px;
    border-bottom: 1px solid var(--border-light);
}

.overview-table td:first-child {
    color: var(--text-secondary);
    width: 65%;
}

.insights-list {
    list-style: none;
    padding: 0;
    margin: 0;
    color: var(--text-primary);
}

.insights-list li {
    padding: 8px 0;
    border-bottom: 1px solid var(--border-light);
}

.insights-list li:last-child { border-bottom: none; }
</style>
