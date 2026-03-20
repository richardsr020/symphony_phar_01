<?php
$periods = $periods ?? [];
$selectedPeriod = $selectedPeriod ?? null;
$fromDate = $fromDate ?? '';
$toDate = $toDate ?? '';
$regularity = $regularity ?? 'all';
$regularThreshold = $regularThreshold ?? 2;
$tracking = $tracking ?? ['suppliers' => [], 'summary' => []];

$formatMoney = static function ($value): string {
    return '$' . number_format((float) $value, 2);
};

$periodLabel = '-';
if (is_array($selectedPeriod)) {
    $periodLabel = sprintf(
        '%s (%s au %s)',
        (string) ($selectedPeriod['label'] ?? 'Exercice'),
        (string) ($selectedPeriod['start_date'] ?? '-'),
        (string) ($selectedPeriod['end_date'] ?? '-')
    );
}

$regularityLabels = [
    'all' => 'Tous',
    'regular' => 'Reguliers',
    'occasional' => 'Occasionnels',
];

$summary = $tracking['summary'] ?? [
    'suppliers' => 0,
    'regular' => 0,
    'lots' => 0,
    'total_qty' => 0,
    'remaining_qty' => 0,
    'total_value' => 0,
];

$exportQuery = array_filter([
    'period_id' => is_array($selectedPeriod) ? (int) ($selectedPeriod['id'] ?? 0) : 0,
    'from_date' => (string) $fromDate,
    'to_date' => (string) $toDate,
    'regularity' => (string) $regularity,
], static fn($value) => $value !== '' && $value !== 0 && $value !== null);
$exportUrl = '/fournisseurs/export' . ($exportQuery !== [] ? '?' . http_build_query($exportQuery) : '');
?>

