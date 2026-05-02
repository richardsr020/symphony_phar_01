<?php
$invoices = $invoices ?? [];
$stats = $stats ?? [
    'total_billed' => 0,
    'total_paid' => 0,
    'total_pending' => 0,
    'total_overdue' => 0,
];
$filters = $filters ?? [
    'status' => '',
    'q' => '',
];
$pagination = $pagination ?? [
    'total' => count($invoices),
    'page' => 1,
    'per_page' => 8,
    'total_pages' => 1,
    'sort_by' => 'invoice_date',
    'sort_dir' => 'desc',
];
$selectedInvoice = $selectedInvoice ?? null;
$flashSuccess = $flashSuccess ?? '';
$flashError = $flashError ?? '';
$csrfToken = App\Core\Security::generateCSRF();
$canManageInvoices = $canManageInvoices ?? true;

$statusLabels = [
    'draft' => 'Brouillon',
    'sent' => 'Envoyee',
    'paid' => 'Payee',
    'overdue' => 'En retard',
    'cancelled' => 'Annulee',
];

$baseQuery = [
    'status' => (string) ($filters['status'] ?? ''),
    'q' => (string) ($filters['q'] ?? ''),
];
$buildUrl = static function (array $extra) use ($baseQuery): string {
    $query = array_merge($baseQuery, $extra);
    $query = array_filter($query, static fn($value) => $value !== '' && $value !== null);
    $queryString = http_build_query($query);
    return '/invoices' . ($queryString !== '' ? '?' . $queryString : '');
};
$sortBy = (string) ($pagination['sort_by'] ?? 'invoice_date');
$sortDir = (string) ($pagination['sort_dir'] ?? 'desc');
$sortLink = static function (string $column) use ($sortBy, $sortDir, $buildUrl): string {
    $nextDir = ($sortBy === $column && $sortDir === 'asc') ? 'desc' : 'asc';
    return $buildUrl(['sort_by' => $column, 'sort_dir' => $nextDir, 'page' => 1]);
};
$sortMark = static function (string $column) use ($sortBy, $sortDir): string {
    if ($sortBy !== $column) {
        return '';
    }
    return $sortDir === 'asc' ? ' ▲' : ' ▼';
};
$exportQuery = array_filter(array_merge($baseQuery, [
    'sort_by' => $sortBy,
    'sort_dir' => $sortDir,
]), static fn($value) => $value !== '' && $value !== null);
$exportUrl = '/invoices/export' . ($exportQuery !== [] ? '?' . http_build_query($exportQuery) : '');
$formatNumber = static function ($value, int $decimals = 2): string {
    $numeric = round((float) $value, $decimals);
    $formatted = number_format($numeric, $decimals, '.', ' ');
    if ($decimals > 0) {
        $formatted = rtrim(rtrim($formatted, '0'), '.');
    }
    return $formatted;
};
$formatMoney = static function ($value) use ($formatNumber): string {
    return '$' . $formatNumber($value, 2);
};
?>

