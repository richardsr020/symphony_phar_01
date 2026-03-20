<?php
$reportType = $reportType ?? 'overview';
$period = $period ?? [
    'period' => 'year',
    'from_date' => date('Y-01-01'),
    'to_date' => date('Y-m-d'),
    'label' => date('d/m/Y', strtotime(date('Y-01-01'))) . ' - ' . date('d/m/Y'),
];
$overview = $overview ?? [
    'revenue' => 0,
    'expenses' => 0,
    'net' => 0,
    'vat_due' => 0,
    'client_debt' => 0,
    'profit_margin' => 0,
    'expense_ratio' => 0,
    'cash_available' => 0,
    'revenue_delta' => 0,
    'expenses_delta' => 0,
    'net_delta' => 0,
    'revenue_prev' => 0,
    'expenses_prev' => 0,
    'net_prev' => 0,
    'revenue_trend' => 0,
    'expenses_trend' => 0,
    'net_trend' => 0,
];
$monthlySeries = $monthlySeries ?? [
    'labels' => ['Aucune periode'],
    'income_invoice' => [0],
    'income_occasional' => [0],
    'income' => [0],
    'expenses' => [0],
];
$revenueBreakdown = $revenueBreakdown ?? ['labels' => ['Aucune donnee'], 'values' => [0]];
$expenseBreakdown = $expenseBreakdown ?? ['labels' => ['Aucune depense'], 'values' => [0], 'colors' => ['#94A3B8']];
$profitLoss = $profitLoss ?? ['revenue' => 0, 'expenses' => 0, 'net' => 0];
$balanceSheet = $balanceSheet ?? ['assets' => 0, 'liabilities' => 0, 'equity' => 0];
$tvaReport = $tvaReport ?? ['vat_total' => 0, 'vat_due' => 0, 'vat_paid' => 0];
$currentFiscalPeriod = $currentFiscalPeriod ?? null;
$periodLabel = (string) ($period['label'] ?? '');
$showCustomRange = true;
$profitMargin = (float) ($overview['profit_margin'] ?? 0);
$expenseRatio = (float) ($overview['expense_ratio'] ?? 0);
$clientDebt = (float) ($overview['client_debt'] ?? 0);
$cashAvailable = (float) ($overview['cash_available'] ?? 0);
$revenueDelta = (float) ($overview['revenue_delta'] ?? 0);
$expensesDelta = (float) ($overview['expenses_delta'] ?? 0);
$netDelta = (float) ($overview['net_delta'] ?? 0);
$formatDelta = static function (float $value): string {
    $prefix = $value >= 0 ? '+' : '';
    return $prefix . number_format($value, 2);
};
$debtRatio = (float) ($overview['revenue'] ?? 0) > 0
    ? ($clientDebt / (float) $overview['revenue']) * 100
    : 0.0;
$performanceTitle = $showCustomRange || (string) ($period['period'] ?? '') === 'fiscal'
    ? 'Performance annuelle'
    : 'Performance de la periode';

$reportRoutes = [
    'overview' => '/reports',
    'profit-loss' => '/reports/profit-loss',
    'balance-sheet' => '/reports/balance',
    'tva' => '/reports/tva',
];

