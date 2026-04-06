<?php
$periods = $periods ?? [];
$selectedPeriod = $selectedPeriod ?? null;
$groupBy = $groupBy ?? 'month';
$segment = $segment ?? 'all';
$tracking = $tracking ?? ['groups' => [], 'summary' => []];
$regularThreshold = $regularThreshold ?? 2;
$selectedClientLedger = $selectedClientLedger ?? null;
$selectedClientName = $selectedClientName ?? '';
$selectedClientPhone = $selectedClientPhone ?? '';
$csrfToken = App\Core\Security::generateCSRF();
$flashSuccess = (string) ($_GET['success'] ?? '');
$flashError = (string) ($_GET['error'] ?? '');

$formatMoney = static function ($value): string {
    return '$' . number_format((float) $value, 2);
};

$groupByLabels = [
    'quarter' => 'Trimestres',
    'month' => 'Mois',
    'week' => 'Semaines',
    'day' => 'Jours',
];

$segmentLabels = [
    'all' => 'Tous les clients',
    'regular' => 'Clients reguliers',
    'debt' => 'Clients en dette',
    'known' => 'Clients connus',
    'anonymous' => 'Clients anonymes',
];

$periodLabel = '-';
if (is_array($selectedPeriod)) {
    $periodLabel = sprintf(
        '%s (%s au %s)',
        (string) ($selectedPeriod['label'] ?? 'Exercice'),
        (string) ($selectedPeriod['start_date'] ?? '-'),
        (string) ($selectedPeriod['end_date'] ?? '-')
    );
}
$summary = $tracking['summary'] ?? [
    'clients' => 0,
    'regular' => 0,
    'debt' => 0,
    'known' => 0,
    'anonymous' => 0,
    'invoices' => 0,
    'total' => 0,
    'paid' => 0,
    'debt_amount' => 0,
];

$flashSuccessMessages = [
    'client_created' => 'Client créé avec succès.',
];
$flashErrorMessages = [
    'client_invalid' => 'Veuillez renseigner au moins un nom ou un téléphone.',
    'client_create_failed' => 'Impossible de créer le client pour le moment.',
    'clients_forbidden' => 'Accès refusé pour la gestion des clients.',
];
$flashSuccessMessage = $flashSuccessMessages[$flashSuccess] ?? '';
$flashErrorMessage = $flashErrorMessages[$flashError] ?? '';

$exportSummaryQuery = array_filter([
    'period_id' => is_array($selectedPeriod) ? (int) ($selectedPeriod['id'] ?? 0) : 0,
    'group_by' => $groupBy,
    'segment' => $segment,
], static fn($value) => $value !== '' && $value !== 0 && $value !== null);
$exportSummaryUrl = '/suivi-clients/export-summary' . ($exportSummaryQuery !== [] ? '?' . http_build_query($exportSummaryQuery) : '');
?>

