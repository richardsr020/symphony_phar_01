<?php
$userName = $userName ?? 'Utilisateur';
$stats = $stats ?? [
    'cash' => 0,
    'revenue' => 0,
    'expenses' => 0,
    'vat_due' => 0,
    'cash_invoices' => 0,
    'cash_transactions' => 0,
    'revenue_invoices' => 0,
    'revenue_occasional' => 0,
    'expenses_transactions' => 0,
    'vat_from_invoices' => 0,
    'vat_paid' => 0,
    'revenue_trend' => 0,
    'expenses_trend' => 0,
];
$recentTransactions = $recentTransactions ?? [];
$insights = $insights ?? [];
$alerts = $alerts ?? [];
$cashflow = $cashflow ?? ['labels' => [], 'net' => [], 'invoice_income' => [], 'occasional_income' => [], 'expenses' => []];
$expenseBreakdown = $expenseBreakdown ?? ['labels' => [], 'values' => [], 'colors' => []];
$currentPeriod = $currentPeriod ?? null;
$cashReconciliation = $cashReconciliation ?? [];
$canAccessTransactions = $canAccessTransactions ?? true;
$canViewTransactionHistory = $canViewTransactionHistory ?? true;
$canManageTransactions = $canManageTransactions ?? true;
$canManageInvoices = $canManageInvoices ?? true;
$canAccessSettings = $canAccessSettings ?? true;
$flashError = $flashError ?? '';
$stockAlertCount = 0;
$outOfStockCount = 0;
$lowStockCount = 0;
$expiryAlertCount = 0;
$criticalAlertCount = 0;
foreach ($alerts as $alert) {
    $type = (string) ($alert['type'] ?? '');
    $statusKey = (string) ($alert['status_key'] ?? '');
    $severity = (string) ($alert['severity'] ?? '');

    if (in_array($type, ['stock', 'expiry'], true)) {
        $stockAlertCount++;
    }
    if ($statusKey === 'out_of_stock') {
        $outOfStockCount++;
    }
    if ($statusKey === 'low_stock') {
        $lowStockCount++;
    }
    if ($type === 'expiry') {
        $expiryAlertCount++;
    }
    if ($severity === 'critical') {
        $criticalAlertCount++;
    }
}

$statCards = [
     [
        'label' => 'Total vendu Aujourdhui',
        'value' => (float) ($stats['daily_sales_total'] ?? 0),
        'format' => 'currency',
        'trend' => null,
        'parts' => [
            'Factures du jour',
        ],
    ],    
[
        'label' => 'Total Encaisse Aujourdhui',
        'value' => (float) ($stats['daily_sales_collected'] ?? 0),
        'format' => 'currency',
        'trend' => null,
        'parts' => [
            'Encaissement du jour',
        ],
    ],
    [
        'label' => 'Total vente avec dette',
        'value' => (int) ($stats['period_sales_with_debt_count'] ?? $stats['daily_sales_with_debt_count'] ?? 0),
        'format' => 'count',
        'trend' => null,
        'parts' => [
            'Factures à solde',
        ],
    ],
    [
        'label' => 'Total de dette (montant dû)',
        'value' => (float) ($stats['period_debt_amount'] ?? $stats['daily_debt_amount'] ?? 0),
        'format' => 'currency',
        'trend' => null,
        'parts' => [
            'Somme restant à encaisser',
        ],
    ],
];

$revenueTotal = (float) ($stats['revenue'] ?? 0);
$cashTotal = (float) ($stats['cash'] ?? 0);
$revenueInvoices = (float) ($stats['revenue_invoices'] ?? 0);
$revenueOccasional = (float) ($stats['revenue_occasional'] ?? 0);
$cashInvoices = (float) ($stats['cash_invoices'] ?? 0);
$cashTransactions = (float) ($stats['cash_transactions'] ?? 0);
$expensesTransactions = (float) ($stats['expenses_transactions'] ?? 0);
$invoiceGap = $cashInvoices - $revenueInvoices;
$transactionGap = $cashTransactions - $revenueOccasional;
$totalGap = $cashTotal - $revenueTotal;
$formatCurrency = static function ($value): string {
    return '$' . number_format((float) $value, 2);
};
?>

