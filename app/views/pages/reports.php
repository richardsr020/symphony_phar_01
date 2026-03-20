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
    'cogs' => 0,
    'gross_margin' => 0,
    'expenses' => 0,
    'net' => 0,
    'vat_due' => 0,
    'client_debt' => 0,
    'profit_margin' => 0,
    'expense_ratio' => 0,
    'cash_available' => 0,
    'cash_in' => 0,
    'cash_out' => 0,
    'stock_value' => 0,
    'stock_purchases' => 0,
    'revenue_delta' => 0,
    'cogs_delta' => 0,
    'expenses_delta' => 0,
    'net_delta' => 0,
    'revenue_prev' => 0,
    'cogs_prev' => 0,
    'expenses_prev' => 0,
    'net_prev' => 0,
    'revenue_trend' => 0,
    'cogs_trend' => 0,
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
$profitLoss = $profitLoss ?? ['revenue' => 0, 'cogs' => 0, 'gross_margin' => 0, 'expenses' => 0, 'net' => 0];
$balanceSheet = $balanceSheet ?? ['assets' => 0, 'liabilities' => 0, 'equity' => 0];
$tvaReport = $tvaReport ?? ['vat_total' => 0, 'vat_due' => 0, 'vat_paid' => 0];
$currentFiscalPeriod = $currentFiscalPeriod ?? null;
$periodLabel = (string) ($period['label'] ?? '');
$showCustomRange = true;
$grossMargin = (float) ($overview['gross_margin'] ?? 0);
$grossMarginRate = (float) ($overview['profit_margin'] ?? 0);
$expenseRatio = (float) ($overview['expense_ratio'] ?? 0);
$clientDebt = (float) ($overview['client_debt'] ?? 0);
$cashAvailable = (float) ($overview['cash_available'] ?? 0);
$cashIn = (float) ($overview['cash_in'] ?? 0);
$cashOut = (float) ($overview['cash_out'] ?? 0);
$stockValue = (float) ($overview['stock_value'] ?? 0);
$stockPurchases = (float) ($overview['stock_purchases'] ?? 0);
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
    ['name' => 'Compte de resultat', 'desc' => 'Chiffre d\'affaires, CMV, resultat net', 'icon' => 'fas fa-chart-bar', 'period' => 'Mensuel', 'route' => '/reports/profit-loss'],
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
$pdfContentUrl = '/reports/pdf-content' . ($exportQuery !== [] ? '?' . http_build_query($exportQuery) : '');
$pdfDownloadUrl = '/reports/pdf-download' . ($exportQuery !== [] ? '?' . http_build_query($exportQuery) : '');
$profitLossSummary = sprintf(
    'Sur la periode %s, votre chiffre d\'affaires est de $%s, le CMV de $%s et le resultat net de $%s.',
    $periodLabel,
    number_format((float) $profitLoss['revenue'], 2),
    number_format((float) $profitLoss['cogs'], 2),
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
    'Sur %s, le chiffre d\'affaires atteint $%s, le CMV $%s et le resultat net $%s (marge brute %s%%).',
    $periodLabel,
    number_format((float) $overview['revenue'], 2),
    number_format((float) $overview['cogs'], 2),
    number_format((float) $overview['net'], 2),
    number_format($grossMarginRate, 1)
);
?>

