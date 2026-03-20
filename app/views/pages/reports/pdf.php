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
    'cash_available' => 0,
    'cash_in' => 0,
    'cash_out' => 0,
    'stock_value' => 0,
    'stock_purchases' => 0,
];
$monthlySeries = $monthlySeries ?? [
    'labels' => [],
    'income_invoice' => [],
    'income_occasional' => [],
    'income' => [],
    'expenses' => [],
];
$revenueBreakdown = $revenueBreakdown ?? ['labels' => [], 'values' => []];
$expenseBreakdown = $expenseBreakdown ?? ['labels' => [], 'values' => [], 'colors' => []];
$profitLoss = $profitLoss ?? ['revenue' => 0, 'cogs' => 0, 'gross_margin' => 0, 'expenses' => 0, 'net' => 0];
$balanceSheet = $balanceSheet ?? ['assets' => 0, 'liabilities' => 0, 'equity' => 0];
$tvaReport = $tvaReport ?? ['vat_total' => 0, 'vat_due' => 0, 'vat_paid' => 0];
$company = $company ?? [];
$autoDownload = (bool) ($autoDownload ?? false);

$companyName = (string) ($company['name'] ?? 'Entreprise');
$companyEmail = (string) ($company['email'] ?? '');
$companyPhone = (string) ($company['phone'] ?? '');
$companyAddress = trim((string) ($company['address'] ?? ''));
$companyCity = trim((string) ($company['city'] ?? ''));
$companyCountry = trim((string) ($company['country'] ?? ''));
$companyLogo = (string) ($company['invoice_logo_url'] ?? '');
$brandColor = (string) ($company['invoice_brand_color'] ?? '#0F172A');
if ($brandColor === '') {
    $brandColor = '#0F172A';
}

$formatMoney = static fn($value) => '$' . number_format((float) $value, 2);
$reportTitles = [
    'overview' => 'Rapport global',
    'profit-loss' => 'Compte de resultat',
    'balance-sheet' => 'Bilan comptable',
    'tva' => 'Declaration TVA',
];
$reportTitle = $reportTitles[$reportType] ?? 'Rapport';
$periodLabel = (string) ($period['label'] ?? (($period['from_date'] ?? '') . ' - ' . ($period['to_date'] ?? '')));
$generatedAt = date('d/m/Y H:i');

$overviewMetrics = [
    ['label' => 'Chiffre d\'affaires', 'value' => $overview['revenue'] ?? 0],
    ['label' => 'CMV', 'value' => $overview['cogs'] ?? 0],
    ['label' => 'Marge brute', 'value' => $overview['gross_margin'] ?? 0],
    ['label' => 'Autres depenses', 'value' => $overview['expenses'] ?? 0],
    ['label' => 'Resultat net', 'value' => $overview['net'] ?? 0],
    ['label' => 'Tresorerie', 'value' => $overview['cash_available'] ?? 0],
    ['label' => 'Creances clients', 'value' => $overview['client_debt'] ?? 0],
    ['label' => 'Valeur stock', 'value' => $overview['stock_value'] ?? 0],
];

$profitLossMetrics = [
    ['label' => 'Chiffre d\'affaires', 'value' => $profitLoss['revenue'] ?? 0],
    ['label' => 'CMV', 'value' => $profitLoss['cogs'] ?? 0],
    ['label' => 'Marge brute', 'value' => $profitLoss['gross_margin'] ?? 0],
    ['label' => 'Autres depenses', 'value' => $profitLoss['expenses'] ?? 0],
    ['label' => 'Resultat net', 'value' => $profitLoss['net'] ?? 0],
];

$balanceMetrics = [
    ['label' => 'Actifs', 'value' => $balanceSheet['assets'] ?? 0],
    ['label' => 'Passifs', 'value' => $balanceSheet['liabilities'] ?? 0],
    ['label' => 'Capitaux propres', 'value' => $balanceSheet['equity'] ?? 0],
];

$tvaMetrics = [
    ['label' => 'TVA totale', 'value' => $tvaReport['vat_total'] ?? 0],
    ['label' => 'TVA due', 'value' => $tvaReport['vat_due'] ?? 0],
    ['label' => 'TVA reglee', 'value' => $tvaReport['vat_paid'] ?? 0],
];

$contactLine = trim(implode(' - ', array_filter([
    $companyEmail !== '' ? $companyEmail : null,
    $companyPhone !== '' ? $companyPhone : null,
], static fn($value) => $value !== null)));
$addressLine = trim(implode(', ', array_filter([
    $companyAddress !== '' ? $companyAddress : null,
    $companyCity !== '' ? $companyCity : null,
    $companyCountry !== '' ? $companyCountry : null,
], static fn($value) => $value !== null)));
?>