<div class="supplier-page">
    <div class="page-header card">
        <div>
            <h1 class="page-title">Fournisseurs</h1>
            <p class="page-subtitle">Suivez les fournisseurs et leurs lots sur la periode.</p>
        </div>
        <div class="header-actions">
            <a class="btn btn-soft" href="<?= htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8') ?>" data-no-async="true">
                <i class="fa-solid fa-file-excel"></i> Exporter Excel
            </a>
            <span class="badge badge-info"><?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>

    <div class="card" style="margin-bottom: 20px;">
        <form method="GET" action="/fournisseurs" class="supplier-filters">
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
                <span>Intervalle sur la periode</span>
                <div class="period-custom-range">
                    <input type="date" class="filter-input" name="from_date" value="<?= htmlspecialchars((string) $fromDate, ENT_QUOTES, 'UTF-8') ?>">
                    <span class="period-separator">→</span>
                    <input type="date" class="filter-input" name="to_date" value="<?= htmlspecialchars((string) $toDate, ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </label>

            <label class="filter-group">
                <span>Regularite</span>
                <select class="filter-select" name="regularity">
                    <?php foreach ($regularityLabels as $value => $label): ?>
                    <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>" <?= $regularity === $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div style="display:flex;gap:8px;align-items:end;">
                <button type="submit" class="btn btn-soft">Appliquer</button>
                <button type="button" class="btn" onclick="window.location.href='/fournisseurs'">Reinitialiser</button>
            </div>
        </form>
        <p class="filter-hint">Fournisseur regulier = au moins <?= (int) $regularThreshold ?> lots sur la periode selectionnee.</p>
    </div>

    <div class="stats-row supplier-summary">
        <div class="stat-card">
            <div class="stat-label">Fournisseurs suivis</div>
            <div class="stat-value"><?= (int) ($summary['suppliers'] ?? 0) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Fournisseurs reguliers</div>
            <div class="stat-value"><?= (int) ($summary['regular'] ?? 0) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Lots fournis</div>
            <div class="stat-value"><?= (int) ($summary['lots'] ?? 0) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Quantite totale</div>
            <div class="stat-value"><?= htmlspecialchars(number_format((float) ($summary['total_qty'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Valeur achat</div>
            <div class="stat-value"><?= htmlspecialchars($formatMoney((float) ($summary['total_value'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>

    <?php if (($tracking['suppliers'] ?? []) === []): ?>
    <div class="card empty-state">
        Aucun fournisseur pour ce filtre.
    </div>
    <?php endif; ?>

    <?php if (($tracking['suppliers'] ?? []) !== []): ?>
    <div class="card supplier-list-card">
        <table class="table">
            <thead>
                <tr>
                    <th>Fournisseur</th>
                    <th>Lots</th>
                    <th>Quantite</th>
                    <th>Restant</th>
                    <th>Valeur achat</th>
                    <th>Produits</th>
                    <th>Dernier lot</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (($tracking['suppliers'] ?? []) as $supplier): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($supplier['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int) ($supplier['lot_count'] ?? 0) ?></td>
                    <td><?= htmlspecialchars(number_format((float) ($supplier['total_qty'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(number_format((float) ($supplier['remaining_qty'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($formatMoney((float) ($supplier['total_value'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int) ($supplier['product_count'] ?? 0) ?></td>
                    <td><?= htmlspecialchars((string) ($supplier['last_supply'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <button type="button" class="btn-icon js-supplier-view" title="Voir" data-supplier-key="<?= htmlspecialchars((string) ($supplier['key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="supplier-modal-overlay" id="supplier-modal" aria-hidden="true">
    <div class="supplier-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="supplier-modal-title">
        <div class="supplier-modal-head">
            <h3 id="supplier-modal-title" style="margin:0;">Details fournisseur</h3>
            <button type="button" class="btn-icon" id="supplier-modal-close" aria-label="Fermer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="supplier-modal-body" id="supplier-modal-body"></div>
    </div>
</div>

<style>
.supplier-page {
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
    align-items: center;
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

.supplier-filters {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-select,
.filter-input {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    background: var(--bg-surface);
    color: var(--text-primary);
    transition: border-color 0.2s, box-shadow 0.2s;
}

.filter-select:focus,
.filter-input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 18%, transparent);
}

.period-custom-range {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 8px;
    align-items: center;
}

.period-separator {
    color: var(--text-secondary);
    font-size: 13px;
}

.filter-hint {
    margin: 8px 0 0;
    color: var(--text-secondary);
    font-size: 12px;
}

.supplier-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
}

.supplier-list-card {
    padding: 18px;
}

.empty-state {
    padding: 20px;
    text-align: center;
    color: var(--text-secondary);
}

.btn-icon {
    width: 34px;
    height: 34px;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--text-secondary);
    background: var(--bg-surface);
    text-decoration: none;
    cursor: pointer;
}

.btn-icon:hover {
    background: var(--accent-soft);
    color: var(--accent);
}

.supplier-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
    z-index: 1100;
}

.supplier-modal-overlay.open {
    display: flex;
}

.supplier-modal-dialog {
    width: min(860px, 95vw);
    max-height: 90vh;
    background: var(--bg-surface);
    border-radius: 18px;
    border: 1px solid var(--border-light);
    box-shadow: 0 24px 50px rgba(15, 23, 42, 0.2);
    display: flex;
    flex-direction: column;
}

.supplier-modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-light);
}

.supplier-modal-body {
    padding: 16px 20px 20px;
    overflow: auto;
}

.supplier-modal-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
}

.supplier-modal-kpi {
    background: var(--bg-surface);
    border-radius: 12px;
    padding: 12px;
    border: 1px solid var(--border-light);
}

.supplier-modal-kpi .label {
    font-size: 12px;
    color: var(--text-secondary);
    margin-bottom: 6px;
}

.supplier-modal-kpi .value {
    font-weight: 600;
    color: var(--text-primary);
}

.supplier-lots-section {
    margin-top: 16px;
}

.supplier-lots-section h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
}

.lot-status-legend {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    font-size: 12px;
    color: var(--text-secondary);
    margin-bottom: 10px;
}

.status-dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    display: inline-block;
    margin-right: 6px;
}

.lot-status-row {
    transition: background 0.2s ease;
}

.lot-status-row td:first-child {
    border-left: 3px solid transparent;
}

.lot-status-active td:first-child,
.status-active {
    border-color: #16a34a;
}

.status-dot.status-active {
    background: #16a34a;
}

.lot-status-active {
    background: rgba(34, 197, 94, 0.12);
}

.lot-status-declassed td:first-child,
.status-declassed {
    border-color: #f59e0b;
}

.status-dot.status-declassed {
    background: #f59e0b;
}

.lot-status-declassed {
    background: rgba(250, 204, 21, 0.18);
}

.lot-status-expired td:first-child,
.status-expired {
    border-color: #f97316;
}

.status-dot.status-expired {
    background: #f97316;
}

.lot-status-expired {
    background: rgba(251, 146, 60, 0.18);
}

.lot-status-deleted td:first-child,
.status-deleted {
    border-color: #ef4444;
}

.status-dot.status-deleted {
    background: #ef4444;
}

.lot-status-deleted {
    background: rgba(239, 68, 68, 0.12);
}

.status-pill {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    background: rgba(15, 23, 42, 0.06);
    color: var(--text-primary);
}

.status-pill.status-active {
    color: #166534;
    background: rgba(34, 197, 94, 0.18);
}

.status-pill.status-declassed {
    color: #92400e;
    background: rgba(250, 204, 21, 0.22);
}

.status-pill.status-expired {
    color: #9a3412;
    background: rgba(251, 146, 60, 0.22);
}

.status-pill.status-deleted {
    color: #991b1b;
    background: rgba(239, 68, 68, 0.18);
}

@media (max-width: 900px) {
    .supplier-filters {
        grid-template-columns: 1fr;
    }

    .period-custom-range {
        grid-template-columns: 1fr;
    }

    .supplier-modal-dialog {
        width: 100%;
    }
}
</style>

<script>
(() => {
    const supplierData = <?= json_encode($tracking['suppliers'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const supplierMap = new Map(supplierData.map((item) => [String(item.key || ''), item]));
    const modal = document.getElementById('supplier-modal');
    const modalClose = document.getElementById('supplier-modal-close');
    const modalBody = document.getElementById('supplier-modal-body');
    const modalTitle = document.getElementById('supplier-modal-title');

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const parseNumber = (value) => {
        const n = parseFloat(String(value).replace(',', '.'));
        return Number.isFinite(n) ? n : 0;
    };

    const formatNumber = (value, maxDecimals = 2) => {
        return new Intl.NumberFormat('fr-FR', {
            minimumFractionDigits: 0,
            maximumFractionDigits: maxDecimals,
        }).format(parseNumber(value));
    };

    const formatMoney = (value) => `$${formatNumber(value, 2)}`;

    const openModal = (supplierKey) => {
        if (!modal || !modalBody) {
            return;
        }
        const supplier = supplierMap.get(String(supplierKey || ''));
        if (!supplier) {
            return;
        }

        const lots = Array.isArray(supplier.lots_preview) ? supplier.lots_preview : [];
        const isExpired = (raw) => {
            const value = String(raw || '').trim();
            if (value === '') {
                return false;
            }
            const date = new Date(`${value}T00:00:00`);
            if (Number.isNaN(date.getTime())) {
                return false;
            }
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return date < today;
        };

        const getLotStatus = (lot) => {
            if (Number(lot.is_declassified || 0) === 1) {
                return 'declassed';
            }
            const remaining = parseNumber(lot.quantity_remaining_base || 0);
            if (remaining <= 0 || String(lot.exhausted_at || '').trim() !== '') {
                return 'deleted';
            }
            if (isExpired(lot.expiration_date)) {
                return 'expired';
            }
            return 'active';
        };

        const statusLabels = {
            active: 'Actif',
            declassed: 'Declasse',
            expired: 'Expire',
            deleted: 'Supprime',
        };

        const lotsRows = lots.length > 0
            ? lots.map((lot) => {
                const status = getLotStatus(lot);
                const statusLabel = statusLabels[status] || 'Actif';
                const lotValue = parseNumber(lot.quantity_initial_base || 0) * parseNumber(lot.unit_cost_base || 0);
                return `
                    <tr class="lot-status-row lot-status-${status}">
                        <td>${escapeHtml(lot.product_name || '-')}</td>
                        <td>${escapeHtml(lot.lot_code || '-')}</td>
                        <td>${formatNumber(lot.quantity_initial_base || 0, 2)}</td>
                        <td>${formatNumber(lot.quantity_remaining_base || 0, 2)}</td>
                        <td>${formatMoney(lot.unit_cost_base || 0)}</td>
                        <td>${formatMoney(lotValue)}</td>
                        <td>${escapeHtml(String(lot.expiration_date || '').trim() || '-')}</td>
                        <td>${escapeHtml(String(lot.opened_at || '').trim() || '-')}</td>
                        <td><span class="status-pill status-${status}">${statusLabel}</span></td>
                    </tr>
                `;
            }).join('')
            : '<tr><td colspan="9" class="muted" style="text-align:center;">Aucun lot recent.</td></tr>';

        if (modalTitle) {
            modalTitle.textContent = `Fournisseur - ${supplier.name || ''}`;
        }

        modalBody.innerHTML = `
            <div class="supplier-modal-grid">
                <div class="supplier-modal-kpi"><div class="label">Lots fournis</div><div class="value">${supplier.lot_count || 0}</div></div>
                <div class="supplier-modal-kpi"><div class="label">Quantite totale</div><div class="value">${formatNumber(supplier.total_qty || 0, 2)}</div></div>
                <div class="supplier-modal-kpi"><div class="label">Quantite restante</div><div class="value">${formatNumber(supplier.remaining_qty || 0, 2)}</div></div>
                <div class="supplier-modal-kpi"><div class="label">Valeur achat</div><div class="value">${formatMoney(supplier.total_value || 0)}</div></div>
                <div class="supplier-modal-kpi"><div class="label">Produits distincts</div><div class="value">${supplier.product_count || 0}</div></div>
                <div class="supplier-modal-kpi"><div class="label">Premiere livraison</div><div class="value">${escapeHtml(String(supplier.first_supply || '-'))}</div></div>
                <div class="supplier-modal-kpi"><div class="label">Derniere livraison</div><div class="value">${escapeHtml(String(supplier.last_supply || '-'))}</div></div>
            </div>
            <div class="supplier-lots-section">
                <h4>Derniers lots</h4>
                <div class="lot-status-legend">
                    <span><span class="status-dot status-active"></span> Actif</span>
                    <span><span class="status-dot status-declassed"></span> Declasse</span>
                    <span><span class="status-dot status-expired"></span> Expire</span>
                    <span><span class="status-dot status-deleted"></span> Supprime</span>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>N° lot</th>
                            <th>Quantite</th>
                            <th>Restant</th>
                            <th>Prix achat</th>
                            <th>Valeur</th>
                            <th>Expiration</th>
                            <th>Entree</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lotsRows}
                    </tbody>
                </table>
            </div>
        `;

        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
    };

    const closeModal = () => {
        if (!modal) {
            return;
        }
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    };

    document.querySelectorAll('.js-supplier-view').forEach((button) => {
        button.addEventListener('click', () => {
            openModal(button.dataset.supplierKey || '');
        });
    });

    if (modalClose) {
        modalClose.addEventListener('click', closeModal);
    }
    if (modal) {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });
    }
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal && modal.classList.contains('open')) {
            closeModal();
        }
    });
})();
</script>
