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
?>

<div class="client-tracking-page">
    <div class="page-header card">
        <div>
            <h1 class="page-title">Suivi clients</h1>
            <p class="page-subtitle">Suivez les mouvements clients par exercice et periode.</p>
        </div>
        <div class="header-actions">
            <button type="button" class="btn btn-add" id="client-create-toggle">+ Nouveau client</button>
            <span class="badge badge-info"><?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>

    <?php if ($flashSuccessMessage !== ''): ?>
    <div class="flash-message flash-success" style="margin-bottom: 14px;">
        <?= htmlspecialchars($flashSuccessMessage, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>
    <?php if ($flashErrorMessage !== ''): ?>
    <div class="flash-message flash-error" style="margin-bottom: 14px;">
        <?= htmlspecialchars($flashErrorMessage, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <div class="card client-create-card" id="client-create-card" hidden>
        <h3 style="margin:0 0 12px 0;">Nouveau client</h3>
        <form method="POST" action="/clients/store" data-async="true" data-async-success="Client créé.">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <div class="client-create-grid">
                <label class="form-field">
                    <span>Nom client</span>
                    <input type="text" name="name" placeholder="Ex: Pharmacie Centrale">
                </label>
                <label class="form-field">
                    <span>Téléphone</span>
                    <input type="text" name="phone" placeholder="Ex: 0812345678">
                </label>
                <label class="form-field">
                    <span>Email (optionnel)</span>
                    <input type="email" name="email" placeholder="contact@client.com">
                </label>
                <label class="form-field form-field-full">
                    <span>Adresse (optionnel)</span>
                    <input type="text" name="address" placeholder="Adresse du client">
                </label>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:10px;">
                <button type="button" class="btn btn-soft" id="client-create-cancel">Annuler</button>
                <button type="submit" class="btn btn-add">Créer</button>
            </div>
            <p class="text-secondary" style="font-size:12px;margin:8px 0 0;">Renseignez au moins un nom ou un téléphone.</p>
        </form>
    </div>

    <div class="card" style="margin-bottom: 20px;">
        <form method="GET" action="/suivi-clients" class="client-filters">
            <label class="filter-group">
                <span>Exercice comptable</span>
                <select class="filter-select" name="period_id">
                    <?php foreach ($periods as $period): ?>
                    <?php $periodId = (int) ($period['id'] ?? 0); ?>
                    <option value="<?= $periodId ?>" <?= (is_array($selectedPeriod) && (int) ($selectedPeriod['id'] ?? 0) === $periodId) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) ($period['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="filter-group">
                <span>Regroupement</span>
                <select class="filter-select" name="group_by">
                    <?php foreach ($groupByLabels as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $groupBy === $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="filter-group">
                <span>Segment clients</span>
                <select class="filter-select" name="segment">
                    <?php foreach ($segmentLabels as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $segment === $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div style="display:flex;gap:8px;align-items:end;">
                <button type="submit" class="btn btn-soft">Appliquer</button>
                <button type="button" class="btn" onclick="window.location.href='/suivi-clients'">Reinitialiser</button>
            </div>
        </form>
        <p class="filter-hint">Clients reguliers = au moins <?= (int) $regularThreshold ?> achats sur la periode selectionnee.</p>
    </div>

    <div class="stats-row client-summary">
        <div class="stat-card">
            <div class="stat-label">Clients suivis</div>
            <div class="stat-value"><?= (int) ($summary['clients'] ?? 0) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Clients reguliers</div>
            <div class="stat-value"><?= (int) ($summary['regular'] ?? 0) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Clients en dette</div>
            <div class="stat-value"><?= (int) ($summary['debt'] ?? 0) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Montant facture</div>
            <div class="stat-value"><?= htmlspecialchars($formatMoney((float) ($summary['total'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Reste a encaisser</div>
            <div class="stat-value"><?= htmlspecialchars($formatMoney((float) ($summary['debt_amount'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>

    <?php if (($tracking['groups'] ?? []) === []): ?>
    <div class="card empty-state">
        Aucun mouvement client pour la periode selectionnee.
    </div>
    <?php endif; ?>

    <?php foreach (($tracking['groups'] ?? []) as $group): ?>
    <div class="card client-group-card">
        <div class="client-group-header">
            <div>
                <h3><?= htmlspecialchars((string) ($group['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                <p><?= htmlspecialchars((string) ($group['period_start'] ?? ''), ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars((string) ($group['period_end'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="client-group-metrics">
                <span>Clients: <?= (int) ($group['client_count'] ?? 0) ?></span>
                <span>Factures: <?= (int) ($group['invoice_count'] ?? 0) ?></span>
                <span>Total: <?= htmlspecialchars($formatMoney((float) ($group['total_amount'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></span>
                <span>Dette: <?= htmlspecialchars($formatMoney((float) ($group['debt_amount'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Nom + téléphone</th>
                    <th>Telephone</th>
                    <th>Achats</th>
                    <th>Total</th>
                    <th>Paye</th>
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
                <tr>
                    <td><?= htmlspecialchars((string) ($client['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
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
                    <td><?= htmlspecialchars($formatMoney((float) ($client['total'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($formatMoney((float) ($client['paid'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($formatMoney((float) ($client['debt'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if (!empty($client['is_regular'])): ?>
                        <span class="pill pill-regular">Regulier</span>
                        <?php endif; ?>
                        <?php if (!empty($client['has_debt'])): ?>
                        <span class="pill pill-debt">Dette</span>
                        <?php endif; ?>
                        <?php if (!empty($client['is_known'])): ?>
                        <span class="pill pill-known">Connu</span>
                        <?php endif; ?>
                        <?php if (!empty($client['is_anonymous'])): ?>
                        <span class="pill pill-anonymous">Anonyme</span>
                        <?php endif; ?>
                    </td>
                    <td><a class="btn btn-soft btn-xs" href="<?= htmlspecialchars($clientViewUrl, ENT_QUOTES, 'UTF-8') ?>">Voir compte</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>

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
    <div class="card client-ledger-card">
        <div class="client-ledger-head">
            <div>
                <h3 style="margin:0 0 6px 0;">Compte client</h3>
                <div class="text-secondary">
                    <?= htmlspecialchars((string) ($ledgerClient['name'] ?? 'Client'), ENT_QUOTES, 'UTF-8') ?>
                    <?php if (trim((string) ($ledgerClient['phone'] ?? '')) !== ''): ?>
                    · <?= htmlspecialchars((string) ($ledgerClient['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </div>
            </div>
            <a class="btn btn-soft" href="<?= htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8') ?>" data-no-async="true">Exporter operations</a>
        </div>

        <div class="stats-row client-summary">
            <div class="stat-card">
                <div class="stat-label">Factures</div>
                <div class="stat-value"><?= (int) ($ledgerSummary['invoice_count'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total facture</div>
                <div class="stat-value"><?= htmlspecialchars($formatMoney((float) ($ledgerSummary['total'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total paye</div>
                <div class="stat-value"><?= htmlspecialchars($formatMoney((float) ($ledgerSummary['paid'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total dettes</div>
                <div class="stat-value"><?= htmlspecialchars($formatMoney((float) ($ledgerSummary['debt'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Facture</th>
                    <th>Date</th>
                    <th>Echeance</th>
                    <th>Total</th>
                    <th>Paye</th>
                    <th>Dette</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($ledgerRows === []): ?>
                <tr><td colspan="7" class="muted" style="text-align:center;">Aucune operation pour ce client.</td></tr>
                <?php endif; ?>
                <?php foreach ($ledgerRows as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($row['invoice_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($row['invoice_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($row['due_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($formatMoney((float) ($row['total'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($formatMoney((float) ($row['paid_amount'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($formatMoney((float) ($row['debt_amount'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.client-tracking-page {
    display: grid;
    gap: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.badge {
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
}

.badge-info {
    background: #1e9ebf;
    color: #fff;
}

.client-filters {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    align-items: end;
}

.filter-hint {
    margin: 8px 0 0;
    color: var(--text-secondary);
    font-size: 12px;
}

.client-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
}

.client-group-card {
    padding: 18px;
}

.client-ledger-card {
    padding: 18px;
}

.client-create-card {
    padding: 18px;
}

.client-create-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.form-field-full {
    grid-column: 1 / -1;
}

.client-ledger-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 16px;
}

.client-group-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 12px;
}

.client-group-header h3 {
    margin: 0 0 4px 0;
    font-size: 1.05rem;
}

.client-group-header p {
    margin: 0;
    color: var(--text-secondary);
    font-size: 12px;
}

.client-group-metrics {
    display: grid;
    gap: 4px;
    font-size: 12px;
    color: var(--text-secondary);
    text-align: right;
}

.pill {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    margin-right: 4px;
    margin-bottom: 4px;
}

.pill-regular { background: rgba(14, 165, 233, 0.15); color: #0369a1; }
.pill-debt { background: rgba(239, 68, 68, 0.15); color: #b91c1c; }
.pill-known { background: rgba(34, 197, 94, 0.15); color: #166534; }
.pill-anonymous { background: rgba(148, 163, 184, 0.2); color: #475569; }

.empty-state {
    padding: 20px;
    text-align: center;
    color: var(--text-secondary);
}

@media (max-width: 900px) {
    .client-filters {
        grid-template-columns: 1fr;
    }

    .client-create-grid {
        grid-template-columns: 1fr;
    }

    .client-group-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .client-group-metrics {
        text-align: left;
    }
}
</style>

<script>
(() => {
    const toggleBtn = document.getElementById('client-create-toggle');
    const cancelBtn = document.getElementById('client-create-cancel');
    const card = document.getElementById('client-create-card');
    const shouldOpen = <?= $flashErrorMessage !== '' ? 'true' : 'false' ?>;
    const openCard = () => {
        if (!card) return;
        card.hidden = false;
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
    const closeCard = () => {
        if (!card) return;
        card.hidden = true;
    };
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            if (!card) return;
            if (card.hidden) {
                openCard();
            } else {
                closeCard();
            }
        });
    }
    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            closeCard();
        });
    }
    if (shouldOpen) {
        openCard();
    }
})();
</script>