<div class="invoices-page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Ventes</h1>
            <p class="page-subtitle">Gestion des brouillons, envois et encaissements</p>
        </div>
        <div class="header-actions">
            <a class="btn btn-soft" href="<?= htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8') ?>" data-no-async="true">Exporter Excel</a>
            <?php if ($canManageInvoices): ?>
            <a class="btn btn-add tw-inline-flex tw-items-center tw-gap-2" href="/invoices/create">
                <i class="fa-solid fa-plus"></i>
                <span>Nouvelle vente</span>
            </a>
            <a class="btn btn-soft tw-inline-flex tw-items-center tw-gap-2" href="/invoices/create?doc=proforma">
                <i class="fa-regular fa-file-lines"></i>
                <span>Creer un proforma</span>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($flashSuccess !== ''): ?>
    <div class="flash-message flash-success"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($flashError !== ''): ?>
    <div class="flash-message flash-error"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="stats-row" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">
        <div class="stat-card" style="padding: 20px;">
            <div class="stat-label">Chiffre d'affaires ventes</div>
            <div class="stat-value"><?= htmlspecialchars($formatMoney((float) $stats['total_billed']), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="stat-card" style="padding: 20px;">
            <div class="stat-label">Tresorerie encaissee</div>
            <div class="stat-value"><?= htmlspecialchars($formatMoney((float) $stats['total_paid']), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="stat-card" style="padding: 20px;">
            <div class="stat-label">Reste a encaisser</div>
            <div class="stat-value"><?= htmlspecialchars($formatMoney((float) $stats['total_pending']), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="stat-card" style="padding: 20px;">
            <div class="stat-label">Ventes en retard</div>
            <div class="stat-value"><?= htmlspecialchars($formatMoney((float) $stats['total_overdue']), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 20px;">
        <form method="GET" action="/invoices" data-auto-filter="true" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;align-items:end;">
            <input type="hidden" name="sort_by" value="<?= htmlspecialchars((string) $pagination['sort_by'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="sort_dir" value="<?= htmlspecialchars((string) $pagination['sort_dir'], ENT_QUOTES, 'UTF-8') ?>">
            <label class="filter-group">
                <span>Recherche</span>
                <input type="text" class="filter-input" name="q" value="<?= htmlspecialchars((string) $filters['q'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Numero ou client">
            </label>
            <label class="filter-group">
                <span>Statut</span>
                <select class="filter-select" name="status">
                    <option value="">Tous</option>
                    <option value="draft" <?= ($filters['status'] === 'draft') ? 'selected' : '' ?>>Brouillon</option>
                    <option value="sent" <?= ($filters['status'] === 'sent') ? 'selected' : '' ?>>Envoyee</option>
                    <option value="paid" <?= ($filters['status'] === 'paid') ? 'selected' : '' ?>>Payee</option>
                    <option value="overdue" <?= ($filters['status'] === 'overdue') ? 'selected' : '' ?>>En retard</option>
                    <option value="cancelled" <?= ($filters['status'] === 'cancelled') ? 'selected' : '' ?>>Annulee</option>
                </select>
            </label>
            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn btn-soft js-auto-filter-submit">Filtrer</button>
                <button type="button" class="btn" onclick="window.location.href='/invoices'">Reinitialiser</button>
            </div>
        </form>
    </div>

    <div class="card">
        <?php if ($canManageInvoices): ?>
        <form method="POST" action="/invoices/merge" data-async="true" data-async-success="Factures fusionnees." id="invoice-merge-form" style="margin-bottom:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn-soft btn-xs" id="invoice-merge-btn">
                <i class="fa-solid fa-object-group"></i> Fusionner la selection
            </button>
            <span class="text-secondary" style="font-size:12px;">Selectionnez au moins 2 factures eligibles.</span>
        </form>
        <?php endif; ?>

        <table class="table">
            <thead>
                <tr>
                    <th style="width:36px;"><input type="checkbox" id="invoice-merge-select-all" aria-label="Selectionner toutes les factures eligibles" <?= $canManageInvoices ? '' : 'disabled' ?>></th>
                    <th><a href="<?= htmlspecialchars($sortLink('invoice_number'), ENT_QUOTES, 'UTF-8') ?>">N° Facture<?= htmlspecialchars($sortMark('invoice_number'), ENT_QUOTES, 'UTF-8') ?></a></th>
                    <th><a href="<?= htmlspecialchars($sortLink('customer_name'), ENT_QUOTES, 'UTF-8') ?>">Client<?= htmlspecialchars($sortMark('customer_name'), ENT_QUOTES, 'UTF-8') ?></a></th>
                    <th><a href="<?= htmlspecialchars($sortLink('invoice_date'), ENT_QUOTES, 'UTF-8') ?>">Date<?= htmlspecialchars($sortMark('invoice_date'), ENT_QUOTES, 'UTF-8') ?></a></th>
                    <th><a href="<?= htmlspecialchars($sortLink('due_date'), ENT_QUOTES, 'UTF-8') ?>">Echeance<?= htmlspecialchars($sortMark('due_date'), ENT_QUOTES, 'UTF-8') ?></a></th>
                    <th><a href="<?= htmlspecialchars($sortLink('total'), ENT_QUOTES, 'UTF-8') ?>">Montant<?= htmlspecialchars($sortMark('total'), ENT_QUOTES, 'UTF-8') ?></a></th>
                    <th>Paye</th>
                    <th>Reste</th>
                    <th><a href="<?= htmlspecialchars($sortLink('status'), ENT_QUOTES, 'UTF-8') ?>">Statut<?= htmlspecialchars($sortMark('status'), ENT_QUOTES, 'UTF-8') ?></a></th>
                    <th>Cree par</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($invoices === []): ?>
                <tr>
                    <td colspan="11" style="text-align: center; padding: 24px; color: var(--text-secondary);">Aucune facture trouvee.</td>
                </tr>
                <?php endif; ?>

                <?php foreach ($invoices as $invoice): ?>
                <?php
                    $invoiceId = (int) ($invoice['id'] ?? 0);
                    $status = (string) ($invoice['status'] ?? 'draft');
                    $documentType = strtolower(trim((string) ($invoice['document_type'] ?? 'invoice')));
                    $isProforma = $documentType === 'proforma';
                    $total = (float) ($invoice['total'] ?? 0);
                    $paid = (float) ($invoice['paid_amount'] ?? 0);
                    $remaining = max($total - $paid, 0);
                    $statusClass = 'status-' . strtolower($status);
                    $isDownloaded = trim((string) ($invoice['downloaded_at'] ?? '')) !== '';
                ?>
                <tr class="<?= $isDownloaded ? 'invoice-row-downloaded' : '' ?>">
                    <td>
                        <?php $canMerge = $canManageInvoices && in_array($status, ['draft', 'sent', 'overdue', 'paid'], true) && $status !== 'cancelled'; ?>
                        <input
                            type="checkbox"
                            class="invoice-merge-checkbox"
                            <?= $canManageInvoices ? 'form="invoice-merge-form"' : '' ?>
                            name="invoice_ids[]"
                            value="<?= $invoiceId ?>"
                            <?= $canMerge ? '' : 'disabled' ?>
                            aria-label="Selection facture <?= htmlspecialchars((string) ($invoice['invoice_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </td>
                    <td><strong><?= htmlspecialchars((string) $invoice['invoice_number'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <td><?= htmlspecialchars((string) $invoice['customer_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(date('d/m/Y', strtotime((string) $invoice['invoice_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(date('d/m/Y', strtotime((string) $invoice['due_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="amount"><?= htmlspecialchars($formatMoney($total), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($formatMoney($paid), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($formatMoney($remaining), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="status-badge <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($statusLabels[$status] ?? $status, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars(trim((string) ($invoice['created_by_name'] ?? '')) !== '' ? (string) $invoice['created_by_name'] : 'Utilisateur', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <div class="row-actions">
                            <a class="btn-icon" title="Apercu" href="/invoices/preview/<?= $invoiceId ?>" data-no-async="true"><i class="fa-regular fa-eye"></i></a>

                            <?php if ($canManageInvoices && $status === 'draft'): ?>
                            <a class="btn-icon" title="Modifier brouillon" href="/invoices/edit/<?= $invoiceId ?>">
                                <i class="fa-regular fa-pen-to-square"></i>
                            </a>

                            <form method="POST" action="/invoices/send/<?= $invoiceId ?>" class="inline-form" data-async="true" data-async-success="<?= $isProforma ? 'Proforma converti en facture payee.' : 'Facture envoyee.' ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="btn-icon success" title="<?= $isProforma ? 'Convertir en facture (payee)' : 'Envoyer la facture' ?>">
                                    <i class="<?= $isProforma ? 'fa-solid fa-file-invoice-dollar' : 'fa-regular fa-paper-plane' ?>"></i>
                                </button>
                            </form>
                            <?php endif; ?>

                            <?php if ($canManageInvoices && in_array($status, ['draft', 'sent', 'overdue', 'paid'], true)): ?>
                            <form method="POST" action="/invoices/cancel/<?= $invoiceId ?>" class="inline-form" data-async="true" data-async-success="Facture annulee." onsubmit="return confirm('Annuler cette facture ? Cette action la retire du chiffre d affaires et de la tresorerie.');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="btn-icon danger" title="Annuler la facture">
                                    <i class="fa-regular fa-circle-xmark"></i>
                                </button>
                            </form>
                            <?php endif; ?>

                            <?php if ($canManageInvoices && $status === 'draft' && $paid <= 0): ?>
                            <form method="POST" action="/invoices/delete/<?= $invoiceId ?>" class="inline-form" data-async="true" data-async-success="Facture supprimee." onsubmit="return confirm('Supprimer definitivement cette facture ?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="btn-icon danger" title="Supprimer la facture">
                                    <i class="fa-regular fa-trash-can"></i>
                                </button>
                            </form>
                            <?php endif; ?>

                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="pagination" style="display:flex;justify-content:space-between;align-items:center;padding:12px 0 2px 0;">
            <div class="page-info">
                Page <?= (int) $pagination['page'] ?> / <?= (int) $pagination['total_pages'] ?> - <?= (int) $pagination['total'] ?> elements
            </div>
            <div style="display:flex;gap:8px;">
                <?php if ((int) $pagination['page'] > 1): ?>
                <a class="btn btn-soft" href="<?= htmlspecialchars($buildUrl(['page' => ((int) $pagination['page']) - 1, 'sort_by' => $sortBy, 'sort_dir' => $sortDir]), ENT_QUOTES, 'UTF-8') ?>">Precedent</a>
                <?php else: ?>
                <button class="btn btn-soft" disabled>Precedent</button>
                <?php endif; ?>

                <?php if ((int) $pagination['page'] < (int) $pagination['total_pages']): ?>
                <a class="btn btn-soft" href="<?= htmlspecialchars($buildUrl(['page' => ((int) $pagination['page']) + 1, 'sort_by' => $sortBy, 'sort_dir' => $sortDir]), ENT_QUOTES, 'UTF-8') ?>">Suivant</a>
                <?php else: ?>
                <button class="btn btn-soft" disabled>Suivant</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (is_array($selectedInvoice) && $selectedInvoice !== []): ?>
    <?php
        $selectedStatus = (string) ($selectedInvoice['status'] ?? '');
        $selectedIsDraft = $selectedStatus === 'draft';
        $selectedItems = is_array($selectedInvoice['items'] ?? null) ? $selectedInvoice['items'] : [];
        $selectedCogs = 0.0;
        $selectedMargin = 0.0;
        foreach ($selectedItems as $itemRow) {
            $selectedCogs += (float) ($itemRow['cogs_amount'] ?? 0);
            $selectedMargin += (float) ($itemRow['margin_amount'] ?? 0);
        }
    ?>
    <div class="card" style="margin-top: 24px;">
        <h3 style="margin-bottom: 14px;">Detail facture <?= htmlspecialchars((string) $selectedInvoice['invoice_number'], ENT_QUOTES, 'UTF-8') ?></h3>
        <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px;">
            <p><strong>Client:</strong> <?= htmlspecialchars((string) $selectedInvoice['customer_name'], ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong>Statut:</strong> <?= htmlspecialchars($statusLabels[(string) $selectedInvoice['status']] ?? (string) $selectedInvoice['status'], ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong>Date:</strong> <?= htmlspecialchars(date('d/m/Y', strtotime((string) $selectedInvoice['invoice_date'])), ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong>Echeance:</strong> <?= htmlspecialchars(date('d/m/Y', strtotime((string) $selectedInvoice['due_date'])), ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong>Sous-total:</strong> <?= htmlspecialchars($formatMoney((float) $selectedInvoice['subtotal']), ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong>TVA:</strong> <?= htmlspecialchars($formatMoney((float) $selectedInvoice['tax_amount']), ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong>Total:</strong> <?= htmlspecialchars($formatMoney((float) $selectedInvoice['total']), ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong>Paye:</strong> <?= htmlspecialchars($formatMoney((float) ($selectedInvoice['paid_amount'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong>Cout marchandises:</strong> <?= htmlspecialchars($formatMoney($selectedCogs), ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong>Benefice brut:</strong> <?= htmlspecialchars($formatMoney($selectedMargin), ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong>NIF/RCCM:</strong> <?= htmlspecialchars((string) ($selectedInvoice['customer_tax_id'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong>Cree par:</strong> <?= htmlspecialchars(trim((string) ($selectedInvoice['created_by_name'] ?? '')) !== '' ? (string) $selectedInvoice['created_by_name'] : 'Utilisateur', ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php if (!empty($selectedInvoice['customer_address'])): ?>
        <p style="margin-top: 10px;"><strong>Adresse:</strong> <?= nl2br(htmlspecialchars((string) $selectedInvoice['customer_address'], ENT_QUOTES, 'UTF-8')) ?></p>
        <?php endif; ?>
        <?php if (!empty($selectedInvoice['notes'])): ?>
        <p style="margin-top: 10px;"><strong>Notes:</strong><br><?= nl2br(htmlspecialchars((string) $selectedInvoice['notes'], ENT_QUOTES, 'UTF-8')) ?></p>
        <?php endif; ?>

        <?php if (!empty($selectedInvoice['items']) && is_array($selectedInvoice['items'])): ?>
        <table class="table" style="margin-top: 14px;">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Quantite</th>
                    <th>Prix unit.</th>
                    <th>TVA %</th>
                    <th>COGS</th>
                    <th>Marge</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($selectedInvoice['items'] as $item): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $item['description'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($formatNumber((float) $item['quantity'], 2), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($formatMoney((float) $item['unit_price']), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($formatNumber((float) $item['tax_rate'], 2), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($formatMoney((float) ($item['cogs_amount'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($formatMoney((float) ($item['margin_amount'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($formatMoney((float) $item['total']), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if ($canManageInvoices && $selectedIsDraft): ?>
        <div style="margin-top: 16px; display:flex; gap:8px; flex-wrap:wrap;">
            <a class="btn btn-soft btn-xs" href="/invoices/edit/<?= (int) $selectedInvoice['id'] ?>">
                <i class="fa-regular fa-pen-to-square"></i> Modifier ce brouillon
            </a>
            <form method="POST" action="/invoices/cancel/<?= (int) $selectedInvoice['id'] ?>" class="inline-form" data-async="true" data-async-success="Brouillon annule." onsubmit="return confirm('Annuler ce brouillon ?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn btn-xs invoice-cancel-btn">
                    <i class="fa-regular fa-circle-xmark"></i> Annuler ce brouillon
                </button>
            </form>
            <form method="POST" action="/invoices/delete/<?= (int) $selectedInvoice['id'] ?>" class="inline-form" data-async="true" data-async-success="Brouillon supprime." onsubmit="return confirm('Supprimer definitivement ce brouillon ?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn btn-xs invoice-cancel-btn">
                    <i class="fa-regular fa-trash-can"></i> Supprimer ce brouillon
                </button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($canManageInvoices && in_array($selectedStatus, ['sent', 'overdue', 'paid'], true)): ?>
        <div style="margin-top: 16px; display:flex; gap:8px; flex-wrap:wrap;">
            <form method="POST" action="/invoices/cancel/<?= (int) $selectedInvoice['id'] ?>" class="inline-form" data-async="true" data-async-success="Facture annulee." onsubmit="return confirm('Annuler cette facture ? Cette action la retire du chiffre d affaires et de la tresorerie.');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn btn-xs invoice-cancel-btn">
                    <i class="fa-regular fa-circle-xmark"></i> Annuler cette facture
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.flash-message {
    border-radius: var(--radius-md);
    padding: 12px 14px;
    margin-bottom: 16px;
}

.invoices-page .page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.invoices-page .page-subtitle {
    margin-top: 6px;
}

.flash-success {
    background: rgba(16, 185, 129, 0.14);
    color: var(--success);
}

.flash-error {
    background: rgba(239, 68, 68, 0.14);
    color: var(--danger);
}

.status-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.status-paid {
    background: #10B98120;
    color: var(--success);
}

.status-sent {
    background: #3B82F620;
    color: var(--info);
}

.status-overdue {
    background: #EF444420;
    color: var(--danger);
}

.status-draft,
.status-cancelled {
    background: var(--border-light);
    color: var(--text-secondary);
}

.amount {
    font-weight: 600;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.filter-group span {
    font-size: 12px;
    color: var(--text-secondary);
}

.filter-select,
.filter-input {
    padding: 10px;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    background: var(--bg-surface);
    color: var(--text-primary);
}

.table th a {
    color: inherit;
    text-decoration: none;
}

.invoices-page .table tbody tr.invoice-row-downloaded > td:first-child {
    border-left: 4px solid #16a34a;
}

.row-actions {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
}

.inline-form {
    margin: 0;
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

.btn-icon.success {
    color: var(--success);
}

.btn-icon.danger {
    color: var(--danger);
}

.pay-form {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.pay-form input {
    width: 100px;
    padding: 7px 8px;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-sm);
    background: var(--bg-surface);
    color: var(--text-primary);
}

.btn-xs {
    padding: 7px 10px;
    font-size: 12px;
}

.invoice-cancel-btn {
    border: 1px solid rgba(239, 68, 68, 0.35);
    color: var(--danger);
    background: rgba(239, 68, 68, 0.08);
}

.invoice-cancel-btn:hover {
    background: rgba(239, 68, 68, 0.14);
}

@media (max-width: 1100px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}

@media (max-width: 760px) {
    .stats-row {
        grid-template-columns: 1fr !important;
    }

    .pay-form {
        width: 100%;
    }

    .pay-form input {
        flex: 1;
        width: auto;
    }
}
</style>

<script>
(() => {
    const mergeForm = document.getElementById('invoice-merge-form');
    const selectAll = document.getElementById('invoice-merge-select-all');
    const mergeBtn = document.getElementById('invoice-merge-btn');
    const checkboxes = Array.from(document.querySelectorAll('.invoice-merge-checkbox'));

    if (!mergeForm || !mergeBtn || checkboxes.length === 0) {
        return;
    }

    const syncState = () => {
        const enabledBoxes = checkboxes.filter((box) => !box.disabled);
        const selectedCount = enabledBoxes.filter((box) => box.checked).length;
        mergeBtn.disabled = selectedCount < 2;
        if (selectAll) {
            selectAll.checked = enabledBoxes.length > 0 && selectedCount === enabledBoxes.length;
            selectAll.indeterminate = selectedCount > 0 && selectedCount < enabledBoxes.length;
        }
    };

    if (selectAll) {
        selectAll.addEventListener('change', () => {
            const enabledBoxes = checkboxes.filter((box) => !box.disabled);
            enabledBoxes.forEach((box) => {
                box.checked = selectAll.checked;
            });
            syncState();
        });
    }

    checkboxes.forEach((box) => {
        box.addEventListener('change', syncState);
    });

    mergeForm.addEventListener('submit', (event) => {
        const selected = checkboxes.filter((box) => !box.disabled && box.checked).length;
        if (selected < 2) {
            event.preventDefault();
            window.alert('Selectionnez au moins 2 factures eligibles a fusionner.');
            return;
        }
        const ok = window.confirm('Fusionner les factures selectionnees en une seule facture ?');
        if (!ok) {
            event.preventDefault();
        }
    });

    syncState();
})();
</script>