$baseRoute = $reportRoutes[$reportType] ?? '/reports';
$reportCards = [
    ['name' => 'Compte de resultat', 'desc' => 'Chiffre d\'affaires, charges, resultat net', 'icon' => 'fas fa-chart-bar', 'period' => 'Mensuel', 'route' => '/reports/profit-loss'],
    ['name' => 'Bilan comptable', 'desc' => 'Actif, passif, capitaux propres', 'icon' => 'fas fa-balance-scale', 'period' => 'Annuel', 'route' => '/reports/balance'],
    ['name' => 'Declaration TVA', 'desc' => 'TVA collectee, deduite, a payer', 'icon' => 'fas fa-file-invoice-dollar', 'period' => 'Mensuel', 'route' => '/reports/tva'],
    ['name' => 'Vue globale', 'desc' => 'Vue macro des performances', 'icon' => 'fas fa-chart-line', 'period' => 'Annuel', 'route' => '/reports'],
];
$buildReportRoute = static function (string $route) use ($period): string {
    $query = array_filter([
        'period' => (string) ($period['period'] ?? 'month'),
        'from_date' => (string) ($period['from_date'] ?? ''),
        'to_date' => (string) ($period['to_date'] ?? ''),
    ], static fn($value) => $value !== '');

    $queryString = http_build_query($query);

    return $route . ($queryString !== '' ? '?' . $queryString : '');
};
$customReportUrl = $baseRoute . '?' . http_build_query([
    'period' => 'custom',
    'from_date' => (string) ($period['from_date'] ?? ''),
    'to_date' => (string) ($period['to_date'] ?? ''),
]);
$exportQuery = array_filter([
    'report_type' => (string) $reportType,
    'period' => (string) ($period['period'] ?? 'month'),
    'from_date' => (string) ($period['from_date'] ?? ''),
    'to_date' => (string) ($period['to_date'] ?? ''),
], static fn($value) => $value !== '');
$exportUrl = '/reports/export' . ($exportQuery !== [] ? '?' . http_build_query($exportQuery) : '');
// URL pour le PDF à afficher dans la modale
$pdfContentUrl = '/reports/pdf-content' . ($exportQuery !== [] ? '?' . http_build_query($exportQuery) : '');
// URL pour le téléchargement PDF
$pdfDownloadUrl = '/reports/pdf-download' . ($exportQuery !== [] ? '?' . http_build_query($exportQuery) : '');
$profitLossSummary = sprintf(
    'Sur la periode %s, votre chiffre d\'affaires est de $%s, les charges de $%s et le resultat net de $%s.',
    $periodLabel,
    number_format((float) $profitLoss['revenue'], 2),
    number_format((float) $profitLoss['expenses'], 2),
    number_format((float) $profitLoss['net'], 2)
);
$balanceSummary = sprintf(
    'Au %s, vos actifs totalisent $%s, vos passifs $%s et vos capitaux propres $%s.',
    (string) ($period['to_date'] ?? ''),
    number_format((float) $balanceSheet['assets'], 2),
    number_format((float) $balanceSheet['liabilities'], 2),
    number_format((float) $balanceSheet['equity'], 2)
);
$tvaSummary = sprintf(
    'Sur la periode %s, la TVA totale est de $%s, dont $%s a payer et $%s deja reglee.',
    $periodLabel,
    number_format((float) $tvaReport['vat_total'], 2),
    number_format((float) $tvaReport['vat_due'], 2),
    number_format((float) $tvaReport['vat_paid'], 2)
);
$overviewSummary = sprintf(
    'Sur %s, le chiffre d\'affaires atteint $%s, les charges $%s et le resultat net $%s (marge nette %s%%).',
    $periodLabel,
    number_format((float) $overview['revenue'], 2),
    number_format((float) $overview['expenses'], 2),
    number_format((float) $overview['net'], 2),
    number_format($profitMargin, 1)
);
?>