<div class="reports-page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Rapports</h1>
            <p class="page-subtitle">Analyse financiere sur la periode <?= htmlspecialchars((string) $period['label'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="header-actions">
            <button type="button" class="btn btn-primary" id="open-pdf-modal-btn"><i class="fa-solid fa-file-pdf"></i> Apercu PDF</button>
            <a class="btn btn-soft" href="<?= htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8') ?>" data-no-async="true"><i class="fa-solid fa-file-excel"></i> Exporter Excel</a>
        </div>
    </div>

    <form class="period-selector" data-auto-filter="true" method="GET" action="<?= htmlspecialchars($baseRoute, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="period" value="custom">
        <div class="period-custom-range">
            <input type="date" class="filter-input" name="from_date" value="<?= htmlspecialchars((string) $period['from_date'], ENT_QUOTES, 'UTF-8') ?>">
            <span class="period-separator">→</span>
            <input type="date" class="filter-input" name="to_date" value="<?= htmlspecialchars((string) $period['to_date'], ENT_QUOTES, 'UTF-8') ?>">
            <button class="btn btn-primary" type="submit">Appliquer</button>
        </div>
        <p class="filter-hint">Choisissez une periode precise.</p>
    </form>

    <div class="fiscal-info">
        Periode rapport: <?= htmlspecialchars((string) $period['label'], ENT_QUOTES, 'UTF-8') ?>
        <?php if ((string) ($period['period'] ?? '') === 'fiscal'): ?>
            (exercice fiscal actif)
        <?php elseif (is_array($currentFiscalPeriod)): ?>
            | Exercice actif: <?= htmlspecialchars((string) $currentFiscalPeriod['start_date'], ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars((string) $currentFiscalPeriod['end_date'], ENT_QUOTES, 'UTF-8') ?>
        <?php endif; ?>
    </div>

    <div class="metrics-grid">
        <div class="metric-item">
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

        <div class="metric-item">
            <div class="metric-header">
                <span class="metric-icon"><i class="fas fa-boxes-stacked"></i></span>
                <span class="metric-change <?= ((float) $overview['cogs_trend'] <= 0) ? 'positive' : 'negative' ?>">
                    <?= ((float) $overview['cogs_trend'] >= 0 ? '+' : '') . number_format((float) $overview['cogs_trend'], 1) ?>%
                </span>
            </div>
            <div class="metric-value">$<?= number_format((float) $overview['cogs'], 2) ?></div>
            <div class="metric-label">CMV</div>
            <div class="metric-compare">vs $<?= number_format((float) $overview['cogs_prev'], 2) ?> periode precedente</div>
            <div class="metric-foot">Δ $<?= htmlspecialchars($formatDelta((float) ($overview['cogs_delta'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></div>
        </div>

        <div class="metric-item">
            <div class="metric-header">
                <span class="metric-icon"><i class="fas fa-percent"></i></span>
                <span class="metric-change <?= $grossMarginRate >= 0 ? 'positive' : 'negative' ?>">
                    <?= ($grossMarginRate >= 0 ? '+' : '') . number_format($grossMarginRate, 1) ?>%
                </span>
            </div>
            <div class="metric-value">$<?= number_format($grossMargin, 2) ?></div>
            <div class="metric-label">Marge brute</div>
            <div class="metric-compare">CA - CMV</div>
            <div class="metric-foot">Taux <?= number_format($grossMarginRate, 1) ?>%</div>
        </div>

        <div class="metric-item">
            <div class="metric-header">
                <span class="metric-icon"><i class="fas fa-chart-line-down"></i></span>
                <span class="metric-change <?= ((float) $overview['expenses_trend'] <= 0) ? 'positive' : 'negative' ?>">
                    <?= ((float) $overview['expenses_trend'] >= 0 ? '+' : '') . number_format((float) $overview['expenses_trend'], 1) ?>%
                </span>
            </div>
            <div class="metric-value">$<?= number_format((float) $overview['expenses'], 2) ?></div>
            <div class="metric-label">Autres depenses</div>
            <div class="metric-compare">hors achat stock</div>
            <div class="metric-foot">Δ $<?= htmlspecialchars($formatDelta($expensesDelta), ENT_QUOTES, 'UTF-8') ?></div>
        </div>

        <div class="metric-item">
            <div class="metric-header">
                <span class="metric-icon"><i class="fas fa-circle-check"></i></span>
                <span class="metric-change <?= ((float) $overview['net_trend'] >= 0) ? 'positive' : 'negative' ?>">
                    <?= ((float) $overview['net_trend'] >= 0 ? '+' : '') . number_format((float) $overview['net_trend'], 1) ?>%
                </span>
            </div>
            <div class="metric-value">$<?= number_format((float) $overview['net'], 2) ?></div>
            <div class="metric-label">Resultat net</div>
            <div class="metric-compare">CA - CMV - depenses</div>
            <div class="metric-foot">Δ $<?= htmlspecialchars($formatDelta($netDelta), ENT_QUOTES, 'UTF-8') ?></div>
        </div>

        <div class="metric-item">
            <div class="metric-header">
                <span class="metric-icon"><i class="fas fa-coins"></i></span>
                <span class="metric-change">Solde</span>
            </div>
            <div class="metric-value">$<?= number_format($cashAvailable, 2) ?></div>
            <div class="metric-label">Tresorerie</div>
            <div class="metric-compare">Au <?= htmlspecialchars((string) ($period['to_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="metric-foot">Encaissements $<?= number_format($cashIn, 2) ?> · Decaissements $<?= number_format($cashOut, 2) ?></div>
        </div>

        <div class="metric-item">
            <div class="metric-header">
                <span class="metric-icon"><i class="fas fa-hand-holding-dollar"></i></span>
                <span class="metric-change <?= $debtRatio <= 30 ? 'positive' : 'negative' ?>">
                    <?= number_format($debtRatio, 1) ?>%
                </span>
            </div>
            <div class="metric-value">$<?= number_format($clientDebt, 2) ?></div>
            <div class="metric-label">Creances clients</div>
            <div class="metric-compare">Impayes au <?= htmlspecialchars((string) ($period['to_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="metric-foot">CA $<?= number_format((float) $overview['revenue'], 2) ?></div>
        </div>

        <div class="metric-item">
            <div class="metric-header">
                <span class="metric-icon"><i class="fas fa-warehouse"></i></span>
                <span class="metric-change">Stock</span>
            </div>
            <div class="metric-value">$<?= number_format($stockValue, 2) ?></div>
            <div class="metric-label">Valeur stock</div>
            <div class="metric-compare">Achat stock $<?= number_format($stockPurchases, 2) ?></div>
            <div class="metric-foot">Alerte si stock bas</div>
        </div>
    </div>

    <div class="reports-grid">
        <?php foreach ($reportCards as $card): ?>
        <button class="report-card" type="button" data-modal-open="<?= htmlspecialchars((string) $card['route'], ENT_QUOTES, 'UTF-8') ?>">
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

    <div class="charts-row">
        <div class="chart-card">
            <h3>Evolution mensuelle</h3>
            <div class="chart-frame">
                <canvas id="monthly-chart"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <h3>Repartition charges par type</h3>
            <div class="chart-frame">
                <canvas id="revenue-chart"></canvas>
            </div>
        </div>
    </div>

    <div class="summary-section">
        <h3>Resume detaille</h3>

        <?php if ($reportType === 'profit-loss'): ?>
        <div class="summary-items">
            <div class="summary-item"><span class="summary-icon"><i class="fas fa-dollar-sign"></i></span><span>Chiffre d'affaires</span><strong>$<?= number_format((float) $profitLoss['revenue'], 2) ?></strong></div>
            <div class="summary-item"><span class="summary-icon"><i class="fas fa-boxes-stacked"></i></span><span>CMV</span><strong>$<?= number_format((float) $profitLoss['cogs'], 2) ?></strong></div>
            <div class="summary-item"><span class="summary-icon"><i class="fas fa-chart-pie"></i></span><span>Marge brute</span><strong>$<?= number_format((float) $profitLoss['gross_margin'], 2) ?></strong></div>
            <div class="summary-item"><span class="summary-icon"><i class="fas fa-money-bill-wave"></i></span><span>Autres depenses</span><strong>$<?= number_format((float) $profitLoss['expenses'], 2) ?></strong></div>
            <div class="summary-item"><span class="summary-icon"><i class="fas fa-chart-line"></i></span><span>Resultat net</span><strong>$<?= number_format((float) $profitLoss['net'], 2) ?></strong></div>
        </div>
        <?php elseif ($reportType === 'balance-sheet'): ?>
        <div class="summary-items">
            <div class="summary-item"><span class="summary-icon"><i class="fas fa-university"></i></span><span>Actifs</span><strong>$<?= number_format((float) $balanceSheet['assets'], 2) ?></strong></div>
            <div class="summary-item"><span class="summary-icon"><i class="fas fa-chart-line-down"></i></span><span>Passifs</span><strong>$<?= number_format((float) $balanceSheet['liabilities'], 2) ?></strong></div>
            <div class="summary-item"><span class="summary-icon"><i class="fas fa-building"></i></span><span>Capitaux propres</span><strong>$<?= number_format((float) $balanceSheet['equity'], 2) ?></strong></div>
        </div>
        <?php elseif ($reportType === 'tva'): ?>
        <div class="summary-items">
            <div class="summary-item"><span class="summary-icon"><i class="fas fa-file-invoice-dollar"></i></span><span>TVA totale</span><strong>$<?= number_format((float) $tvaReport['vat_total'], 2) ?></strong></div>
            <div class="summary-item"><span class="summary-icon"><i class="fas fa-clock"></i></span><span>TVA a payer</span><strong>$<?= number_format((float) $tvaReport['vat_due'], 2) ?></strong></div>
            <div class="summary-item"><span class="summary-icon"><i class="fas fa-check"></i></span><span>TVA deja reglee</span><strong>$<?= number_format((float) $tvaReport['vat_paid'], 2) ?></strong></div>
        </div>
        <?php else: ?>
        <div class="summary-items">
            <div class="summary-item"><span class="summary-icon"><i class="fas fa-calendar"></i></span><span>Periode</span><strong><?= htmlspecialchars((string) $period['label'], ENT_QUOTES, 'UTF-8') ?></strong></div>
            <div class="summary-item"><span class="summary-icon"><i class="fas fa-chart-bar"></i></span><span>Rapport actif</span><strong>Vue globale</strong></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modales -->
    <div class="report-modal" id="pdf-modal" aria-hidden="true">
        <div class="modal-overlay" data-modal-close></div>
        <div class="modal-panel modal-panel-large">
            <div class="modal-header">
                <h3>Aperçu PDF - Rapport <?= htmlspecialchars($reportType === 'overview' ? 'global' : $reportType, ENT_QUOTES, 'UTF-8') ?></h3>
                <div class="modal-actions">
                    <a href="<?= htmlspecialchars($pdfDownloadUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary btn-sm" data-no-async="true">Télécharger PDF</a>
                    <button class="modal-close" type="button" data-modal-close>&times;</button>
                </div>
            </div>
            <div class="modal-body modal-body-pdf">
                <div class="pdf-loader" id="pdf-loader">
                    <div class="loader-spinner"></div>
                    <p>Generation du PDF en cours...</p>
                </div>
                <iframe id="pdf-preview-iframe" class="pdf-preview-frame" src="" style="display:none;"></iframe>
                <div class="pdf-error" id="pdf-error" style="display:none;">Erreur lors du chargement du PDF.</div>
            </div>
        </div>
    </div>

    <div class="report-modal" data-report-modal="/reports/profit-loss" aria-hidden="true">
        <div class="modal-overlay" data-modal-close></div>
        <div class="modal-panel">
            <div class="modal-header">
                <h3>Compte de resultat</h3>
                <button class="modal-close" type="button" data-modal-close>&times;</button>
            </div>
            <div class="modal-body">
                <p><?= htmlspecialchars($profitLossSummary, ENT_QUOTES, 'UTF-8') ?></p>
                <div class="modal-stats">
                    <div><span>Chiffre d'affaires</span><strong>$<?= number_format((float) $profitLoss['revenue'], 2) ?></strong></div>
                    <div><span>CMV</span><strong>$<?= number_format((float) $profitLoss['cogs'], 2) ?></strong></div>
                    <div><span>Marge brute</span><strong>$<?= number_format((float) $profitLoss['gross_margin'], 2) ?></strong></div>
                    <div><span>Autres depenses</span><strong>$<?= number_format((float) $profitLoss['expenses'], 2) ?></strong></div>
                    <div><span>Resultat net</span><strong>$<?= number_format((float) $profitLoss['net'], 2) ?></strong></div>
                </div>
                <a class="btn btn-primary" href="<?= htmlspecialchars($buildReportRoute('/reports/profit-loss'), ENT_QUOTES, 'UTF-8') ?>">Ouvrir le rapport complet</a>
            </div>
        </div>
    </div>

    <div class="report-modal" data-report-modal="/reports/balance" aria-hidden="true">
        <div class="modal-overlay" data-modal-close></div>
        <div class="modal-panel">
            <div class="modal-header">
                <h3>Bilan comptable</h3>
                <button class="modal-close" type="button" data-modal-close>&times;</button>
            </div>
            <div class="modal-body">
                <p><?= htmlspecialchars($balanceSummary, ENT_QUOTES, 'UTF-8') ?></p>
                <div class="modal-stats">
                    <div><span>Actifs</span><strong>$<?= number_format((float) $balanceSheet['assets'], 2) ?></strong></div>
                    <div><span>Passifs</span><strong>$<?= number_format((float) $balanceSheet['liabilities'], 2) ?></strong></div>
                    <div><span>Capitaux propres</span><strong>$<?= number_format((float) $balanceSheet['equity'], 2) ?></strong></div>
                </div>
                <a class="btn btn-primary" href="<?= htmlspecialchars($buildReportRoute('/reports/balance'), ENT_QUOTES, 'UTF-8') ?>">Ouvrir le rapport complet</a>
            </div>
        </div>
    </div>

    <div class="report-modal" data-report-modal="/reports/tva" aria-hidden="true">
        <div class="modal-overlay" data-modal-close></div>
        <div class="modal-panel">
            <div class="modal-header">
                <h3>Declaration TVA</h3>
                <button class="modal-close" type="button" data-modal-close>&times;</button>
            </div>
            <div class="modal-body">
                <p><?= htmlspecialchars($tvaSummary, ENT_QUOTES, 'UTF-8') ?></p>
                <div class="modal-stats">
                    <div><span>TVA totale</span><strong>$<?= number_format((float) $tvaReport['vat_total'], 2) ?></strong></div>
                    <div><span>TVA a payer</span><strong>$<?= number_format((float) $tvaReport['vat_due'], 2) ?></strong></div>
                    <div><span>TVA deja reglee</span><strong>$<?= number_format((float) $tvaReport['vat_paid'], 2) ?></strong></div>
                </div>
                <a class="btn btn-primary" href="<?= htmlspecialchars($buildReportRoute('/reports/tva'), ENT_QUOTES, 'UTF-8') ?>">Ouvrir le rapport complet</a>
            </div>
        </div>
    </div>

    <div class="report-modal" data-report-modal="/reports" aria-hidden="true">
        <div class="modal-overlay" data-modal-close></div>
        <div class="modal-panel">
            <div class="modal-header">
                <h3>Vue globale</h3>
                <button class="modal-close" type="button" data-modal-close>&times;</button>
            </div>
            <div class="modal-body">
                <p><?= htmlspecialchars($overviewSummary, ENT_QUOTES, 'UTF-8') ?></p>
                <div class="modal-stats">
                    <div><span>Marge brute</span><strong><?= number_format($grossMarginRate, 1) ?>%</strong></div>
                    <div><span>Taux charges</span><strong><?= number_format($expenseRatio, 1) ?>%</strong></div>
                    <div><span>Taux dettes clients</span><strong><?= number_format($debtRatio, 1) ?>%</strong></div>
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
/* ===== ULTRA-MINIMALIST TRANSPARENT DESIGN ===== */
.reports-page {
    width: 100%;
    padding: 24px 32px;
    margin: 0;
    box-sizing: border-box;
}

/* Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    flex-wrap: wrap;
    gap: 16px;
}

.page-title {
    font-size: 28px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 4px 0;
}

.page-subtitle {
    color: var(--text-secondary);
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 12px;
}

/* Period selector - transparent */
.period-selector {
    margin-bottom: 20px;
    padding: 0 0 16px 0;
    border-bottom: 1px solid var(--border-light);
}

.period-custom-range {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.filter-input {
    padding: 10px 12px;
    border: 1px solid var(--border-light);
    border-radius: 12px;
    background: transparent;
    color: var(--text-primary);
    font-size: 14px;
}

.filter-input:focus {
    outline: none;
    border-color: var(--accent);
}

.period-separator {
    color: var(--text-secondary);
}

.filter-hint {
    margin: 8px 0 0;
    font-size: 12px;
    color: var(--text-secondary);
}

/* Fiscal info */
.fiscal-info {
    margin-bottom: 32px;
    padding: 0 0 12px 0;
    border-bottom: 1px solid var(--border-light);
    font-size: 13px;
    color: var(--text-secondary);
}

/* Metrics grid - no backgrounds */
.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 32px;
    margin-bottom: 48px;
}

.metric-item {
    padding: 0;
    border: none;
    background: transparent;
}

.metric-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.metric-icon {
    font-size: 24px;
    color: var(--accent);
}

.metric-change {
    font-size: 12px;
    font-weight: 500;
    padding: 2px 8px;
    border-radius: 20px;
}

.metric-change.positive {
    background: rgba(34, 197, 94, 0.12);
    color: #22c55e;
}

.metric-change.negative {
    background: rgba(239, 68, 68, 0.12);
    color: #ef4444;
}

.metric-value {
    font-size: 32px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.metric-label {
    font-size: 13px;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.metric-compare {
    font-size: 11px;
    color: var(--text-secondary);
    opacity: 0.7;
}

.metric-foot {
    margin-top: 8px;
    font-size: 11px;
    color: var(--text-secondary);
    border-top: 1px solid var(--border-light);
    padding-top: 6px;
}

/* Reports grid */
.reports-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 48px;
}

.report-card {
    display: flex;
    gap: 16px;
    padding: 20px;
    background: transparent;
    border: 1px solid var(--border-light);
    border-radius: 16px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: left;
    width: 100%;
}

.report-card:hover {
    border-color: var(--accent);
    transform: translateY(-2px);
}

.report-icon {
    font-size: 28px;
    color: var(--accent);
}

.report-content {
    flex: 1;
}

.report-content h3 {
    font-size: 15px;
    font-weight: 600;
    margin: 0 0 6px 0;
    color: var(--text-primary);
}

.report-content p {
    font-size: 12px;
    color: var(--text-secondary);
    margin: 0 0 8px 0;
}

.report-meta {
    display: flex;
    justify-content: space-between;
}

.report-period {
    font-size: 10px;
    color: var(--text-secondary);
    background: var(--accent-soft);
    padding: 2px 8px;
    border-radius: 12px;
}

/* Charts row */
.charts-row {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
    margin-bottom: 48px;
}

.chart-card {
    border: none;
    background: transparent;
    padding: 0;
}

.chart-card h3 {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 16px 0;
}

.chart-frame {
    position: relative;
    height: 320px;
    border: 1px solid var(--border-light);
    border-radius: 16px;
    padding: 16px;
    background: transparent;
}

/* Summary section */
.summary-section {
    margin-bottom: 24px;
}

.summary-section h3 {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 16px 0;
}

.summary-items {
    display: flex;
    flex-wrap: wrap;
    gap: 24px;
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 16px;
    border: 1px solid var(--border-light);
    border-radius: 40px;
    background: transparent;
}

.summary-icon {
    font-size: 14px;
    color: var(--accent);
}

.summary-item span:not(.summary-icon) {
    font-size: 13px;
    color: var(--text-secondary);
}

.summary-item strong {
    font-size: 14px;
    color: var(--text-primary);
}

/* Buttons */
.btn {
    padding: 8px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: var(--accent);
    color: white;
    border: none;
}

.btn-primary:hover {
    background: #436c0b;
}

.btn-soft {
    background: var(--accent-soft);
    color: var(--accent);
    border: none;
}

.btn-soft:hover {
    background: rgba(84, 136, 14, 0.2);
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

/* Modal styles - transparent */
.report-modal {
    position: fixed;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.report-modal.is-visible {
    display: flex;
}

.modal-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
}

.modal-panel {
    position: relative;
    width: min(600px, 90vw);
    max-height: 85vh;
    overflow: auto;
    background: var(--bg-surface);
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
}

.modal-panel-large {
    width: min(1000px, 95vw);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-light);
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
    color: var(--text-primary);
}

.modal-actions {
    display: flex;
    gap: 12px;
    align-items: center;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: var(--text-secondary);
}

.modal-body p {
    color: var(--text-secondary);
    margin-bottom: 20px;
}

.modal-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

.modal-stats div {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    border: 1px solid var(--border-light);
    border-radius: 12px;
    background: transparent;
}

.modal-stats span {
    font-size: 13px;
    color: var(--text-secondary);
}

.modal-stats strong {
    font-size: 16px;
    color: var(--text-primary);
}

.modal-chart h4 {
    font-size: 14px;
    margin: 0 0 12px 0;
    color: var(--text-secondary);
}

.modal-chart .chart-frame {
    height: 260px;
    border: 1px solid var(--border-light);
    border-radius: 12px;
    padding: 12px;
}

/* PDF modal */
.modal-body-pdf {
    padding: 0;
}

.pdf-loader {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px;
}

.loader-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid var(--border-light);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 12px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.pdf-preview-frame {
    width: 100%;
    height: 70vh;
    border: none;
}

.pdf-error {
    text-align: center;
    padding: 40px;
    color: var(--danger);
}

/* Responsive */
@media (max-width: 1024px) {
    .reports-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .charts-row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .reports-page {
        padding: 16px 20px;
    }
    .reports-grid {
        grid-template-columns: 1fr;
    }
    .metrics-grid {
        gap: 20px;
    }
    .period-custom-range {
        flex-direction: column;
        align-items: stretch;
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
                    { label: 'Revenus factures', data: monthlyIncomeInvoices, backgroundColor: '#2563EB', borderRadius: 6 },
                    { label: 'Revenus occasionnels', data: monthlyIncomeOccasional, backgroundColor: '#10B981', borderRadius: 6 },
                    { label: 'Charges', data: monthlyExpenses, backgroundColor: '#EF4444', borderRadius: 6 }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });
    }

    const revenueCtx = document.getElementById('revenue-chart')?.getContext('2d');
    if (hasChart && revenueCtx) {
        new Chart(revenueCtx, {
            type: 'doughnut',
            data: { labels: expenseLabels, datasets: [{ data: expenseValues, backgroundColor: expenseColors.length ? expenseColors : ['#94A3B8'], borderWidth: 0 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '60%' }
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
        modals.forEach(m => { m.classList.remove('is-visible'); m.setAttribute('aria-hidden', 'true'); });
        document.body.classList.remove('modal-open');
        if (pdfIframe) { pdfIframe.src = ''; pdfIframe.style.display = 'none'; }
        if (pdfLoader) pdfLoader.style.display = 'flex';
        if (pdfError) pdfError.style.display = 'none';
    };

    const openModal = (key) => {
        const modal = document.querySelector(`.report-modal[data-report-modal="${key}"]`);
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
                            { label: 'Revenus', data: monthlyIncomeTotal, borderColor: '#2563EB', backgroundColor: 'rgba(37, 99, 235, 0.12)', fill: true, tension: 0.35 },
                            { label: 'Charges', data: monthlyExpenses, borderColor: '#EF4444', backgroundColor: 'rgba(239, 68, 68, 0.12)', fill: true, tension: 0.35 }
                        ]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
                });
            }
        }
    };

    if (openPdfBtn && pdfModal) {
        openPdfBtn.addEventListener('click', () => {
            if (pdfLoader) pdfLoader.style.display = 'flex';
            if (pdfIframe) { pdfIframe.style.display = 'none'; pdfIframe.src = ''; }
            if (pdfError) pdfError.style.display = 'none';
            pdfModal.classList.add('is-visible');
            document.body.classList.add('modal-open');
            if (pdfIframe) {
                pdfIframe.src = pdfContentUrl + '&t=' + Date.now();
                pdfIframe.onload = () => {
                    if (pdfLoader) pdfLoader.style.display = 'none';
                    if (pdfIframe) pdfIframe.style.display = 'block';
                };
                pdfIframe.onerror = () => {
                    if (pdfLoader) pdfLoader.style.display = 'none';
                    if (pdfError) pdfError.style.display = 'block';
                };
            }
        });
    }

    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', () => openModal(trigger.getAttribute('data-modal-open') || ''));
    });

    document.addEventListener('click', (e) => {
        if (e.target.hasAttribute?.('data-modal-close')) closeAllModals();
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeAllModals(); });
})();
</script>