<div class="client-tracking-page">
    <!-- Page Header -->
    <div class="page-header animate-slide-down">
        <div>
            <h1 class="page-title">Suivi clients</h1>
            <p class="page-subtitle">Suivez les mouvements clients par exercice et periode.</p>
        </div>
        <div class="header-actions">
            <button type="button" class="btn btn-add" id="client-create-toggle">
                <i class="fa-solid fa-user-plus"></i> Nouveau client
            </button>
            <a class="btn btn-soft" href="<?= htmlspecialchars($exportSummaryUrl, ENT_QUOTES, 'UTF-8') ?>" data-no-async="true">
                <i class="fa-solid fa-file-excel"></i> Exporter synthese
            </a>
            <span class="badge badge-info"><?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>

    <?php if ($flashSuccessMessage !== ''): ?>
    <div class="flash-message flash-success animate-fade-in">
        <i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($flashSuccessMessage, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>
    <?php if ($flashErrorMessage !== ''): ?>
    <div class="flash-message flash-error animate-fade-in">
        <i class="fa-solid fa-exclamation-triangle"></i> <?= htmlspecialchars($flashErrorMessage, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <!-- Modal for client creation -->
    <div class="modal-overlay" id="client-modal-overlay">
        <div class="modal-container animate-modal">
            <div class="modal-header">
                <h3><i class="fa-solid fa-user-plus"></i> Nouveau client</h3>
                <button type="button" class="modal-close" id="modal-close-btn">&times;</button>
            </div>
            <form method="POST" action="/clients/store" id="client-create-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Nom client</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-user input-icon"></i>
                            <input type="text" name="name" placeholder="Ex: Pharmacie Centrale" class="form-input">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Téléphone</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-phone input-icon"></i>
                            <input type="text" name="phone" placeholder="Ex: 0812345678" class="form-input">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email (optionnel)</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-envelope input-icon"></i>
                            <input type="email" name="email" placeholder="contact@client.com" class="form-input">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Adresse (optionnel)</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-location-dot input-icon"></i>
                            <input type="text" name="address" placeholder="Adresse du client" class="form-input">
                        </div>
                    </div>
                    <p class="form-hint"><i class="fa-solid fa-info-circle"></i> Renseignez au moins un nom ou un téléphone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-soft" id="modal-cancel-btn">Annuler</button>
                    <button type="submit" class="btn btn-add">Créer le client</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters Section - Full width -->
    <div class="filters-section animate-slide-up">
        <form method="GET" action="/suivi-clients" class="client-filters">
            <div class="filter-group">
                <label class="filter-label">Exercice comptable</label>
                <div class="select-wrapper">
                    <i class="fa-solid fa-calendar select-icon"></i>
                    <select class="filter-select" name="period_id">
                        <?php foreach ($periods as $period): ?>
                        <?php $periodId = (int) ($period['id'] ?? 0); ?>
                        <option value="<?= $periodId ?>" <?= (is_array($selectedPeriod) && (int) ($selectedPeriod['id'] ?? 0) === $periodId) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($period['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="filter-group">
                <label class="filter-label">Regroupement</label>
                <div class="select-wrapper">
                    <i class="fa-solid fa-chart-line select-icon"></i>
                    <select class="filter-select" name="group_by">
                        <?php foreach ($groupByLabels as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $groupBy === $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="filter-group">
                <label class="filter-label">Segment clients</label>
                <div class="select-wrapper">
                    <i class="fa-solid fa-users select-icon"></i>
                    <select class="filter-select" name="segment">
                        <?php foreach ($segmentLabels as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $segment === $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-magnifying-glass"></i> Appliquer
                </button>
                <button type="button" class="btn btn-outline" onclick="window.location.href='/suivi-clients'">
                    <i class="fa-solid fa-rotate-right"></i> Réinitialiser
                </button>
            </div>
        </form>
        <p class="filter-hint">
            <i class="fa-solid fa-chart-simple"></i> Clients réguliers = au moins <?= (int) $regularThreshold ?> achats sur la période selectionnée.
        </p>
    </div>

    <!-- Stats Grid - Horizontal alignment full width -->
    <div class="stats-grid animate-slide-up" style="animation-delay: 0.05s;">
        <div class="stat-item">
            <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
            <div class="stat-info">
                <div class="stat-label">Clients suivis</div>
                <div class="stat-value"><?= (int) ($summary['clients'] ?? 0) ?></div>
            </div>
        </div>
        <div class="stat-item">
            <div class="stat-icon"><i class="fa-solid fa-star"></i></div>
            <div class="stat-info">
                <div class="stat-label">Clients réguliers</div>
                <div class="stat-value"><?= (int) ($summary['regular'] ?? 0) ?></div>
            </div>
        </div>
        <div class="stat-item">
            <div class="stat-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
            <div class="stat-info">
                <div class="stat-label">Clients en dette</div>
                <div class="stat-value"><?= (int) ($summary['debt'] ?? 0) ?></div>
            </div>
        </div>
        <div class="stat-item">
            <div class="stat-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
            <div class="stat-info">
                <div class="stat-label">Montant facturé</div>
                <div class="stat-value"><?= htmlspecialchars($formatMoney((float) ($summary['total'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
        <div class="stat-item">
            <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
            <div class="stat-info">
                <div class="stat-label">Reste à encaisser</div>
                <div class="stat-value"><?= htmlspecialchars($formatMoney((float) ($summary['debt_amount'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
    </div>

    <?php if (($tracking['groups'] ?? []) === []): ?>
    <div class="empty-state animate-fade-in">
        <i class="fa-solid fa-chart-line empty-icon"></i>
        <p>Aucun mouvement client pour la periode selectionnée.</p>
    </div>
    <?php endif; ?>

    <!-- Client Groups - Full width -->
    <?php foreach (($tracking['groups'] ?? []) as $group): ?>
    <div class="client-group animate-slide-up">
        <div class="client-group-header">
            <div>
                <h3><?= htmlspecialchars((string) ($group['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                <p><?= htmlspecialchars((string) ($group['period_start'] ?? ''), ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars((string) ($group['period_end'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="client-group-metrics">
                <span><i class="fa-solid fa-user"></i> <?= (int) ($group['client_count'] ?? 0) ?></span>
                <span><i class="fa-solid fa-file-invoice"></i> <?= (int) ($group['invoice_count'] ?? 0) ?></span>
                <span><i class="fa-solid fa-chart-line"></i> <?= htmlspecialchars($formatMoney((float) ($group['total_amount'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></span>
                <span><i class="fa-solid fa-hand-holding-dollar"></i> <?= htmlspecialchars($formatMoney((float) ($group['debt_amount'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Nom + téléphone</th>
                        <th>Téléphone</th>
                        <th>Achats</th>
                        <th>Total</th>
                        <th>Payé</th>
                        <th>Dette</th>
                        <th>Profil</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($group['clients'] ?? []) === []): ?>
                    <tr>
                        <td colspan="9" class="muted" style="text-align:center;">Aucun client pour ce filtre.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach (($group['clients'] ?? []) as $client): ?>
                    <?php
                        $clientQuery = array_filter([
                            'period_id' => is_array($selectedPeriod) ? (int) ($selectedPeriod['id'] ?? 0) : 0,
                            'group_by' => $groupBy,
                            'segment' => $segment,
                            'client_name' => (string) ($client['name'] ?? ''),
                            'client_phone' => (string) ($client['phone'] ?? ''),
                        ], static fn($value) => $value !== '' && $value !== 0 && $value !== null);
                        $clientViewUrl = '/suivi-clients' . ($clientQuery !== [] ? '?' . http_build_query($clientQuery) : '');
                    ?>
                    <tr class="client-row">
                        <td class="client-name"><?= htmlspecialchars((string) ($client['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <?php
                            $clientPhone = trim((string) ($client['phone'] ?? ''));
                            $clientName = trim((string) ($client['name'] ?? ''));
                            if ($clientPhone !== '' && $clientName !== '') {
                                $clientLabel = $clientPhone . ' - ' . $clientName;
                            } elseif ($clientPhone !== '') {
                                $clientLabel = $clientPhone;
                            } else {
                                $clientLabel = $clientName;
                            }
                        ?>
                        <td><?= htmlspecialchars($clientLabel !== '' ? $clientLabel : '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($client['phone'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int) ($client['invoice_count'] ?? 0) ?></td>
                        <td class="amount"><?= htmlspecialchars($formatMoney((float) ($client['total'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="amount"><?= htmlspecialchars($formatMoney((float) ($client['paid'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="amount debt"><?= htmlspecialchars($formatMoney((float) ($client['debt'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if (!empty($client['is_regular'])): ?>
                            <span class="pill pill-regular"><i class="fa-solid fa-star"></i> Régulier</span>
                            <?php endif; ?>
                            <?php if (!empty($client['has_debt'])): ?>
                            <span class="pill pill-debt"><i class="fa-solid fa-exclamation-triangle"></i> Dette</span>
                            <?php endif; ?>
                            <?php if (!empty($client['is_known'])): ?>
                            <span class="pill pill-known"><i class="fa-solid fa-check"></i> Connu</span>
                            <?php endif; ?>
                            <?php if (!empty($client['is_anonymous'])): ?>
                            <span class="pill pill-anonymous"><i class="fa-solid fa-user-secret"></i> Anonyme</span>
                            <?php endif; ?>
                        </td>
                        <td><a class="btn btn-soft btn-xs" href="<?= htmlspecialchars($clientViewUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-eye"></i> Voir compte</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Client Ledger - Full width -->
    <?php if (is_array($selectedClientLedger) && is_array($selectedClientLedger['client'] ?? null)): ?>
    <?php
        $ledgerClient = $selectedClientLedger['client'];
        $ledgerSummary = $selectedClientLedger['summary'] ?? [];
        $ledgerRows = $selectedClientLedger['rows'] ?? [];
        $exportQuery = array_filter([
            'period_id' => is_array($selectedPeriod) ? (int) ($selectedPeriod['id'] ?? 0) : 0,
            'client_name' => (string) ($ledgerClient['name'] ?? $selectedClientName),
            'client_phone' => (string) ($ledgerClient['phone'] ?? $selectedClientPhone),
        ], static fn($value) => $value !== '' && $value !== 0 && $value !== null);
        $exportUrl = '/suivi-clients/export' . ($exportQuery !== [] ? '?' . http_build_query($exportQuery) : '');
    ?>
    <div class="client-ledger animate-slide-up">
        <div class="client-ledger-head">
            <div>
                <h3><i class="fa-solid fa-user"></i> Compte client</h3>
                <div class="text-secondary">
                    <?= htmlspecialchars((string) ($ledgerClient['name'] ?? 'Client'), ENT_QUOTES, 'UTF-8') ?>
                    <?php if (trim((string) ($ledgerClient['phone'] ?? '')) !== ''): ?>
                    · <?= htmlspecialchars((string) ($ledgerClient['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </div>
            </div>
            <a class="btn btn-soft" href="<?= htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8') ?>" data-no-async="true">
                <i class="fa-solid fa-download"></i> Exporter opérations
            </a>
        </div>

        <div class="stats-inline">
            <div class="stat-inline-item">
                <span class="stat-inline-label"><i class="fa-solid fa-file-invoice"></i> Factures</span>
                <span class="stat-inline-value"><?= (int) ($ledgerSummary['invoice_count'] ?? 0) ?></span>
            </div>
            <div class="stat-inline-item">
                <span class="stat-inline-label"><i class="fa-solid fa-chart-line"></i> Total facturé</span>
                <span class="stat-inline-value"><?= htmlspecialchars($formatMoney((float) ($ledgerSummary['total'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="stat-inline-item">
                <span class="stat-inline-label"><i class="fa-solid fa-check-circle"></i> Total payé</span>
                <span class="stat-inline-value"><?= htmlspecialchars($formatMoney((float) ($ledgerSummary['paid'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="stat-inline-item">
                <span class="stat-inline-label"><i class="fa-solid fa-hand-holding-dollar"></i> Total dettes</span>
                <span class="stat-inline-value"><?= htmlspecialchars($formatMoney((float) ($ledgerSummary['debt'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Facture</th>
                        <th>Date</th>
                        <th>Échéance</th>
                        <th>Total</th>
                        <th>Payé</th>
                        <th>Dette</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($ledgerRows === []): ?>
                    <tr><td colspan="7" class="muted" style="text-align:center;">Aucune opération pour ce client.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($ledgerRows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($row['invoice_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($row['invoice_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($row['due_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="amount"><?= htmlspecialchars($formatMoney((float) ($row['total'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="amount"><?= htmlspecialchars($formatMoney((float) ($row['paid_amount'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="amount debt"><?= htmlspecialchars($formatMoney((float) ($row['debt_amount'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
/* ===== ANIMATIONS ===== */
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes modalSlideIn {
    from { opacity: 0; transform: translateY(-50px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.animate-slide-down { animation: slideDown 0.4s ease-out; }
.animate-slide-up { animation: slideUp 0.4s ease-out; }
.animate-fade-in { animation: fadeIn 0.3s ease-out; }
.animate-modal { animation: modalSlideIn 0.3s ease-out; }

/* ===== PAGE CONTAINERS - FULL WIDTH ===== */
.client-tracking-page {
    width: 100%;
    padding: 24px 32px;
    margin: 0;
    box-sizing: border-box;
}

/* Remove any max-width constraints */
.client-tracking-page {
    max-width: none;
}

/* ===== HEADER ===== */
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
    align-items: center;
}

.badge {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    background: var(--accent-soft);
    color: var(--accent);
}

/* ===== FLASH MESSAGES ===== */
.flash-message {
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.flash-success {
    background: rgba(34, 197, 94, 0.12);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #22c55e;
}

.flash-error {
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

/* ===== MODAL ===== */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.modal-container {
    background: var(--bg-surface);
    border-radius: 24px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    overflow: hidden;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border-light);
}

.modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
    color: var(--text-primary);
}

.modal-header h3 i {
    margin-right: 8px;
    color: var(--accent);
}

.modal-close {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: var(--text-secondary);
    transition: all 0.2s;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.modal-close:hover {
    background: rgba(0, 0, 0, 0.1);
    color: var(--danger);
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--border-light);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    background: var(--bg-primary);
}

/* ===== FORM STYLES ===== */
.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--text-primary);
    font-size: 0.875rem;
}

.input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.input-icon {
    position: absolute;
    left: 14px;
    color: var(--text-secondary);
    font-size: 14px;
    pointer-events: none;
}

.form-input {
    width: 100%;
    padding: 12px 12px 12px 40px;
    border: 1.5px solid var(--border-light);
    border-radius: 12px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: transparent;
    color: var(--text-primary);
}

.form-input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(84, 136, 14, 0.1);
}

.form-input::placeholder {
    color: var(--text-secondary);
    opacity: 0.6;
}

.form-group.has-error .form-label {
    color: #dc2626;
}

.form-input.input-error {
    border-color: #dc2626;
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.12);
}

.field-error-message {
    display: block;
    margin-top: 6px;
    font-size: 11px;
    line-height: 1.35;
    color: #dc2626;
}

.form-hint {
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* ===== FILTERS SECTION - FULL WIDTH ===== */
.filters-section {
    margin-bottom: 32px;
    width: 100%;
}

.client-filters {
    display: grid;
    grid-template-columns: repeat(3, 1fr) auto;
    gap: 16px;
    align-items: end;
    width: 100%;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-label {
    font-size: 13px;
    font-weight: 500;
    color: var(--text-secondary);
}

.select-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.select-icon {
    position: absolute;
    left: 12px;
    color: var(--text-secondary);
    font-size: 14px;
    pointer-events: none;
    z-index: 1;
}

.filter-select {
    width: 100%;
    padding: 10px 12px 10px 36px;
    border: 1.5px solid var(--border-light);
    border-radius: 12px;
    background: transparent;
    color: var(--text-primary);
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    appearance: none;
}

.filter-select:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(84, 136, 14, 0.1);
}

.filter-select:hover {
    border-color: var(--accent);
}

.filter-actions {
    display: flex;
    gap: 8px;
    align-items: end;
}

.filter-hint {
    margin: 12px 0 0;
    color: var(--text-secondary);
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* ===== STATS GRID - FULL WIDTH ===== */
.stats-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0;
    margin-bottom: 32px;
    padding: 20px 0;
    border-bottom: 1px solid var(--border-light);
    width: 100%;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 8px 24px;
    border-right: 1px solid var(--border-light);
    transition: all 0.3s ease;
    flex: 1;
    min-width: 0;
}

.stat-item:first-child {
    padding-left: 0;
}

.stat-item:last-child {
    border-right: none;
    padding-right: 0;
}

.stat-icon {
    font-size: 32px;
    color: var(--accent);
    flex-shrink: 0;
}

.stat-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.stat-label {
    font-size: 12px;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.stat-value {
    font-size: 24px;
    font-weight: 700;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ===== BUTTONS ===== */
.btn {
    padding: 10px 18px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: var(--accent);
    color: white;
}

.btn-primary:hover {
    background: #436c0b;
    transform: translateY(-1px);
}

.btn-outline {
    background: transparent;
    border: 1.5px solid var(--border-light);
    color: var(--text-primary);
}

.btn-outline:hover {
    border-color: var(--accent);
    color: var(--accent);
}

.btn-soft {
    background: var(--accent-soft);
    color: var(--accent);
}

.btn-soft:hover {
    background: rgba(84, 136, 14, 0.2);
}

.btn-add {
    background: var(--accent);
    color: white;
}

.btn-add:hover {
    background: #436c0b;
}

.btn-xs {
    padding: 4px 10px;
    font-size: 12px;
}

/* ===== CLIENT GROUP - FULL WIDTH ===== */
.client-group {
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border-light);
    width: 100%;
}

.client-group:last-child {
    border-bottom: none;
}

.client-group-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.client-group-header h3 {
    margin: 0 0 4px 0;
    font-size: 1.1rem;
    color: var(--text-primary);
}

.client-group-header p {
    margin: 0;
    color: var(--text-secondary);
    font-size: 12px;
}

.client-group-metrics {
    display: flex;
    gap: 20px;
    font-size: 13px;
    color: var(--text-secondary);
    flex-wrap: wrap;
}

.client-group-metrics span {
    display: flex;
    align-items: center;
    gap: 6px;
}

/* ===== CLIENT LEDGER - FULL WIDTH ===== */
.client-ledger {
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border-light);
    width: 100%;
}

.client-ledger-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.client-ledger-head h3 {
    margin: 0 0 6px 0;
    font-size: 1.1rem;
    color: var(--text-primary);
}

.client-ledger-head h3 i {
    color: var(--accent);
    margin-right: 6px;
}

.stats-inline {
    display: flex;
    flex-wrap: wrap;
    gap: 32px;
    margin-bottom: 24px;
    padding: 12px 0;
}

.stat-inline-item {
    display: flex;
    align-items: baseline;
    gap: 8px;
}

.stat-inline-label {
    font-size: 13px;
    color: var(--text-secondary);
}

.stat-inline-label i {
    margin-right: 4px;
}

.stat-inline-value {
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary);
}

/* ===== TABLE STYLES ===== */
.table-responsive {
    overflow-x: auto;
    width: 100%;
}

.table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

.table th,
.table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-light);
}

.table th {
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 13px;
}

.table td {
    color: var(--text-primary);
    font-size: 14px;
}

.client-row {
    transition: background 0.2s ease;
}

.client-row:hover {
    background: var(--accent-soft);
}

.amount {
    font-weight: 500;
}

.amount.debt {
    color: var(--danger);
}

/* ===== PILLS ===== */
.pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    margin: 2px;
}

.pill-regular { background: rgba(14, 165, 233, 0.15); color: #0369a1; }
.pill-debt { background: rgba(239, 68, 68, 0.15); color: #b91c1c; }
.pill-known { background: rgba(34, 197, 94, 0.15); color: #166534; }
.pill-anonymous { background: rgba(148, 163, 184, 0.2); color: #475569; }

/* ===== EMPTY STATE ===== */
.empty-state {
    text-align: center;
    padding: 48px 20px;
    color: var(--text-secondary);
}

.empty-icon {
    font-size: 48px;
    color: var(--text-secondary);
    opacity: 0.5;
    margin-bottom: 12px;
}

.text-secondary {
    color: var(--text-secondary);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1200px) {
    .stats-grid {
        flex-wrap: wrap;
    }
    
    .stat-item {
        flex: 1 1 auto;
        min-width: 180px;
        border-right: none;
        border-bottom: 1px solid var(--border-light);
        padding: 12px 0;
    }
    
    .stat-item:first-child {
        padding-left: 0;
    }
    
    .stat-item:last-child {
        border-bottom: none;
        padding-right: 0;
    }
}

@media (max-width: 900px) {
    .client-tracking-page {
        padding: 16px 20px;
    }
    
    .client-filters {
        grid-template-columns: 1fr;
    }
    
    .filter-actions {
        grid-column: 1 / -1;
    }
    
    .client-group-header {
        flex-direction: column;
    }
    
    .stats-inline {
        flex-direction: column;
        gap: 12px;
    }
    
    .client-group-metrics {
        flex-direction: column;
        gap: 8px;
    }
}

@media (max-width: 640px) {
    .client-tracking-page {
        padding: 12px 16px;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .stat-item {
        min-width: 100%;
    }
}
</style>

<script>
(() => {
    const modalOverlay = document.getElementById('client-modal-overlay');
    const toggleBtn = document.getElementById('client-create-toggle');
    const closeBtns = [document.getElementById('modal-close-btn'), document.getElementById('modal-cancel-btn')];
    const form = document.getElementById('client-create-form');
    const shouldOpen = <?= $flashErrorMessage !== '' ? 'true' : 'false' ?>;

    const resolveErrorContainer = (field) => {
        if (!field) return null;
        return field.closest('.form-group') || field.parentElement;
    };

    const clearFieldError = (field) => {
        if (!field) return;
        field.classList.remove('input-error');
        const container = resolveErrorContainer(field);
        if (!container) return;
        container.classList.remove('has-error');
        const message = container.querySelector('.field-error-message');
        if (message) {
            message.remove();
        }
    };

    const setFieldError = (field, message) => {
        if (!field) return;
        field.classList.add('input-error');
        const container = resolveErrorContainer(field);
        if (!container) return;
        container.classList.add('has-error');
        let messageNode = container.querySelector('.field-error-message');
        if (!messageNode) {
            messageNode = document.createElement('small');
            messageNode.className = 'field-error-message';
            container.appendChild(messageNode);
        }
        messageNode.textContent = message;
    };

    const focusField = (field) => {
        if (!field || typeof field.focus !== 'function') return;
        field.focus();
        if (typeof field.scrollIntoView === 'function') {
            field.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    };
    
    const openModal = () => {
        if (!modalOverlay) return;
        modalOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    };
    
    const closeModal = () => {
        if (!modalOverlay) return;
        modalOverlay.classList.remove('active');
        document.body.style.overflow = '';
        if (form) {
            form.reset();
            form.querySelectorAll('.form-input').forEach((field) => clearFieldError(field));
        }
    };
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', openModal);
    }
    
    if (closeBtns) {
        closeBtns.forEach(btn => {
            if (btn) btn.addEventListener('click', closeModal);
        });
    }
    
    if (modalOverlay) {
        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) closeModal();
        });
        
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modalOverlay.classList.contains('active')) {
                closeModal();
            }
        });
    }
    
    if (shouldOpen) {
        openModal();
    }
    
    if (form) {
        const nameInput = form.querySelector('input[name="name"]');
        const phoneInput = form.querySelector('input[name="phone"]');
        const emailInput = form.querySelector('input[name="email"]');

        const validateNamePhone = () => {
            const name = String(nameInput?.value || '').trim();
            const phone = String(phoneInput?.value || '').trim();

            clearFieldError(nameInput);
            clearFieldError(phoneInput);

            if (name !== '' || phone !== '') {
                return true;
            }

            setFieldError(nameInput, 'Renseignez au moins un nom ou un telephone.');
            setFieldError(phoneInput, 'Renseignez au moins un nom ou un telephone.');
            return false;
        };

        const validateEmail = () => {
            if (!emailInput) {
                return true;
            }
            const email = String(emailInput.value || '').trim();
            if (email === '') {
                clearFieldError(emailInput);
                return true;
            }
            if (!emailInput.checkValidity()) {
                setFieldError(emailInput, 'Adresse email invalide.');
                return false;
            }
            clearFieldError(emailInput);
            return true;
        };

        [nameInput, phoneInput].forEach((field) => {
            if (!field) {
                return;
            }
            field.addEventListener('input', validateNamePhone);
            field.addEventListener('change', validateNamePhone);
        });

        if (emailInput) {
            emailInput.addEventListener('input', validateEmail);
            emailInput.addEventListener('change', validateEmail);
        }

        form.addEventListener('submit', function(e) {
            const isNamePhoneValid = validateNamePhone();
            const isEmailValid = validateEmail();

            if (!isNamePhoneValid || !isEmailValid) {
                e.preventDefault();
                focusField(form.querySelector('.input-error'));
            }
        });
    }
})();
</script>