<div class="reports-page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Rapports</h1>
            <p class="page-subtitle">Analyse financiere sur la periode <?= htmlspecialchars((string) $period['label'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a class="btn btn-soft" href="<?= htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8') ?>" data-no-async="true">Exporter Excel</a>
            <!-- Nouveau bouton pour ouvrir la modale PDF -->
            
        </div>
    </div>

    <form class="period-selector" data-auto-filter="true" method="GET" action="<?= htmlspecialchars($baseRoute, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="period" value="custom">
        <div class="period-custom-range">
            <input type="date" class="filter-input" name="from_date" value="<?= htmlspecialchars((string) $period['from_date'], ENT_QUOTES, 'UTF-8') ?>">
            <span class="period-separator">→</span>
            <input type="date" class="filter-input" name="to_date" value="<?= htmlspecialchars((string) $period['to_date'], ENT_QUOTES, 'UTF-8') ?>">
            <button class="btn btn-primary js-auto-filter-submit" type="submit">Appliquer</button>
            <span class="period-hint">Choisissez une periode precise.</span>
        </div>
    </form>

    <div class="card fiscal-help-card">
        <strong>Periode rapport:</strong> <?= htmlspecialchars((string) $period['label'], ENT_QUOTES, 'UTF-8') ?>
        <?php if ((string) ($period['period'] ?? '') === 'fiscal'): ?>
            (exercice fiscal actif)
        <?php elseif (is_array($currentFiscalPeriod)): ?>
            | Exercice actif: <?= htmlspecialchars((string) $currentFiscalPeriod['start_date'], ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars((string) $currentFiscalPeriod['end_date'], ENT_QUOTES, 'UTF-8') ?>
        <?php endif; ?>
    </div>

    <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 20px; margin-bottom: 40px;">
        <div class="metric-card card">
            <div class="metric-header">
                <span class="metric-icon"><i class="fas fa-chart-line"></i></span>
                <span class="metric-change <?= ((float) $overview['revenue_trend'] >= 0) ? 'positive' : 'negative' ?>">
                    <?= ((float) $overview['revenue_trend'] >= 0 ? '+' : '') . number_format((float) $overview['revenue_trend'], 1) ?>%
                </span>
            </div>
            <div class="metric-value">$<?= number_format((float) $overview['revenue'], 2) ?></div>
            <div class="metric-label">Chiffre d'affaires</div>
            <div class="metric-compare">vs $<?= number_format((float) $overview['revenue_prev'], 2) ?> periode precedente</div>
            <div class="metric-foot">Δ $<?= htmlspecialchars($formatDelta($revenueDelta), ENT_QUOTES, 'UTF-8') ?></div>
        </div>

        <div class="metric-card card">
            <div class="metric-header">
                <span class="metric-icon"><i class="fas fa-chart-line-down"></i></span>
                <span class="metric-change <?= ((float) $overview['expenses_trend'] <= 0) ? 'positive' : 'negative' ?>">
                    <?= ((float) $overview['expenses_trend'] >= 0 ? '+' : '') . number_format((float) $overview['expenses_trend'], 1) ?>%
                </span>
            </div>
            <div class="metric-value">$<?= number_format((float) $overview['expenses'], 2) ?></div>
            <div class="metric-label">Charges</div>
            <div class="metric-compare">vs $<?= number_format((float) $overview['expenses_prev'], 2) ?> periode precedente</div>
            <div class="metric-foot">Δ $<?= htmlspecialchars($formatDelta($expensesDelta), ENT_QUOTES, 'UTF-8') ?></div>
        </div>

        <div class="metric-card card">
            <div class="metric-header">
                <span class="metric-icon"><i class="fas fa-coins"></i></span>
                <span class="metric-change">Mobilisable</span>
            </div>
            <div class="metric-value">$<?= number_format($cashAvailable, 2) ?></div>
            <div class="metric-label">Tresorerie disponible</div>
            <div class="metric-compare">Solde au <?= htmlspecialchars((string) ($period['to_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="metric-foot">Encaissements clients - depenses</div>
        </div>

        <div class="metric-card card">
            <div class="metric-header">
                <span class="metric-icon"><i class="fas fa-percent"></i></span>
                <span class="metric-change <?= $profitMargin >= 0 ? 'positive' : 'negative' ?>">
                    <?= ($profitMargin >= 0 ? '+' : '') . number_format($profitMargin, 1) ?>%
                </span>
            </div>
            <div class="metric-value"><?= number_format($profitMargin, 1) ?>%</div>
            <div class="metric-label">Profitabilite</div>
            <div class="metric-compare">Marge nette sur la periode</div>
            <div class="metric-foot">Net $<?= number_format((float) $overview['net'], 2) ?></div>
        </div>

        <div class="metric-card card">
            <div class="metric-header">
                <span class="metric-icon"><i class="fas fa-hand-holding-dollar"></i></span>
                <span class="metric-change <?= $debtRatio <= 30 ? 'positive' : 'negative' ?>">
                    <?= number_format($debtRatio, 1) ?>%
                </span>
            </div>
            <div class="metric-value">$<?= number_format($clientDebt, 2) ?></div>
            <div class="metric-label">Dettes clients</div>
            <div class="metric-compare">Encours total au <?= htmlspecialchars((string) ($period['to_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="metric-foot">CA $<?= number_format((float) $overview['revenue'], 2) ?></div>
        </div>

        <div class="metric-card card">
            <div class="metric-header">
                <span class="metric-icon"><i class="fas fa-file-invoice-dollar"></i></span>
                <span class="metric-change">Taux 16%</span>
            </div>
            <div class="metric-value">$<?= number_format((float) $overview['vat_due'], 2) ?></div>
            <div class="metric-label">TVA a payer</div>
            <div class="metric-compare">Sur la periode selectionnee</div>
            <div class="metric-foot">Total $<?= number_format((float) $tvaReport['vat_total'], 2) ?></div>
        </div>
    </div>

    <div class="reports-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 40px;">
        <?php foreach ($reportCards as $card): ?>
        <button class="report-card card" type="button" data-modal-open="<?= htmlspecialchars((string) $card['route'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="report-icon"><i class="<?= $card['icon'] ?>"></i></div>
            <div class="report-content">
                <h3><?= htmlspecialchars($card['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                <p><?= htmlspecialchars($card['desc'], ENT_QUOTES, 'UTF-8') ?></p>
                <div class="report-meta">
                    <span class="report-period"><?= htmlspecialchars($card['period'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
        </button>
        <?php endforeach; ?>
    </div>

    <div class="charts-row reports-charts-row">
        <div class="card report-chart-card">
            <h3 style="margin-bottom: 20px;">Evolution mensuelle</h3>
            <div class="chart-frame">
                <canvas id="monthly-chart"></canvas>
            </div>
        </div>

        <div class="card report-chart-card">
            <h3 style="margin-bottom: 20px;">Repartition charges par type</h3>
            <div class="chart-frame">
                <canvas id="revenue-chart"></canvas>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Résumé detaille</h3>
        </div>

        <?php if ($reportType === 'profit-loss'): ?>
        <div class="saved-reports">
            <div class="saved-item"><div class="saved-icon"><i class="fas fa-dollar-sign"></i></div><div class="saved-info"><div class="saved-name">Revenus</div><div class="saved-date">$<?= number_format((float) $profitLoss['revenue'], 2) ?></div></div></div>
            <div class="saved-item"><div class="saved-icon"><i class="fas fa-money-bill-wave"></i></div><div class="saved-info"><div class="saved-name">Charges</div><div class="saved-date">$<?= number_format((float) $profitLoss['expenses'], 2) ?></div></div></div>
            <div class="saved-item"><div class="saved-icon"><i class="fas fa-chart-line"></i></div><div class="saved-info"><div class="saved-name">Resultat net</div><div class="saved-date">$<?= number_format((float) $profitLoss['net'], 2) ?></div></div></div>
        </div>
        <?php elseif ($reportType === 'balance-sheet'): ?>
        <div class="saved-reports">
            <div class="saved-item"><div class="saved-icon"><i class="fas fa-university"></i></div><div class="saved-info"><div class="saved-name">Actifs</div><div class="saved-date">$<?= number_format((float) $balanceSheet['assets'], 2) ?></div></div></div>
            <div class="saved-item"><div class="saved-icon"><i class="fas fa-chart-line-down"></i></div><div class="saved-info"><div class="saved-name">Passifs</div><div class="saved-date">$<?= number_format((float) $balanceSheet['liabilities'], 2) ?></div></div></div>
            <div class="saved-item"><div class="saved-icon"><i class="fas fa-building"></i></div><div class="saved-info"><div class="saved-name">Capitaux propres</div><div class="saved-date">$<?= number_format((float) $balanceSheet['equity'], 2) ?></div></div></div>
        </div>
        <?php elseif ($reportType === 'tva'): ?>
        <div class="saved-reports">
            <div class="saved-item"><div class="saved-icon"><i class="fas fa-file-invoice-dollar"></i></div><div class="saved-info"><div class="saved-name">TVA totale</div><div class="saved-date">$<?= number_format((float) $tvaReport['vat_total'], 2) ?></div></div></div>
            <div class="saved-item"><div class="saved-icon"><i class="fas fa-clock"></i></div><div class="saved-info"><div class="saved-name">TVA a payer</div><div class="saved-date">$<?= number_format((float) $tvaReport['vat_due'], 2) ?></div></div></div>
            <div class="saved-item"><div class="saved-icon"><i class="fas fa-check"></i></div><div class="saved-info"><div class="saved-name">TVA deja reglee</div><div class="saved-date">$<?= number_format((float) $tvaReport['vat_paid'], 2) ?></div></div></div>
        </div>
        <?php else: ?>
        <div class="saved-reports">
            <div class="saved-item"><div class="saved-icon"><i class="fas fa-calendar"></i></div><div class="saved-info"><div class="saved-name">Periode</div><div class="saved-date"><?= htmlspecialchars((string) $period['label'], ENT_QUOTES, 'UTF-8') ?></div></div></div>
            <div class="saved-item"><div class="saved-icon"><i class="fas fa-chart-bar"></i></div><div class="saved-info"><div class="saved-name">Rapport actif</div><div class="saved-date">Vue globale</div></div></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modale PDF -->
    <div class="report-modal" id="pdf-modal" aria-hidden="true">
        <div class="modal-overlay" data-modal-close></div>
        <div class="modal-panel modal-panel-large" role="dialog" aria-modal="true" aria-labelledby="modal-title-pdf">
            <div class="modal-header">
                <h3 id="modal-title-pdf">Aperçu PDF - Rapport <?= htmlspecialchars($reportType === 'overview' ? 'global' : $reportType, ENT_QUOTES, 'UTF-8') ?></h3>
                <div style="display:flex;gap:8px;">
                    <a href="<?= htmlspecialchars($pdfDownloadUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary btn-sm" id="download-pdf-btn" data-no-async="true">
                        <i class="fas fa-download"></i> Télécharger PDF
                    </a>
                    <button class="modal-close" type="button" data-modal-close aria-label="Fermer">&times;</button>
                </div>
            </div>
            <div class="modal-body modal-body-pdf">
                <div class="pdf-loader" id="pdf-loader">
                    <div class="loader-spinner"></div>
                    <p>Génération du PDF en cours...</p>
                </div>
                <iframe id="pdf-preview-iframe" class="pdf-preview-frame" src="" style="width:100%; height:70vh; border:none; display:none;"></iframe>
                <div class="pdf-error" id="pdf-error" style="display:none; color: var(--danger); text-align:center; padding:40px;">
                    <i class="fas fa-exclamation-triangle" style="font-size:48px; margin-bottom:16px;"></i>
                    <p>Erreur lors du chargement du PDF. Veuillez réessayer.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="report-modal" data-report-modal="/reports/profit-loss" aria-hidden="true">
        <div class="modal-overlay" data-modal-close></div>
        <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="modal-title-profit-loss">
            <div class="modal-header">
                <h3 id="modal-title-profit-loss">Compte de resultat</h3>
                <button class="modal-close" type="button" data-modal-close aria-label="Fermer">&times;</button>
            </div>
            <div class="modal-body">
                <p><?= htmlspecialchars($profitLossSummary, ENT_QUOTES, 'UTF-8') ?></p>
                <div class="modal-stats">
                    <div class="modal-stat">
                        <div class="modal-stat-label">Chiffre d'affaires</div>
                        <div class="modal-stat-value">$<?= number_format((float) $profitLoss['revenue'], 2) ?></div>
                    </div>
                    <div class="modal-stat">
                        <div class="modal-stat-label">Charges</div>
                        <div class="modal-stat-value">$<?= number_format((float) $profitLoss['expenses'], 2) ?></div>
                    </div>
                    <div class="modal-stat">
                        <div class="modal-stat-label">Resultat net</div>
                        <div class="modal-stat-value">$<?= number_format((float) $profitLoss['net'], 2) ?></div>
                    </div>
                </div>
                <a class="btn btn-primary" href="<?= htmlspecialchars($buildReportRoute('/reports/profit-loss'), ENT_QUOTES, 'UTF-8') ?>">Ouvrir le rapport complet</a>
            </div>
        </div>
    </div>

    <div class="report-modal" data-report-modal="/reports/balance" aria-hidden="true">
        <div class="modal-overlay" data-modal-close></div>
        <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="modal-title-balance">
            <div class="modal-header">
                <h3 id="modal-title-balance">Bilan comptable</h3>
                <button class="modal-close" type="button" data-modal-close aria-label="Fermer">&times;</button>
            </div>
            <div class="modal-body">
                <p><?= htmlspecialchars($balanceSummary, ENT_QUOTES, 'UTF-8') ?></p>
                <div class="modal-stats">
                    <div class="modal-stat">
                        <div class="modal-stat-label">Actifs</div>
                        <div class="modal-stat-value">$<?= number_format((float) $balanceSheet['assets'], 2) ?></div>
                    </div>
                    <div class="modal-stat">
                        <div class="modal-stat-label">Passifs</div>
                        <div class="modal-stat-value">$<?= number_format((float) $balanceSheet['liabilities'], 2) ?></div>
                    </div>
                    <div class="modal-stat">
                        <div class="modal-stat-label">Capitaux propres</div>
                        <div class="modal-stat-value">$<?= number_format((float) $balanceSheet['equity'], 2) ?></div>
                    </div>
                </div>
                <a class="btn btn-primary" href="<?= htmlspecialchars($buildReportRoute('/reports/balance'), ENT_QUOTES, 'UTF-8') ?>">Ouvrir le rapport complet</a>
            </div>
        </div>
    </div>

    <div class="report-modal" data-report-modal="/reports/tva" aria-hidden="true">
        <div class="modal-overlay" data-modal-close></div>
        <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="modal-title-tva">
            <div class="modal-header">
                <h3 id="modal-title-tva">Declaration TVA</h3>
                <button class="modal-close" type="button" data-modal-close aria-label="Fermer">&times;</button>
            </div>
            <div class="modal-body">
                <p><?= htmlspecialchars($tvaSummary, ENT_QUOTES, 'UTF-8') ?></p>
                <div class="modal-stats">
                    <div class="modal-stat">
                        <div class="modal-stat-label">TVA totale</div>
                        <div class="modal-stat-value">$<?= number_format((float) $tvaReport['vat_total'], 2) ?></div>
                    </div>
                    <div class="modal-stat">
                        <div class="modal-stat-label">TVA a payer</div>
                        <div class="modal-stat-value">$<?= number_format((float) $tvaReport['vat_due'], 2) ?></div>
                    </div>
                    <div class="modal-stat">
                        <div class="modal-stat-label">TVA deja reglee</div>
                        <div class="modal-stat-value">$<?= number_format((float) $tvaReport['vat_paid'], 2) ?></div>
                    </div>
                </div>
                <a class="btn btn-primary" href="<?= htmlspecialchars($buildReportRoute('/reports/tva'), ENT_QUOTES, 'UTF-8') ?>">Ouvrir le rapport complet</a>
            </div>
        </div>
    </div>

    <div class="report-modal" data-report-modal="/reports" aria-hidden="true">
        <div class="modal-overlay" data-modal-close></div>
        <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="modal-title-overview">
            <div class="modal-header">
                <h3 id="modal-title-overview">Vue globale</h3>
                <button class="modal-close" type="button" data-modal-close aria-label="Fermer">&times;</button>
            </div>
            <div class="modal-body">
                <p><?= htmlspecialchars($overviewSummary, ENT_QUOTES, 'UTF-8') ?></p>
                <div class="modal-stats">
                    <div class="modal-stat">
                        <div class="modal-stat-label">Marge nette</div>
                        <div class="modal-stat-value"><?= number_format($profitMargin, 1) ?>%</div>
                    </div>
                    <div class="modal-stat">
                        <div class="modal-stat-label">Taux charges</div>
                        <div class="modal-stat-value"><?= number_format($expenseRatio, 1) ?>%</div>
                    </div>
                    <div class="modal-stat">
                        <div class="modal-stat-label">Taux dettes clients</div>
                        <div class="modal-stat-value"><?= number_format($debtRatio, 1) ?>%</div>
                    </div>
                </div>
                <div class="modal-chart">
                    <h4><?= htmlspecialchars($performanceTitle, ENT_QUOTES, 'UTF-8') ?></h4>
                    <div class="chart-frame">
                        <canvas id="annual-performance-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.reports-page .page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.period-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: flex-end;
    margin-bottom: 16px;
    padding: 14px;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    background: var(--bg-surface);
}

.period-quick-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    flex: 1 1 420px;
}

.period-custom-range {
    display: grid;
    grid-template-columns: minmax(145px, 1fr) auto minmax(145px, 1fr) auto;
    align-items: center;
    gap: 10px;
    flex: 1 1 460px;
}

.period-custom-range.is-hidden {
    display: none;
}

.period-hint {
    font-size: 12px;
    color: var(--text-secondary);
    grid-column: 1 / -1;
}

.period-separator {
    color: var(--text-secondary);
    font-weight: 600;
}

.period-custom-range .filter-input {
    width: 100%;
    min-height: 42px;
    padding: 10px 12px;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    background: var(--bg-surface);
    color: var(--text-primary);
}

.period-custom-range .filter-input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 16%, transparent);
}

.period-custom-range .filter-input[type="date"] {
    color-scheme: light;
}

[data-theme="dark"] .period-custom-range .filter-input {
    background: color-mix(in srgb, var(--bg-surface) 92%, transparent);
    color: #e6edf3;
    border-color: color-mix(in srgb, var(--border-light) 82%, #94a3b8 18%);
}

[data-theme="dark"] .period-custom-range .filter-input[type="date"] {
    color-scheme: dark;
}

[data-theme="dark"] .period-custom-range .filter-input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(0.92) brightness(1.08);
}

.metric-card { padding: 20px 20px 36px; position: relative; }
.metric-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
.metric-icon { font-size: 24px; }
.metric-change { font-size: 13px; font-weight: 500; padding: 4px 8px; border-radius: 20px; }
.metric-change.positive { background: #10B98120; color: var(--success); }
.metric-change.negative { background: #EF444420; color: var(--danger); }
.metric-value { font-size: 28px; font-weight: 600; color: var(--text-primary); margin-bottom: 5px; }
.metric-label { color: var(--text-secondary); font-size: 14px; margin-bottom: 10px; }
.metric-compare { font-size: 12px; color: var(--text-tertiary); }
.metric-foot { position: absolute; right: 14px; bottom: 12px; font-size: 11px; color: var(--text-tertiary); }
.report-card { display: flex; gap: 15px; padding: 20px; cursor: pointer; transition: all 0.2s; text-decoration: none; color: inherit; border: none; background: var(--bg-surface); text-align: left; width: 100%; appearance: none; }
.report-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.report-icon { font-size: 32px; }
.report-content { flex: 1; }
.report-content h3 { font-size: 16px; font-weight: 600; margin-bottom: 8px; }
.report-content p { font-size: 13px; color: var(--text-secondary); margin-bottom: 12px; }
.report-meta { display: flex; justify-content: space-between; align-items: center; }
.report-period { font-size: 12px; color: var(--text-tertiary); }
.saved-reports { margin-top: 15px; }
.saved-item { display: flex; align-items: center; gap: 15px; padding: 15px; border-radius: var(--radius-md); }
.saved-item:hover { background: var(--accent-soft); }
.saved-icon { font-size: 20px; }
.saved-info { flex: 1; }
.saved-name { font-weight: 500; margin-bottom: 4px; }
.saved-date { font-size: 12px; color: var(--text-secondary); }
.reports-charts-row { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px; }
.report-chart-card { overflow: hidden; }
.chart-frame { position: relative; height: 320px; min-height: 320px; max-height: 320px; }
.chart-frame canvas { width: 100% !important; height: 100% !important; display: block; }
.fiscal-help-card { margin-bottom: 20px; padding: 12px 14px; font-size: 13px; color: var(--text-secondary); }

.report-modal {
    position: fixed;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 60;
}

.report-modal.is-visible {
    display: flex;
}

.modal-overlay {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
}

.modal-panel {
    position: relative;
    width: min(920px, 92vw);
    max-height: 85vh;
    overflow: auto;
    background: var(--bg-surface);
    border-radius: var(--radius-lg);
    padding: 24px;
    box-shadow: var(--shadow-lg);
}

.modal-panel-large {
    width: min(1200px, 95vw);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.modal-close {
    border: none;
    background: transparent;
    font-size: 24px;
    line-height: 1;
    color: var(--text-secondary);
    cursor: pointer;
}

.modal-body p {
    color: var(--text-secondary);
    margin-bottom: 16px;
}

.modal-body-pdf {
    padding: 0;
    overflow: hidden;
}

.modal-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin-bottom: 18px;
}

.modal-stat {
    padding: 14px;
    border-radius: var(--radius-md);
    background: color-mix(in srgb, var(--bg-surface) 88%, var(--accent) 12%);
}

.modal-stat-label {
    font-size: 12px;
    color: var(--text-secondary);
    margin-bottom: 6px;
}

.modal-stat-value {
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary);
}

.modal-chart h4 {
    margin-bottom: 12px;
    font-size: 14px;
    color: var(--text-secondary);
}

.modal-chart .chart-frame {
    height: 260px;
    min-height: 260px;
}

.pdf-preview-frame {
    width: 100%;
    height: 70vh;
    border: none;
    background: #f8f9fa;
}

.pdf-loader {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px;
    background: var(--bg-surface);
}

.loader-spinner {
    width: 48px;
    height: 48px;
    border: 4px solid var(--border-light);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 16px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.btn-sm {
    padding: 8px 14px;
    font-size: 13px;
}

@media (max-width: 1024px) {
    .reports-charts-row { grid-template-columns: 1fr; }
    .period-custom-range {
        grid-template-columns: 1fr;
    }
    .period-separator {
        justify-self: center;
    }
}
</style>

<script>
(function () {
    const hasChart = typeof Chart !== 'undefined';
    const monthlyLabels = <?= json_encode($monthlySeries['labels'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const monthlyIncomeInvoices = <?= json_encode($monthlySeries['income_invoice'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const monthlyIncomeOccasional = <?= json_encode($monthlySeries['income_occasional'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const monthlyIncomeTotal = <?= json_encode($monthlySeries['income'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const monthlyExpenses = <?= json_encode($monthlySeries['expenses'] ?? [], JSON_UNESCAPED_UNICODE) ?>;

    const expenseLabels = <?= json_encode($expenseBreakdown['labels'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const expenseValues = <?= json_encode($expenseBreakdown['values'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const expenseColors = <?= json_encode($expenseBreakdown['colors'] ?? [], JSON_UNESCAPED_UNICODE) ?>;

    const monthlyCtx = document.getElementById('monthly-chart')?.getContext('2d');
    if (hasChart && monthlyCtx) {
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: monthlyLabels,
                datasets: [
                    {
                        label: 'Revenus factures',
                        data: monthlyIncomeInvoices,
                        backgroundColor: '#2563EB',
                        borderRadius: 6
                    },
                    {
                        label: 'Revenus occasionnels',
                        data: monthlyIncomeOccasional,
                        backgroundColor: '#10B981',
                        borderRadius: 6
                    },
                    {
                        label: 'Charges',
                        data: monthlyExpenses,
                        backgroundColor: '#EF4444',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    const revenueCtx = document.getElementById('revenue-chart')?.getContext('2d');
    if (hasChart && revenueCtx) {
        new Chart(revenueCtx, {
            type: 'doughnut',
            data: {
                labels: expenseLabels,
                datasets: [{
                    data: expenseValues,
                    backgroundColor: expenseColors.length ? expenseColors : ['#94A3B8'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                },
                cutout: '60%'
            }
        });
    }

    const modalTriggers = document.querySelectorAll('[data-modal-open]');
    const modals = document.querySelectorAll('.report-modal');
    const pdfModal = document.getElementById('pdf-modal');
    const openPdfBtn = document.getElementById('open-pdf-modal-btn');
    const pdfIframe = document.getElementById('pdf-preview-iframe');
    const pdfLoader = document.getElementById('pdf-loader');
    const pdfError = document.getElementById('pdf-error');
    const pdfContentUrl = <?= json_encode($pdfContentUrl, JSON_UNESCAPED_UNICODE) ?>;
    
    let annualChart = null;

    const closeAllModals = () => {
        modals.forEach((modal) => {
            modal.classList.remove('is-visible');
            modal.setAttribute('aria-hidden', 'true');
        });
        document.body.classList.remove('modal-open');
        
        // Arrêter le chargement du PDF si la modale est fermée
        if (pdfIframe) {
            pdfIframe.src = '';
            pdfIframe.style.display = 'none';
        }
        if (pdfLoader) pdfLoader.style.display = 'flex';
        if (pdfError) pdfError.style.display = 'none';
    };

    const openModal = (key) => {
        const modal = document.querySelector(`.report-modal[data-report-modal=\"${key}\"]`);
        if (!modal) return;
        modal.classList.add('is-visible');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');

        if (key === '/reports' && !annualChart && hasChart) {
            const annualCtx = document.getElementById('annual-performance-chart')?.getContext('2d');
            if (annualCtx) {
                annualChart = new Chart(annualCtx, {
                    type: 'line',
                    data: {
                        labels: monthlyLabels,
                        datasets: [
                            {
                                label: 'Revenus',
                                data: monthlyIncomeTotal,
                                borderColor: '#2563EB',
                                backgroundColor: 'rgba(37, 99, 235, 0.12)',
                                fill: true,
                                tension: 0.35
                            },
                            {
                                label: 'Charges',
                                data: monthlyExpenses,
                                borderColor: '#EF4444',
                                backgroundColor: 'rgba(239, 68, 68, 0.12)',
                                fill: true,
                                tension: 0.35
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                });
            }
        }
    };

    // Ouvrir la modale PDF
    if (openPdfBtn && pdfModal) {
        openPdfBtn.addEventListener('click', () => {
            // Réinitialiser l'état
            if (pdfLoader) pdfLoader.style.display = 'flex';
            if (pdfIframe) {
                pdfIframe.style.display = 'none';
                pdfIframe.src = '';
            }
            if (pdfError) pdfError.style.display = 'none';
            
            // Ouvrir la modale
            pdfModal.classList.add('is-visible');
            pdfModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');
            
            // Charger le PDF
            if (pdfIframe) {
                // Ajouter un timestamp pour éviter le cache
                const timestamp = new Date().getTime();
                pdfIframe.src = pdfContentUrl + '&t=' + timestamp;
                
                // Gérer le chargement
                pdfIframe.onload = () => {
                    if (pdfLoader) pdfLoader.style.display = 'none';
                    if (pdfIframe) pdfIframe.style.display = 'block';
                    if (pdfError) pdfError.style.display = 'none';
                };
                
                pdfIframe.onerror = () => {
                    if (pdfLoader) pdfLoader.style.display = 'none';
                    if (pdfIframe) pdfIframe.style.display = 'none';
                    if (pdfError) pdfError.style.display = 'block';
                };
            }
        });
    }

    modalTriggers.forEach((trigger) => {
        trigger.addEventListener('click', () => {
            openModal(trigger.getAttribute('data-modal-open') || '');
        });
    });

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        if (target.hasAttribute('data-modal-close')) {
            closeAllModals();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllModals();
        }
    });

    // Gérer le téléchargement du PDF
    const downloadBtn = document.getElementById('download-pdf-btn');
    if (downloadBtn) {
        downloadBtn.addEventListener('click', () => {
            // Optionnel : ajouter un indicateur de téléchargement
            console.log('Téléchargement du PDF en cours...');
        });
    }
})();
</script>