<div class="report-pdf-page">
    <section class="report-sheet" style="--brand-color: <?= htmlspecialchars($brandColor, ENT_QUOTES, 'UTF-8') ?>;">
        <header class="report-header">
            <div class="brand-block">
                <?php if ($companyLogo !== ''): ?>
                <img class="brand-logo" src="<?= htmlspecialchars($companyLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Logo entreprise">
                <?php endif; ?>
                <div>
                    <h1 class="brand-name"><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></h1>
                    <?php if ($addressLine !== ''): ?>
                    <p class="muted"><?= htmlspecialchars($addressLine, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php if ($contactLine !== ''): ?>
                    <p class="muted"><?= htmlspecialchars($contactLine, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="report-meta">
                <p class="report-kicker">Rapport financier</p>
                <h2><?= htmlspecialchars($reportTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                <p><strong>Periode:</strong> <?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Genere le:</strong> <?= htmlspecialchars($generatedAt, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </header>

        <section class="metrics-grid">
            <?php
                $metrics = $overviewMetrics;
                if ($reportType === 'profit-loss') {
                    $metrics = $profitLossMetrics;
                } elseif ($reportType === 'balance-sheet') {
                    $metrics = $balanceMetrics;
                } elseif ($reportType === 'tva') {
                    $metrics = $tvaMetrics;
                }
            ?>
            <?php foreach ($metrics as $metric): ?>
            <article class="metric-card">
                <p class="metric-label"><?= htmlspecialchars((string) ($metric['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="metric-value"><?= htmlspecialchars($formatMoney($metric['value'] ?? 0), ENT_QUOTES, 'UTF-8') ?></p>
            </article>
            <?php endforeach; ?>
        </section>

        <?php if ($reportType === 'overview'): ?>
        <div class="split-grid">
            <section class="section-card">
                <h3>Repartition des revenus</h3>
                <table class="clean-table">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $labels = is_array($revenueBreakdown['labels'] ?? null) ? $revenueBreakdown['labels'] : [];
                            $values = is_array($revenueBreakdown['values'] ?? null) ? $revenueBreakdown['values'] : [];
                        ?>
                        <?php if ($labels === []): ?>
                        <tr>
                            <td colspan="2" class="muted">Aucune donnee disponible.</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($labels as $idx => $label): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($formatMoney($values[$idx] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <section class="section-card">
                <h3>Repartition des depenses</h3>
                <table class="clean-table">
                    <thead>
                        <tr>
                            <th>Poste</th>
                            <th>Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $expenseLabels = is_array($expenseBreakdown['labels'] ?? null) ? $expenseBreakdown['labels'] : [];
                            $expenseValues = is_array($expenseBreakdown['values'] ?? null) ? $expenseBreakdown['values'] : [];
                        ?>
                        <?php if ($expenseLabels === []): ?>
                        <tr>
                            <td colspan="2" class="muted">Aucune donnee disponible.</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($expenseLabels as $idx => $label): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($formatMoney($expenseValues[$idx] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>

        <section class="section-card">
            <h3>Serie mensuelle</h3>
            <table class="clean-table">
                <thead>
                    <tr>
                        <th>Mois</th>
                        <th>Revenus factures</th>
                        <th>Revenus occasionnels</th>
                        <th>Total revenus</th>
                        <th>Depenses</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        $monthLabels = is_array($monthlySeries['labels'] ?? null) ? $monthlySeries['labels'] : [];
                        $incomeInvoice = is_array($monthlySeries['income_invoice'] ?? null) ? $monthlySeries['income_invoice'] : [];
                        $incomeOccasional = is_array($monthlySeries['income_occasional'] ?? null) ? $monthlySeries['income_occasional'] : [];
                        $incomeTotals = is_array($monthlySeries['income'] ?? null) ? $monthlySeries['income'] : [];
                        $expenseTotals = is_array($monthlySeries['expenses'] ?? null) ? $monthlySeries['expenses'] : [];
                    ?>
                    <?php if ($monthLabels === []): ?>
                    <tr>
                        <td colspan="5" class="muted">Aucune donnee disponible.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($monthLabels as $idx => $label): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($formatMoney($incomeInvoice[$idx] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($formatMoney($incomeOccasional[$idx] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($formatMoney($incomeTotals[$idx] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($formatMoney($expenseTotals[$idx] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <?php endif; ?>

        <?php if ($reportType === 'profit-loss'): ?>
        <section class="section-card">
            <h3>Compte de resultat</h3>
            <table class="clean-table">
                <tbody>
                    <tr>
                        <td>Chiffre d'affaires</td>
                        <td><?= htmlspecialchars($formatMoney($profitLoss['revenue'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <tr>
                        <td>CMV</td>
                        <td><?= htmlspecialchars($formatMoney($profitLoss['cogs'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <tr>
                        <td>Marge brute</td>
                        <td><?= htmlspecialchars($formatMoney($profitLoss['gross_margin'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <tr>
                        <td>Autres depenses</td>
                        <td><?= htmlspecialchars($formatMoney($profitLoss['expenses'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <tr>
                        <td>Resultat net</td>
                        <td><?= htmlspecialchars($formatMoney($profitLoss['net'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                </tbody>
            </table>
        </section>
        <?php endif; ?>

        <?php if ($reportType === 'balance-sheet'): ?>
        <section class="section-card">
            <h3>Bilan comptable</h3>
            <table class="clean-table">
                <tbody>
                    <tr>
                        <td>Actifs</td>
                        <td><?= htmlspecialchars($formatMoney($balanceSheet['assets'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <tr>
                        <td>Passifs</td>
                        <td><?= htmlspecialchars($formatMoney($balanceSheet['liabilities'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <tr>
                        <td>Capitaux propres</td>
                        <td><?= htmlspecialchars($formatMoney($balanceSheet['equity'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                </tbody>
            </table>
        </section>
        <?php endif; ?>

        <?php if ($reportType === 'tva'): ?>
        <section class="section-card">
            <h3>Declaration TVA</h3>
            <table class="clean-table">
                <tbody>
                    <tr>
                        <td>TVA totale</td>
                        <td><?= htmlspecialchars($formatMoney($tvaReport['vat_total'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <tr>
                        <td>TVA due</td>
                        <td><?= htmlspecialchars($formatMoney($tvaReport['vat_due'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <tr>
                        <td>TVA reglee</td>
                        <td><?= htmlspecialchars($formatMoney($tvaReport['vat_paid'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                </tbody>
            </table>
        </section>
        <?php endif; ?>
    </section>
</div>

<style>
body {
    background: #e2e8f0;
}
.app .sidebar,
.app .main > .header {
    display: none !important;
}
.app .main {
    margin-left: 0 !important;
    width: 100% !important;
}
.view-container {
    padding: 0 !important;
}
.report-pdf-page {
    padding: 20px;
}
.report-sheet {
    width: 210mm;
    min-height: 297mm;
    background: #ffffff;
    margin: 0 auto;
    padding: 16mm 14mm;
    border-radius: 14px;
    box-shadow: 0 18px 34px rgba(15, 23, 42, 0.16);
    color: #0f172a;
}
.report-header {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    align-items: flex-start;
    margin-bottom: 18px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e2e8f0;
}
.brand-block {
    display: flex;
    gap: 14px;
    align-items: center;
}
.brand-logo {
    width: 64px;
    height: 64px;
    object-fit: contain;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
}
.brand-name {
    margin: 0 0 4px 0;
    font-size: 20px;
    color: var(--brand-color);
}
.report-meta {
    text-align: right;
}
.report-kicker {
    margin: 0 0 6px 0;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    font-size: 11px;
    color: #64748b;
}
.report-meta h2 {
    margin: 0 0 6px 0;
    font-size: 20px;
}
.report-meta p {
    margin: 2px 0;
    font-size: 12px;
}
.muted {
    color: #64748b;
    font-size: 12px;
    margin: 2px 0;
}
.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 12px;
    margin-bottom: 18px;
}
.metric-card {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px;
    background: #f8fafc;
    border-top: 4px solid var(--brand-color);
}
.metric-label {
    margin: 0;
    font-size: 12px;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}
.metric-value {
    margin: 6px 0 0 0;
    font-size: 18px;
    font-weight: 700;
}
.split-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 16px;
}
.section-card {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px;
    background: #ffffff;
    margin-bottom: 16px;
}
.section-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
}
.clean-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.clean-table th,
.clean-table td {
    padding: 8px 6px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}
.clean-table th {
    background: var(--brand-color);
    color: #ffffff;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: 11px;
}

@media (max-width: 980px) {
    .report-sheet {
        width: 100%;
        min-height: auto;
    }
    .report-header {
        flex-direction: column;
        text-align: left;
    }
    .report-meta {
        text-align: left;
    }
    .split-grid {
        grid-template-columns: 1fr;
    }
}

@media print {
    @page {
        size: A4;
        margin: 10mm;
    }
    html, body {
        background: #ffffff !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    body * { visibility: hidden; }
    .report-sheet, .report-sheet * { visibility: visible; }
    .report-pdf-page { padding: 0; }
    .report-sheet {
        position: absolute;
        left: 0;
        top: 0;
        margin: 0;
        width: 190mm;
        min-height: 277mm;
        border-radius: 0;
        box-shadow: none;
    }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<?php if ($autoDownload): ?>
<script>
window.addEventListener('load', async () => {
    if (typeof window.html2pdf !== 'function') {
        return;
    }
    const sheet = document.querySelector('.report-sheet');
    if (!sheet) {
        return;
    }
    const fileName = <?= json_encode('rapport-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($reportTitle)) . '.pdf', JSON_UNESCAPED_UNICODE) ?>;
    await window.html2pdf()
        .set({
            margin: 0,
            filename: fileName,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: {
                scale: 2,
                useCORS: true,
                backgroundColor: '#ffffff',
            },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak: { mode: ['avoid-all', 'css', 'legacy'] },
        })
        .from(sheet)
        .save();
});
</script>
<?php endif; ?>