<div class="dashboard">
    <?php if ($flashError !== ''): ?>
    <div class="flash-message flash-error"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div>
            <h1 class="page-title">Bonjour, <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?> 👋</h1>
            
            <p class="page-subtitle">Voici votre situation financière du <?php
        $mois = [
            1=>'janvier','février','mars','avril','mai','juin',
            'juillet','août','septembre','octobre','novembre','décembre'
                ];
                echo date('d').' '.$mois[date('n')].' '.date('Y');?>
            </p>
        </div>
        <div class="banner-actions">
            <?php if ($canManageTransactions): ?>
            <button class="btn btn-add" onclick="window.location.href='/transactions/create'">
                <span>+</span> Nouvelle transaction
            </button>
            <?php endif; ?>
            <?php if ($canManageInvoices): ?>
            <button class="btn btn-add" onclick="window.location.href='/invoices/create'">
                <span>📄</span> Créer facture
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($alerts !== []): ?>
    <div class="alert-banner">
        <div class="alert-banner-head">
            <div>
                <div class="alert-banner-title">
                    Alertes & avertissements (<?= count($alerts) ?>)
                </div>
                <div class="alert-banner-summary">
                    <?= htmlspecialchars(
                        $stockAlertCount > 0
                            ? 'Surveillez vos alertes stock sans afficher toutes les lignes ici.'
                            : 'Des alertes actives demandent votre attention.',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>
            </div>
            <?php if ($stockAlertCount > 0): ?>
            <a class="btn-icon alert-banner-detail-btn" href="/stock/alerts" title="Voir le detail des alertes stock" aria-label="Voir le detail des alertes stock">
                <i class="fa-solid fa-circle-info"></i>
            </a>
            <?php endif; ?>
        </div>
        <div class="alert-banner-items">
            <?php if ($criticalAlertCount > 0): ?>
            <span class="alert-banner-item alert-banner-item-critical">Critiques: <?= (int) $criticalAlertCount ?></span>
            <?php endif; ?>
            <?php if ($outOfStockCount > 0): ?>
            <span class="alert-banner-item alert-banner-item-critical">Ruptures: <?= (int) $outOfStockCount ?></span>
            <?php endif; ?>
            <?php if ($lowStockCount > 0): ?>
            <span class="alert-banner-item alert-banner-item-warning">Stock bas: <?= (int) $lowStockCount ?></span>
            <?php endif; ?>
            <?php if ($expiryAlertCount > 0): ?>
            <span class="alert-banner-item alert-banner-item-warning">Expirations: <?= (int) $expiryAlertCount ?></span>
            <?php endif; ?>
            <?php if ($stockAlertCount === 0): ?>
            <span class="alert-banner-item">Alertes actives: <?= (int) count($alerts) ?></span>
            <?php endif; ?>
            <?php if ($stockAlertCount > 0): ?>
            <span class="alert-banner-item">Voir la page detail pour chaque message</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="exercise-card">
        <div>
            <div class="exercise-label">Periode d'exercice comptable active</div>
            <?php if (is_array($currentPeriod)): ?>
            <div class="exercise-value">
                <?= htmlspecialchars((string) $currentPeriod['start_date'], ENT_QUOTES, 'UTF-8') ?>
                <span>→</span>
                <?= htmlspecialchars((string) $currentPeriod['end_date'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php else: ?>
            <div class="exercise-value">Non configuree</div>
            <?php endif; ?>
        </div>
        <?php if ($canAccessSettings): ?>
        <a class="btn btn-soft" href="/settings?tab=fiscal">Configurer l'exercice</a>
        <?php endif; ?>
    </div>

    <!-- Stats Grid -->
    <div class="stat-grid">
        <?php foreach ($statCards as $idx => $stat): ?>
        <?php $gradientClass = 'gradient-' . ((int) ($idx % 4) + 1); ?>
        
        <div class="stat-card <?= htmlspecialchars($gradientClass, ENT_QUOTES, 'UTF-8') ?>">
            <div class="stat-label"><?= $stat['label'] ?>
            </div>
            
            <div class="stat-value">
                <?php if (($stat['format'] ?? 'currency') === 'count'): ?>
                    <?= number_format((int) $stat['value'], 0, ',', ' ') ?>
                    <span class="stat-unit">factures</span>
                <?php else: ?>
                    $<?= number_format((float) $stat['value'], 2) ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($stat['parts']) && is_array($stat['parts'])): ?>

            <div class="stat-formula">
                <?= htmlspecialchars(implode(' + ', $stat['parts']), ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>
            <?php if ($stat['trend'] !== null): ?>
            <div class="stat-trend">
                <?php if ((float) $stat['trend'] > 0): ?>
                <span class="trend-up">▲ <?= number_format((float) $stat['trend'], 1) ?>%</span>
                <?php elseif ((float) $stat['trend'] < 0): ?>
                <span class="trend-down">▼ <?= number_format(abs((float) $stat['trend']), 1) ?>%</span>
                <?php else: ?>
                <span class="text-secondary">Stable</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>


    <!-- Charts Row -->
    <div class="charts-row">
        <div class="card">
            <div class="card-header">
                <h3>Tresorerie et revenus (factures vs occasionnels)</h3>
                <select class="chart-period" id="cashflow-period">
                    <option value="7d">7 derniers jours</option>
                    <option value="30d" selected>30 derniers jours</option>
                    <option value="year">Cette année</option>
                </select>
            </div>
            <div class="chart-frame">
                <canvas id="cashflow-chart"></canvas>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Répartition des dépenses</h3>
                <select class="chart-period" id="expenses-period">
                    <option value="month" selected>Ce mois</option>
                    <option value="last_month">Mois dernier</option>
                    <option value="year">Cette année</option>
                </select>
            </div>
            <div class="chart-frame">
                <canvas id="expenses-chart"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Transactions & Insights -->
    <div class="dashboard-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
        <!-- Recent Transactions -->
        <div class="card">
            <div class="card-header">
                <h3>Transactions récentes</h3>
                <?php if ($canAccessTransactions && $canViewTransactionHistory): ?>
                <a href="/transactions" class="btn btn-soft">Voir tout</a>
                <?php endif; ?>
            </div>
            
            <div class="transactions-list">
                <?php if (!$canViewTransactionHistory): ?>
                <p class="text-secondary">Votre role ne permet pas d afficher l historique des transactions.</p>
                <?php endif; ?>
                <?php if ($recentTransactions === []): ?>
                <?php if ($canViewTransactionHistory): ?>
                <p class="text-secondary">Aucune transaction recente.</p>
                <?php endif; ?>
                <?php endif; ?>
                <?php foreach ($recentTransactions as $transaction): ?>
                <div class="transaction-item" <?= $canAccessTransactions ? "onclick=\"window.location.href='/transactions'\"" : '' ?>>
                    <div class="transaction-icon">
                        <?= $transaction['type'] === 'income' ? '💰' : '💳' ?>
                    </div>
                    <div class="transaction-info">
                        <div class="transaction-name"><?= htmlspecialchars((string) $transaction['description'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="transaction-category"><?= htmlspecialchars((string) $transaction['category'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="transaction-amount <?= $transaction['type'] ?>">
                        <?= $transaction['type'] === 'income' ? '+' : '-' ?>$<?= number_format((float) $transaction['amount'], 2) ?>
                    </div>
                    <div class="transaction-date"><?= htmlspecialchars(date('d/m/Y', strtotime((string) $transaction['transaction_date'])), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- AI Insights -->
        <div class="card">
            <div class="card-header">
                <h3>🔍 Statistiques Kombiphar</h3>
                <span class="badge-ai">IA temps réel</span>
            </div>
            
            <div class="insights-list">
                <?php if ($insights === []): ?>
                <p class="text-secondary">Aucun insight disponible.</p>
                <?php endif; ?>
                <?php foreach ($insights as $insight): ?>
                <?php $insightType = in_array((string) ($insight['type'] ?? 'info'), ['warning', 'info', 'success'], true) ? (string) $insight['type'] : 'info'; ?>
                <div class="insight-item insight-<?= htmlspecialchars($insightType, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="insight-icon">
                        <?php if ($insightType === 'warning'): ?>⚠️
                        <?php elseif ($insightType === 'info'): ?>💡
                        <?php else: ?>✅
                        <?php endif; ?>
                    </div>
                    <div class="insight-content">
                        <strong><?= htmlspecialchars((string) $insight['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <p><?= htmlspecialchars((string) $insight['message'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions" style="margin-top: 20px;">
                <button class="btn btn-soft btn-block" onclick="window.location.href='/invoices/create'">
                    Enregistrer une nouvelle vente
                </button>
            </div>
        </div>
    </div>

</div>

<style>
.flash-message {
    border-radius: var(--radius-md);
    padding: 12px 14px;
    margin-bottom: 16px;
}

.flash-error {
    background: rgba(239, 68, 68, 0.14);
    color: var(--danger);
}
.alert-banner {
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
    background: #fef3c7;
    border: 1px solid #facc15;
    color: #92400e;
    padding: 14px 18px;
    border-radius: var(--radius-md);
    margin-bottom: 16px;
}
.alert-banner-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.alert-banner-title {
    font-size: 13px;
    font-weight: 600;
}
.alert-banner-summary {
    font-size: 12px;
    opacity: 0.9;
}
.alert-banner-items {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 12px;
    font-size: 12px;
}
.alert-banner-item {
    background: rgba(146, 64, 14, 0.08);
    padding: 4px 8px;
    border-radius: 999px;
}
.alert-banner-item-critical {
    background: rgba(220, 38, 38, 0.16);
    color: #b91c1c;
}
.alert-banner-item-warning {
    background: rgba(234, 179, 8, 0.18);
    color: #92400e;
}
.alert-banner-detail-btn {
    color: #92400e;
    border-color: rgba(146, 64, 14, 0.2);
    background: rgba(255, 255, 255, 0.45);
}

.welcome-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.page-title {
    font-size: 28px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 5px;
}

.page-subtitle {
    color: var(--text-secondary);
}

.banner-actions {
    display: flex;
    gap: 12px;
}

.exercise-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
    padding: 14px 16px;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    background: linear-gradient(135deg, rgba(15, 157, 88, 0.12) 0%, rgba(14, 165, 233, 0.08) 100%);
}

.exercise-label {
    font-size: 12px;
    color: var(--text-secondary);
    margin-bottom: 4px;
}

.exercise-value {
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 8px;
}

.stat-formula {
    margin-top: 6px;
    font-size: 11px;
    color: var(--text-tertiary);
    line-height: 1.35;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.card-header h3 {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
}

.chart-period {
    padding: 6px 12px;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-sm);
    background: var(--bg-surface);
    color: var(--text-primary);
    font-size: 13px;
}

.charts-row {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
}

.chart-frame {
    position: relative;
    height: 320px;
    min-height: 320px;
    max-height: 320px;
}

.chart-frame canvas {
    width: 100% !important;
    height: 100% !important;
    display: block;
}

.transactions-list {
    margin-top: 10px;
}

.transaction-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 12px;
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all 0.2s;
}

.transaction-item:hover {
    background: var(--accent-soft);
}

.transaction-icon {
    width: 40px;
    height: 40px;
    background: var(--accent-soft);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.transaction-info {
    flex: 1;
}

.transaction-name {
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.transaction-category {
    font-size: 12px;
    color: var(--text-secondary);
}

.transaction-amount {
    font-weight: 600;
    font-size: 16px;
}

.transaction-amount.income {
    color: var(--success);
}

.transaction-amount.expense {
    color: var(--text-primary);
}

.transaction-date {
    font-size: 12px;
    color: var(--text-secondary);
}

.insights-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.insight-item {
    display: flex;
    gap: 12px;
    padding: 15px;
    background: var(--bg-primary);
    border-radius: var(--radius-md);
    border-left: 3px solid;
}

.insight-warning {
    border-left-color: var(--warning);
}
.insight-info {
    border-left-color: var(--info);
}
.insight-success {
    border-left-color: var(--success);
}

.insight-icon {
    font-size: 20px;
}

.insight-content strong {
    display: block;
    margin-bottom: 5px;
    font-size: 14px;
}

.insight-content p {
    font-size: 13px;
    color: var(--text-secondary);
}

.badge-ai {
    background: var(--accent);
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
}

.bill-item {
    padding: 15px;
    background: var(--bg-primary);
    border-radius: var(--radius-md);
}

.bill-date {
    font-size: 12px;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.bill-name {
    font-weight: 500;
    margin-bottom: 5px;
}

.bill-amount {
    font-weight: 600;
    color: var(--accent);
}

.recon-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}
.recon-item {
    background: transparent;
    border: 1px solid var(--border-light);
    border-radius: 10px;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 13px;
}
.recon-item strong {
    font-size: 15px;
}
.recon-total {
    background: rgba(84, 136, 14, 0.08);
    border-color: var(--accent);
}
.recon-pos { color: #0f9d58; }
.recon-neg { color: #dc2626; }
.recon-table {
    margin-top: 6px;
}
.recon-table-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

@media (max-width: 1024px) {
    .charts-row,
    .dashboard-grid {
        grid-template-columns: 1fr !important;
    }
}

/* Dark theme adjustments */
@media (prefers-color-scheme: dark) {
    .recon-item {
        background: transparent;
        border-color: rgba(255, 255, 255, 0.1);
    }
    .recon-total {
        background: rgba(84, 136, 14, 0.15);
    }
}
</style>

<script>
(function () {
    if (typeof Chart === 'undefined') {
        return;
    }
    const cashflowLabels = <?= json_encode($cashflow['labels'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const cashflowNet = <?= json_encode($cashflow['net'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const cashflowInvoiceIncome = <?= json_encode($cashflow['invoice_income'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const cashflowOccasionalIncome = <?= json_encode($cashflow['occasional_income'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const cashflowExpenses = <?= json_encode($cashflow['expenses'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const expenseLabels = <?= json_encode($expenseBreakdown['labels'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const expenseValues = <?= json_encode($expenseBreakdown['values'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const expenseColors = <?= json_encode($expenseBreakdown['colors'] ?? [], JSON_UNESCAPED_UNICODE) ?>;

    const cashflowSelect = document.getElementById('cashflow-period');
    const expensesSelect = document.getElementById('expenses-period');
    const defaultCashflowPeriod = cashflowSelect ? cashflowSelect.value : '30d';
    const defaultExpensesPeriod = expensesSelect ? expensesSelect.value : 'month';

    let cashflowChart = null;
    let expensesChart = null;

    // Cashflow Chart
    const cashflowCtx = document.getElementById('cashflow-chart')?.getContext('2d');
    if (cashflowCtx) {
        cashflowChart = new Chart(cashflowCtx, {
            type: 'line',
            data: {
                labels: cashflowLabels.length ? cashflowLabels : ['Sem 1'],
                datasets: [
                    {
                        label: 'Revenus factures',
                        data: cashflowInvoiceIncome.length ? cashflowInvoiceIncome : [0],
                        borderColor: '#2563EB',
                        backgroundColor: 'rgba(37, 99, 235, 0.15)',
                        tension: 0.35,
                        fill: false
                    },
                    {
                        label: 'Revenus occasionnels',
                        data: cashflowOccasionalIncome.length ? cashflowOccasionalIncome : [0],
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16, 185, 129, 0.15)',
                        tension: 0.35,
                        fill: false
                    },
                    {
                        label: 'Depenses',
                        data: cashflowExpenses.length ? cashflowExpenses : [0],
                        borderColor: '#EF4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        tension: 0.35,
                        fill: false
                    },
                    {
                        label: 'Tresorerie nette',
                        data: cashflowNet.length ? cashflowNet : [0],
                        borderColor: '#F97316',
                        backgroundColor: 'rgba(249, 115, 22, 0.16)',
                        borderWidth: 2.5,
                        tension: 0.35,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                },
                scales: {
                    y: {
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { callback: (v) => '$' + v.toLocaleString() }
                    }
                }
            }
        });
    }

    // Expenses Chart
    const expensesCtx = document.getElementById('expenses-chart')?.getContext('2d');
    if (expensesCtx) {
        expensesChart = new Chart(expensesCtx, {
            type: 'doughnut',
            data: {
                labels: expenseLabels.length ? expenseLabels : ['Aucune depense'],
                datasets: [{
                    data: expenseValues.length ? expenseValues : [0],
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
                cutout: '70%'
            }
        });
    }

    const applyDashboardChartPayload = (payload) => {
        if (!payload || typeof payload !== 'object') {
            return;
        }

        const cashflowPayload = payload.cashflow || {};
        const expensesPayload = payload.expenseBreakdown || {};

        if (cashflowChart) {
            const labels = Array.isArray(cashflowPayload.labels) && cashflowPayload.labels.length > 0 ? cashflowPayload.labels : ['-'];
            cashflowChart.data.labels = labels;
            cashflowChart.data.datasets[0].data = Array.isArray(cashflowPayload.invoice_income) && cashflowPayload.invoice_income.length > 0 ? cashflowPayload.invoice_income : [0];
            cashflowChart.data.datasets[1].data = Array.isArray(cashflowPayload.occasional_income) && cashflowPayload.occasional_income.length > 0 ? cashflowPayload.occasional_income : [0];
            cashflowChart.data.datasets[2].data = Array.isArray(cashflowPayload.expenses) && cashflowPayload.expenses.length > 0 ? cashflowPayload.expenses : [0];
            cashflowChart.data.datasets[3].data = Array.isArray(cashflowPayload.net) && cashflowPayload.net.length > 0 ? cashflowPayload.net : [0];
            cashflowChart.update();
        }

        if (expensesChart) {
            expensesChart.data.labels = Array.isArray(expensesPayload.labels) && expensesPayload.labels.length > 0 ? expensesPayload.labels : ['Aucune depense'];
            expensesChart.data.datasets[0].data = Array.isArray(expensesPayload.values) && expensesPayload.values.length > 0 ? expensesPayload.values : [0];
            expensesChart.data.datasets[0].backgroundColor = Array.isArray(expensesPayload.colors) && expensesPayload.colors.length > 0 ? expensesPayload.colors : ['#94A3B8'];
            expensesChart.update();
        }
    };

    const refreshDashboardCharts = () => {
        const selectedCashflow = cashflowSelect ? cashflowSelect.value : defaultCashflowPeriod;
        const selectedExpenses = expensesSelect ? expensesSelect.value : defaultExpensesPeriod;
        const query = new URLSearchParams({
            refresh: '1',
            cashflow_period: selectedCashflow,
            expenses_period: selectedExpenses,
        });

        fetch('/api/dashboard?' + query.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('dashboard_fetch_failed');
                }
                return response.json();
            })
            .then((payload) => {
                applyDashboardChartPayload(payload);
            })
            .catch(() => {});
    };

    if (cashflowSelect) {
        cashflowSelect.addEventListener('change', refreshDashboardCharts);
    }
    if (expensesSelect) {
        expensesSelect.addEventListener('change', refreshDashboardCharts);
    }
})();
</script>
