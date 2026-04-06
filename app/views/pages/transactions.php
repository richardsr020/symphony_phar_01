<?php
$transactions = $transactions ?? [];
$summary = $summary ?? [
    'total_debit' => 0,
    'total_credit' => 0,
    'balance' => 0,
    'total_count' => 0,
];
$filters = $filters ?? [
    'status' => '',
    'type' => '',
    'q' => '',
    'from_date' => '',
    'to_date' => '',
];
$accounts = $accounts ?? [];
$showCreateForm = $showCreateForm ?? false;
$editingTransaction = $editingTransaction ?? null;
$pagination = $pagination ?? [
    'total' => count($transactions),
    'page' => 1,
    'per_page' => 20,
    'total_pages' => 1,
    'sort_by' => 'transaction_date',
    'sort_dir' => 'desc',
];
$flashSuccess = $flashSuccess ?? '';
$flashError = $flashError ?? '';
$nextTransactionReference = $nextTransactionReference ?? ('TX-' . date('Ym') . '-0001');
$csrfToken = App\Core\Security::generateCSRF();

$statusLabels = [
    'draft' => 'Brouillon',
    'posted' => 'Validee',
    'void' => 'Annulee',
];

$typeLabels = [
    'income' => 'Revenu occasionnel',
    'expense' => 'Depense',
    'transfer' => 'Transfert',
    'journal' => 'Journal',
    'billing' => 'Facturation',
    'debt_payment' => 'Remboursement de dettes',
];
$expenseSubcategoryLabels = [
    'fiscal' => 'Fiscal',
    'salarial' => 'Salarial',
    'achat_stock' => 'Achat de stock',
    'loyer' => 'Loyer',
    'electricite' => 'Electricite',
    'eau' => 'Eau',
    'internet' => 'Internet',
    'other' => 'Autre',
];
$expenseFiscalSubcategoryLabels = [
    'impot' => 'Impot',
    'tax' => 'Tax',
    'versement_tva' => 'Versement TVA',
];

$baseQuery = [
    'status' => (string) ($filters['status'] ?? ''),
    'type' => (string) ($filters['type'] ?? ''),
    'q' => (string) ($filters['q'] ?? ''),
    'from_date' => (string) ($filters['from_date'] ?? ''),
    'to_date' => (string) ($filters['to_date'] ?? ''),
];
$buildUrl = static function (array $extra) use ($baseQuery): string {
    $query = array_merge($baseQuery, $extra);
    $query = array_filter($query, static fn($value) => $value !== '' && $value !== null);
    $queryString = http_build_query($query);
    return '/transactions' . ($queryString !== '' ? '?' . $queryString : '');
};
$sortBy = (string) ($pagination['sort_by'] ?? 'transaction_date');
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
$exportUrl = '/transactions/export' . ($exportQuery !== [] ? '?' . http_build_query($exportQuery) : '');
?>

<div class="transactions-page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Transactions</h1>
            <p class="page-subtitle">Gerez toutes vos ecritures comptables</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn btn-soft" onclick="window.location.href='<?= htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8') ?>'">
                Exporter Excel
            </button>
            <button class="btn btn-add" onclick="window.location.href='/transactions?mode=create'">
                <span>+</span> Nouvelle transaction
            </button>
        </div>
    </div>

    <?php if ($flashSuccess !== ''): ?>
    <div class="flash-message flash-success"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($flashError !== ''): ?>
    <div class="flash-message flash-error"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($showCreateForm): ?>
    <div class="card" style="margin-bottom: 20px;">
        <h3 style="margin-bottom: 16px;">Nouvelle transaction</h3>
        <form method="POST" action="/transactions/store" class="transaction-create-form" data-async="true" data-async-success="Transaction enregistree." novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="filters-grid">
                <div class="filter-group">
                    <label>Date</label>
                    <input type="date" class="filter-input" name="transaction_date" value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="filter-group">
                    <label>Type</label>
                    <select class="filter-select js-transaction-type" name="type" required>
                        <option value="income">Revenu occasionnel</option>
                        <option value="expense">Depense</option>
                        <option value="transfer">Transfert</option>
                        <option value="journal">Journal</option>
                        <option value="debt_payment">Remboursement de dettes</option>
                    </select>
                </div>
                <div class="filter-group js-expense-subcategory-group" style="display:none;">
                    <label>Sous-categorie depense</label>
                    <select class="filter-select js-expense-subcategory" name="expense_subcategory">
                        <option value="">Selectionnez une sous-categorie</option>
                        <?php foreach ($expenseSubcategoryLabels as $value => $label): ?>
                        <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group js-expense-fiscal-group" style="display:none;">
                    <label>Detail fiscal</label>
                    <select class="filter-select js-expense-fiscal-subcategory" name="expense_fiscal_subcategory">
                        <option value="">Selectionnez un detail fiscal</option>
                        <?php foreach ($expenseFiscalSubcategoryLabels as $value => $label): ?>
                        <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group js-expense-other-group" style="display:none;">
                    <label>Autre a decrire</label>
                    <input type="text" class="filter-input js-expense-other-input" name="expense_subcategory_other" placeholder="Ex: Maintenance imprimerie">
                </div>
                <div class="filter-group">
                    <label>Statut</label>
                    <select class="filter-select js-status-select" name="status" required>
                        <option value="draft">Brouillon</option>
                        <option value="posted">Validee</option>
                        <option value="void">Annulee</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="js-amount-label">Montant</label>
                    <input type="number" class="filter-input js-debt-amount" name="amount" min="0.01" step="0.01" required>
                </div>
                <div class="filter-group">
                    <label>Compte</label>
                    <select class="filter-select js-account-select" name="account_id">
                        <option value="">Compte systeme automatique</option>
                        <?php foreach ($accounts as $account): ?>
                        <option value="<?= (int) $account['id'] ?>">
                            <?= htmlspecialchars((string) $account['code'] . ' ' . (string) $account['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="js-fiscal-treasury-note" style="display:none;color:var(--text-secondary);font-size:11px;">Les depenses fiscales sont prelevees automatiquement depuis la tresorerie.</small>
                </div>
                <div class="filter-group">
                    <label>Reference</label>
                    <input type="text" class="filter-input" name="reference_preview" value="<?= htmlspecialchars((string) $nextTransactionReference, ENT_QUOTES, 'UTF-8') ?>" readonly>
                    <small class="text-secondary" style="font-size:11px;">Reference generee automatiquement a l'enregistrement.</small>
                </div>
                <div class="filter-group js-debt-client-group" style="grid-column: 1 / -1; display:none;">
                    <label>Client endette</label>
                    <div class="debt-client-search">
                        <input type="text" class="filter-input js-debt-client-query" placeholder="Nom ou telephone">
                        <input type="hidden" name="debt_client_name" class="js-debt-client-name">
                        <input type="hidden" name="debt_client_phone" class="js-debt-client-phone">
                        <div class="client-suggestions debt-client-suggestions js-debt-client-suggestions" hidden></div>
                    </div>
                    <small class="text-secondary" style="font-size:11px;">Tapez pour rechercher un client avec factures impayees.</small>
                </div>
                <div class="filter-group js-debt-invoices-group" style="grid-column: 1 / -1; display:none;">
                    <label>Factures concernees</label>
                    <div class="debt-invoice-list js-debt-invoice-list"></div>
                    <small class="text-secondary js-debt-invoice-summary" style="font-size:11px;"></small>
                </div>
                <div class="filter-group" style="grid-column: 1 / -1;">
                    <label>Description</label>
                    <input type="text" class="filter-input js-transaction-description" name="description" placeholder="Description de l'ecriture" required>
                </div>
            </div>

            <div class="filter-actions">
                <button type="button" class="btn" onclick="window.location.href='/transactions'">Annuler</button>
                <button type="submit" class="btn btn-add">Enregistrer</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php if (is_array($editingTransaction) && $editingTransaction !== []): ?>
    <div class="card" style="margin-bottom: 20px;">
        <h3 style="margin-bottom: 16px;">Modifier transaction #<?= (int) $editingTransaction['id'] ?></h3>
        <form method="POST" action="/transactions/update/<?= (int) $editingTransaction['id'] ?>" class="transaction-create-form" data-async="true" data-async-success="Transaction mise a jour." novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <?php
            $firstEntry = $editingTransaction['entries'][0] ?? null;
            $existingAmount = 0.0;
            if (is_array($firstEntry)) {
                $existingAmount = max((float) ($firstEntry['debit'] ?? 0), (float) ($firstEntry['credit'] ?? 0));
            }
            ?>
            <div class="filters-grid">
                <div class="filter-group">
                    <label>Date</label>
                    <input type="date" class="filter-input" name="transaction_date" value="<?= htmlspecialchars((string) $editingTransaction['transaction_date'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="filter-group">
                    <label>Type</label>
                    <select class="filter-select js-transaction-type" name="type" required>
                        <option value="income" <?= ($editingTransaction['type'] === 'income') ? 'selected' : '' ?>>Revenu occasionnel</option>
                        <option value="expense" <?= ($editingTransaction['type'] === 'expense') ? 'selected' : '' ?>>Depense</option>
                        <option value="transfer" <?= ($editingTransaction['type'] === 'transfer') ? 'selected' : '' ?>>Transfert</option>
                        <option value="journal" <?= ($editingTransaction['type'] === 'journal') ? 'selected' : '' ?>>Journal</option>
                        <option value="debt_payment" <?= ($editingTransaction['type'] === 'debt_payment') ? 'selected' : '' ?>>Remboursement de dettes</option>
                    </select>
                </div>
                <div class="filter-group js-expense-subcategory-group" style="<?= (($editingTransaction['type'] ?? '') === 'expense') ? '' : 'display:none;' ?>">
                    <label>Sous-categorie depense</label>
                    <select class="filter-select js-expense-subcategory" name="expense_subcategory">
                        <option value="">Selectionnez une sous-categorie</option>
                        <?php foreach ($expenseSubcategoryLabels as $value => $label): ?>
                        <option
                            value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>"
                            <?= (($editingTransaction['expense_subcategory'] ?? '') === $value) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group js-expense-fiscal-group" style="<?= (($editingTransaction['type'] ?? '') === 'expense' && ($editingTransaction['expense_subcategory'] ?? '') === 'fiscal') ? '' : 'display:none;' ?>">
                    <label>Detail fiscal</label>
                    <select class="filter-select js-expense-fiscal-subcategory" name="expense_fiscal_subcategory">
                        <option value="">Selectionnez un detail fiscal</option>
                        <?php foreach ($expenseFiscalSubcategoryLabels as $value => $label): ?>
                        <option
                            value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>"
                            <?= (($editingTransaction['expense_fiscal_subcategory'] ?? '') === $value) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group js-expense-other-group" style="<?= (($editingTransaction['type'] ?? '') === 'expense' && ($editingTransaction['expense_subcategory'] ?? '') === 'other') ? '' : 'display:none;' ?>">
                    <label>Autre a decrire</label>
                    <input
                        type="text"
                        class="filter-input js-expense-other-input"
                        name="expense_subcategory_other"
                        value="<?= htmlspecialchars((string) ($editingTransaction['expense_subcategory_other'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Ex: Maintenance imprimerie"
                    >
                </div>
                <div class="filter-group">
                    <label>Statut</label>
                    <select class="filter-select js-status-select" name="status" required>
                        <option value="draft" <?= ($editingTransaction['status'] === 'draft') ? 'selected' : '' ?>>Brouillon</option>
                        <option value="posted" <?= ($editingTransaction['status'] === 'posted') ? 'selected' : '' ?>>Validee</option>
                        <option value="void" <?= ($editingTransaction['status'] === 'void') ? 'selected' : '' ?>>Annulee</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="js-amount-label">Montant</label>
                    <input type="number" class="filter-input js-debt-amount" name="amount" min="0.01" step="0.01" value="<?= htmlspecialchars(number_format($existingAmount, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="filter-group">
                    <label>Compte</label>
                    <select class="filter-select js-account-select" name="account_id">
                        <option value="">Compte systeme automatique</option>
                        <?php foreach ($accounts as $account): ?>
                        <option
                            value="<?= (int) $account['id'] ?>"
                            <?= ((int) ($firstEntry['account_id'] ?? 0) === (int) $account['id']) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars((string) $account['code'] . ' ' . (string) $account['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="js-fiscal-treasury-note" style="<?= (($editingTransaction['type'] ?? '') === 'expense' && ($editingTransaction['expense_subcategory'] ?? '') === 'fiscal') ? '' : 'display:none;' ?>;color:var(--text-secondary);font-size:11px;">Les depenses fiscales sont prelevees automatiquement depuis la tresorerie.</small>
                </div>
                <div class="filter-group">
                    <label>Reference</label>
                    <input type="text" class="filter-input" name="reference_preview" value="<?= htmlspecialchars((string) ($editingTransaction['reference'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>" readonly>
                    <small class="text-secondary" style="font-size:11px;">Reference gerée automatiquement.</small>
                </div>
                <div class="filter-group" style="grid-column: 1 / -1;">
                    <label>Description</label>
                    <input type="text" class="filter-input js-transaction-description" name="description" value="<?= htmlspecialchars((string) $editingTransaction['description'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
            </div>

            <div class="filter-actions">
                <button type="button" class="btn" onclick="window.location.href='/transactions'">Annuler</button>
                <button type="submit" class="btn btn-primary">Mettre a jour</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="filters-card card">
        <form method="GET" action="/transactions" data-auto-filter="true">
            <input type="hidden" name="sort_by" value="<?= htmlspecialchars((string) $pagination['sort_by'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="sort_dir" value="<?= htmlspecialchars((string) $pagination['sort_dir'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="filters-grid">
                <div class="filter-group">
                    <label>Du</label>
                    <input type="date" class="filter-input" name="from_date" value="<?= htmlspecialchars((string) $filters['from_date'], ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="filter-group">
                    <label>Au</label>
                    <input type="date" class="filter-input" name="to_date" value="<?= htmlspecialchars((string) $filters['to_date'], ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="filter-group">
                    <label>Type</label>
                    <select class="filter-select" name="type">
                        <option value="">Tous</option>
                        <option value="income" <?= ($filters['type'] === 'income') ? 'selected' : '' ?>>Revenu occasionnel</option>
                        <option value="expense" <?= ($filters['type'] === 'expense') ? 'selected' : '' ?>>Depense</option>
                        <option value="transfer" <?= ($filters['type'] === 'transfer') ? 'selected' : '' ?>>Transfert</option>
                        <option value="journal" <?= ($filters['type'] === 'journal') ? 'selected' : '' ?>>Journal</option>
                        <option value="billing" <?= ($filters['type'] === 'billing') ? 'selected' : '' ?>>Facturation</option>
                        <option value="debt_payment" <?= ($filters['type'] === 'debt_payment') ? 'selected' : '' ?>>Remboursement de dettes</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Statut</label>
                    <select class="filter-select" name="status">
                        <option value="">Tous</option>
                        <option value="posted" <?= ($filters['status'] === 'posted') ? 'selected' : '' ?>>Validee</option>
                        <option value="draft" <?= ($filters['status'] === 'draft') ? 'selected' : '' ?>>Brouillon</option>
                        <option value="void" <?= ($filters['status'] === 'void') ? 'selected' : '' ?>>Annulee</option>
                    </select>
                </div>

                <div class="filter-group" style="grid-column: 1 / -1;">
                    <label>Recherche</label>
                    <input type="text" class="filter-input" name="q" value="<?= htmlspecialchars((string) $filters['q'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Description ou reference...">
                </div>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-soft js-auto-filter-submit">Appliquer</button>
                <button type="button" class="btn" onclick="window.location.href='/transactions'">Reinitialiser</button>
            </div>
        </form>
    </div>

    <div class="summary-stats" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 24px;">
        <div class="stat-mini">
            <span class="stat-label">Total debit</span>
            <span class="stat-number text-success">$<?= number_format((float) $summary['total_debit'], 2) ?></span>
        </div>
        <div class="stat-mini">
            <span class="stat-label">Total credit</span>
            <span class="stat-number text-danger">$<?= number_format((float) $summary['total_credit'], 2) ?></span>
        </div>
        <div class="stat-mini">
            <span class="stat-label">Solde</span>
            <span class="stat-number">$<?= number_format((float) $summary['balance'], 2) ?></span>
        </div>
        <div class="stat-mini">
            <span class="stat-label">Transactions</span>
            <span class="stat-number"><?= (int) $summary['total_count'] ?></span>
        </div>
    </div>

    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th><a href="<?= htmlspecialchars($sortLink('transaction_date'), ENT_QUOTES, 'UTF-8') ?>">Date<?= htmlspecialchars($sortMark('transaction_date'), ENT_QUOTES, 'UTF-8') ?></a></th>
                    <th><a href="<?= htmlspecialchars($sortLink('description'), ENT_QUOTES, 'UTF-8') ?>">Description<?= htmlspecialchars($sortMark('description'), ENT_QUOTES, 'UTF-8') ?></a></th>
                    <th><a href="<?= htmlspecialchars($sortLink('type'), ENT_QUOTES, 'UTF-8') ?>">Type<?= htmlspecialchars($sortMark('type'), ENT_QUOTES, 'UTF-8') ?></a></th>
                    <th>Compte</th>
                    <th><a href="<?= htmlspecialchars($sortLink('debit_total'), ENT_QUOTES, 'UTF-8') ?>">Debit<?= htmlspecialchars($sortMark('debit_total'), ENT_QUOTES, 'UTF-8') ?></a></th>
                    <th><a href="<?= htmlspecialchars($sortLink('credit_total'), ENT_QUOTES, 'UTF-8') ?>">Credit<?= htmlspecialchars($sortMark('credit_total'), ENT_QUOTES, 'UTF-8') ?></a></th>
                    <th><a href="<?= htmlspecialchars($sortLink('status'), ENT_QUOTES, 'UTF-8') ?>">Statut<?= htmlspecialchars($sortMark('status'), ENT_QUOTES, 'UTF-8') ?></a></th>
                    <th>Cree par</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($transactions === []): ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 24px; color: var(--text-secondary);">Aucune transaction trouvee.</td>
                </tr>
                <?php endif; ?>

                <?php foreach ($transactions as $transaction): ?>
                <?php
                    $source = (string) ($transaction['source'] ?? 'transaction');
                    $sourceId = (int) ($transaction['source_id'] ?? $transaction['id']);
                ?>
                <tr>
                    <td><?= htmlspecialchars(date('d/m/Y', strtotime((string) $transaction['transaction_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $transaction['description'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="category-badge">
                            <?php
                            $transactionTypeLabel = $typeLabels[(string) $transaction['type']] ?? (string) $transaction['type'];
                            if ((string) $transaction['type'] === 'expense' && !empty($transaction['expense_subcategory'])) {
                                $subcategoryLabel = $expenseSubcategoryLabels[(string) $transaction['expense_subcategory']] ?? (string) $transaction['expense_subcategory'];
                                $transactionTypeLabel .= ' - ' . $subcategoryLabel;
                                if ((string) ($transaction['expense_subcategory'] ?? '') === 'fiscal' && !empty($transaction['expense_fiscal_subcategory'])) {
                                    $fiscalLabel = $expenseFiscalSubcategoryLabels[(string) $transaction['expense_fiscal_subcategory']] ?? (string) $transaction['expense_fiscal_subcategory'];
                                    $transactionTypeLabel .= ' / ' . $fiscalLabel;
                                }
                            }
                            ?>
                            <?= htmlspecialchars((string) $transactionTypeLabel, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars((string) $transaction['account_label'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="text-success"><?= ((float) $transaction['debit_total'] > 0) ? '$' . number_format((float) $transaction['debit_total'], 2) : '-' ?></td>
                    <td class="text-danger"><?= ((float) $transaction['credit_total'] > 0) ? '$' . number_format((float) $transaction['credit_total'], 2) : '-' ?></td>
                    <td>
                        <?php $statusClass = 'status-' . strtolower(str_replace(' ', '-', (string) $transaction['status'])); ?>
                        <span class="status-badge <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($statusLabels[(string) $transaction['status']] ?? (string) $transaction['status'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars(trim((string) ($transaction['created_by_name'] ?? '')) !== '' ? (string) $transaction['created_by_name'] : 'Utilisateur', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if ($source === 'invoice_payment'): ?>
                        <a class="btn-icon" href="/invoices/preview/<?= $sourceId ?>" title="Voir facture" aria-label="Voir facture" data-no-async="true"><i class="fa-solid fa-file-invoice-dollar"></i></a>
                        <?php elseif ((string) ($transaction['type'] ?? '') === 'debt_payment'): ?>
                        <a class="btn-icon" href="/receipts/preview/<?= (int) $transaction['id'] ?>" title="Voir recu" aria-label="Voir recu" data-no-async="true"><i class="fa-solid fa-receipt"></i></a>
                        <a class="btn-icon" href="/receipts/pdf/<?= (int) $transaction['id'] ?>" title="Telecharger PDF" aria-label="Telecharger PDF" data-no-async="true"><i class="fa-solid fa-file-pdf"></i></a>
                        <?php elseif (in_array((string) ($transaction['type'] ?? ''), ['income', 'expense'], true)): ?>
                        <a class="btn-icon" href="/transactions/preview/<?= (int) $transaction['id'] ?>" title="Voir bon" aria-label="Voir bon" data-no-async="true"><i class="fa-solid fa-eye"></i></a>
                        <a class="btn-icon" href="/transactions/pdf/<?= (int) $transaction['id'] ?>" title="Telecharger PDF" aria-label="Telecharger PDF" data-no-async="true"><i class="fa-solid fa-file-pdf"></i></a>
                        <button class="btn-icon" type="button" onclick="window.location.href='/transactions/edit/<?= (int) $transaction['id'] ?>'" title="Modifier" aria-label="Modifier"><i class="fa-regular fa-pen-to-square"></i></button>
                        <form method="POST" action="/transactions/delete/<?= (int) $transaction['id'] ?>" style="display:inline;" data-async="true" data-async-success="Transaction supprimee." onsubmit="return confirm('Supprimer cette transaction ?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <button class="btn-icon" type="submit" title="Supprimer" aria-label="Supprimer"><i class="fa-solid fa-trash"></i></button>
                        </form>
                        <?php else: ?>
                        <button class="btn-icon" type="button" onclick="window.location.href='/transactions/edit/<?= (int) $transaction['id'] ?>'" title="Modifier" aria-label="Modifier"><i class="fa-regular fa-pen-to-square"></i></button>
                        <form method="POST" action="/transactions/delete/<?= (int) $transaction['id'] ?>" style="display:inline;" data-async="true" data-async-success="Transaction supprimee." onsubmit="return confirm('Supprimer cette transaction ?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <button class="btn-icon" type="submit" title="Supprimer" aria-label="Supprimer"><i class="fa-solid fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
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

</div>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.flash-message {
    border-radius: var(--radius-md);
    padding: 12px 14px;
    margin-bottom: 16px;
}

.flash-success {
    background: rgba(16, 185, 129, 0.14);
    color: var(--success);
}

.flash-error {
    background: rgba(239, 68, 68, 0.14);
    color: var(--danger);
}

.filters-card {
    margin-bottom: 24px;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-group label {
    font-size: 12px;
    color: var(--text-secondary);
    font-weight: 500;
}

.filter-select, .filter-input {
    padding: 10px;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    background: var(--bg-surface);
    color: var(--text-primary);
}

.filter-group.has-error label {
    color: #dc2626;
}

.filter-select.input-error,
.filter-input.input-error,
.debt-invoice-list.input-error {
    border-color: #dc2626;
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.12);
}

.field-error-message {
    display: block;
    margin-top: 4px;
    font-size: 11px;
    line-height: 1.35;
    color: #dc2626;
}

.filter-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.stat-mini {
    background: var(--bg-surface);
    padding: 16px;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
}

.stat-mini .stat-label {
    display: block;
    font-size: 12px;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.stat-mini .stat-number {
    font-size: 20px;
    font-weight: 600;
}

.category-badge {
    background: var(--accent-soft);
    color: var(--accent);
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.status-posted {
    background: #10B98120;
    color: var(--success);
}

.status-draft {
    background: #F59E0B20;
    color: var(--warning);
}

.status-void {
    background: #EF444420;
    color: var(--danger);
}

.table th a {
    color: inherit;
    text-decoration: none;
}

.debt-client-search {
    position: relative;
}

.debt-client-suggestions {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    z-index: 20;
    border: 1px solid var(--border-light);
    border-radius: 10px;
    background: var(--bg-surface);
    max-height: 200px;
    overflow-y: auto;
}

.debt-client-suggestions .client-suggestion-item {
    width: 100%;
    text-align: left;
    padding: 8px 10px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 12px;
}

.debt-client-suggestions .client-suggestion-item:hover {
    background: var(--accent-soft);
}

.client-suggestion-empty {
    padding: 8px 10px;
    font-size: 12px;
    color: var(--text-secondary);
}

.client-suggestion-name {
    display: block;
    font-weight: 600;
    color: var(--text-primary);
}

.client-suggestion-meta {
    display: block;
    margin-top: 3px;
    font-size: 11px;
    color: var(--text-secondary);
}

.debt-invoice-list {
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    padding: 10px;
    background: var(--bg-surface);
    display: grid;
    gap: 8px;
}

.debt-invoice-item {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    font-size: 12px;
    align-items: center;
}

.debt-invoice-item strong {
    font-weight: 600;
}

@media (max-width: 900px) {
    .filters-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .summary-stats {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}

@media (max-width: 640px) {
    .filters-grid {
        grid-template-columns: 1fr;
    }

    .summary-stats {
        grid-template-columns: 1fr !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('.transaction-create-form');

    forms.forEach((form) => {
        const typeSelect = form.querySelector('.js-transaction-type');
        const subcategoryGroup = form.querySelector('.js-expense-subcategory-group');
        const subcategorySelect = form.querySelector('.js-expense-subcategory');
        const fiscalGroup = form.querySelector('.js-expense-fiscal-group');
        const fiscalSelect = form.querySelector('.js-expense-fiscal-subcategory');
        const accountSelect = form.querySelector('.js-account-select');
        const fiscalTreasuryNote = form.querySelector('.js-fiscal-treasury-note');
        const otherGroup = form.querySelector('.js-expense-other-group');
        const otherInput = form.querySelector('.js-expense-other-input');
        const debtClientGroup = form.querySelector('.js-debt-client-group');
        const debtClientQuery = form.querySelector('.js-debt-client-query');
        const debtClientName = form.querySelector('.js-debt-client-name');
        const debtClientPhone = form.querySelector('.js-debt-client-phone');
        const debtClientSuggestions = form.querySelector('.js-debt-client-suggestions');
        const debtInvoicesGroup = form.querySelector('.js-debt-invoices-group');
        const debtInvoicesList = form.querySelector('.js-debt-invoice-list');
        const debtInvoicesSummary = form.querySelector('.js-debt-invoice-summary');
        const debtAmountInput = form.querySelector('.js-debt-amount');
        const descriptionInput = form.querySelector('.js-transaction-description');
        const statusSelect = form.querySelector('.js-status-select');
        const amountLabel = form.querySelector('.js-amount-label');

        if (!typeSelect) {
            return;
        }

        let debtLookupTimer = null;
        let debtLookupAbortController = null;
        let debtInvoices = [];
        let activeDebtClient = null;
        let debtInvoicesLoaded = false;
        const defaultAmountLabel = amountLabel ? amountLabel.textContent : 'Montant';

        const escapeHtml = (value) => String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const formatMoney = (value) => {
            const numeric = Number.parseFloat(value);
            if (!Number.isFinite(numeric)) {
                return '0.00';
            }
            return numeric.toFixed(2);
        };

        const isVisibleField = (field) => !!(field && field.offsetParent !== null);

        const resolveErrorContainer = (field) => {
            if (!field) {
                return null;
            }
            return field.closest('.filter-group') || field.parentElement;
        };

        const clearFieldError = (field) => {
            if (!field) {
                return;
            }
            field.classList.remove('input-error');
            const container = resolveErrorContainer(field);
            if (!container) {
                return;
            }
            container.classList.remove('has-error');
            const message = container.querySelector('.field-error-message');
            if (message) {
                message.remove();
            }
        };

        const setFieldError = (field, message) => {
            if (!field) {
                return;
            }
            field.classList.add('input-error');
            const container = resolveErrorContainer(field);
            if (!container) {
                return;
            }
            container.classList.add('has-error');
            let messageNode = container.querySelector('.field-error-message');
            if (!messageNode) {
                messageNode = document.createElement('small');
                messageNode.className = 'field-error-message';
                container.appendChild(messageNode);
            }
            messageNode.textContent = message;
        };

        const getFieldMessage = (field) => {
            if (!field) {
                return '';
            }
            if (field.validity.valueMissing) {
                if (field.type === 'date') {
                    return 'Veuillez renseigner la date.';
                }
                if (field.name === 'amount') {
                    return 'Veuillez renseigner le montant.';
                }
                if (field.tagName === 'SELECT') {
                    return 'Veuillez choisir une valeur.';
                }
                return 'Ce champ est requis.';
            }
            if (field.validity.rangeUnderflow) {
                return field.name === 'amount'
                    ? 'Le montant doit etre superieur a zero.'
                    : 'La valeur saisie est trop petite.';
            }
            if (field.validity.typeMismatch && field.type === 'email') {
                return 'Adresse email invalide.';
            }
            if (field.validity.stepMismatch) {
                return 'La valeur saisie est invalide.';
            }
            return '';
        };

        const validateVisibleField = (field) => {
            if (!field || field.disabled || field.type === 'hidden' || !isVisibleField(field)) {
                clearFieldError(field);
                return true;
            }
            const message = getFieldMessage(field);
            if (message !== '') {
                setFieldError(field, message);
                return false;
            }
            clearFieldError(field);
            return true;
        };

        const focusField = (field) => {
            if (!field || typeof field.focus !== 'function') {
                return;
            }
            field.focus();
            if (typeof field.scrollIntoView === 'function') {
                field.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        };

        const isDebtPayment = () => typeSelect.value === 'debt_payment';

        const resetDebtClient = () => {
            activeDebtClient = null;
            debtInvoices = [];
            debtInvoicesLoaded = false;
            if (debtClientName) {
                debtClientName.value = '';
            }
            if (debtClientPhone) {
                debtClientPhone.value = '';
            }
            if (debtInvoicesList) {
                debtInvoicesList.innerHTML = '<div class="text-secondary" style="font-size:12px;">Selectionnez un client pour voir ses factures impayees.</div>';
            }
            if (debtInvoicesSummary) {
                debtInvoicesSummary.textContent = '';
            }
            clearFieldError(debtClientQuery);
        };

        const hideDebtSuggestions = () => {
            if (!debtClientSuggestions) {
                return;
            }
            debtClientSuggestions.hidden = true;
            debtClientSuggestions.innerHTML = '';
        };

        const renderDebtSuggestions = (items) => {
            if (!debtClientSuggestions) {
                return;
            }
            if (!Array.isArray(items) || items.length === 0) {
                debtClientSuggestions.hidden = false;
                debtClientSuggestions.innerHTML = '<div class="client-suggestion-empty">Aucun client endette trouve.</div>';
                return;
            }
            debtClientSuggestions.hidden = false;
            debtClientSuggestions.innerHTML = items.map((item) => `
                <button type="button" class="client-suggestion-item"
                    data-name="${escapeHtml(String(item.name || ''))}"
                    data-phone="${escapeHtml(String(item.phone || ''))}"
                    data-debt="${escapeHtml(String(item.debt_total || 0))}"
                    data-count="${escapeHtml(String(item.debt_count || 0))}">
                    <span class="client-suggestion-name">${escapeHtml(String(item.name || 'Client'))}</span>
                    <span class="client-suggestion-meta">${escapeHtml(String(item.phone || '-'))} · ${escapeHtml(String(item.debt_count || 0))} dette(s) · $${escapeHtml(formatMoney(item.debt_total || 0))}</span>
                </button>
            `).join('');
        };

        const fetchDebtSuggestions = async (query) => {
            const value = String(query || '').trim();
            if (value === '' || !isDebtPayment()) {
                hideDebtSuggestions();
                return;
            }

            if (debtLookupAbortController) {
                debtLookupAbortController.abort();
            }
            debtLookupAbortController = new AbortController();

            try {
                const response = await fetch(`/api/clients/search?q=${encodeURIComponent(value)}&limit=6&only_debtors=1`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: debtLookupAbortController.signal,
                });
                if (!response.ok) {
                    throw new Error('lookup_failed');
                }
                const payload = await response.json();
                const items = Array.isArray(payload.items) ? payload.items : [];
                renderDebtSuggestions(items);
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
                hideDebtSuggestions();
            }
        };

        const computeDebtAllocations = () => {
            const amount = Number.parseFloat(String(debtAmountInput?.value || '0').replace(',', '.')) || 0;
            let remaining = Math.max(amount, 0);
            const allocations = [];
            let totalDebt = 0;

            debtInvoices.forEach((invoice) => {
                const balance = Math.max(Number(invoice.remaining || 0), 0);
                totalDebt += balance;
                if (remaining <= 0) {
                    return;
                }
                const applied = Math.min(balance, remaining);
                if (applied > 0) {
                    allocations.push({ ...invoice, applied });
                    remaining = Math.max(remaining - applied, 0);
                }
            });

            return {
                allocations,
                remaining,
                totalDebt,
                amount,
            };
        };

        const renderDebtInvoices = () => {
            if (!debtInvoicesList) {
                return;
            }
            if (!activeDebtClient) {
                debtInvoicesList.classList.remove('input-error');
                debtInvoicesList.innerHTML = '<div class="text-secondary" style="font-size:12px;">Selectionnez un client pour voir ses factures impayees.</div>';
                if (debtInvoicesSummary) {
                    debtInvoicesSummary.textContent = '';
                }
                return;
            }
            if (!debtInvoicesLoaded) {
                debtInvoicesList.classList.remove('input-error');
                debtInvoicesList.innerHTML = '<div class="text-secondary" style="font-size:12px;">Chargement des factures impayees...</div>';
                if (debtInvoicesSummary) {
                    debtInvoicesSummary.textContent = '';
                }
                return;
            }
            if (!Array.isArray(debtInvoices) || debtInvoices.length === 0) {
                debtInvoicesList.classList.add('input-error');
                debtInvoicesList.innerHTML = '<div class="text-secondary" style="font-size:12px;">Aucune facture impayee trouvee pour ce client.</div>';
                if (debtInvoicesSummary) {
                    debtInvoicesSummary.textContent = '';
                }
                return;
            }

            debtInvoicesList.classList.remove('input-error');

            const { allocations, remaining, totalDebt, amount } = computeDebtAllocations();
            const allocationMap = new Map(allocations.map((item) => [String(item.id), item]));

            debtInvoicesList.innerHTML = debtInvoices.map((invoice) => {
                const allocation = allocationMap.get(String(invoice.id));
                const applied = allocation ? allocation.applied : 0;
                return `
                    <div class="debt-invoice-item">
                        <div>
                            <strong>${escapeHtml(String(invoice.invoice_number || ''))}</strong>
                            <span class="text-secondary">· ${escapeHtml(String(invoice.invoice_date || ''))}</span>
                        </div>
                        <div>
                            <span>Reste $${escapeHtml(formatMoney(invoice.remaining || 0))}</span>
                            <span class="text-secondary">→ Paye $${escapeHtml(formatMoney(applied))}</span>
                        </div>
                    </div>
                `;
            }).join('');

            if (debtInvoicesSummary) {
                if (amount > totalDebt + 0.01) {
                    debtInvoicesSummary.textContent = `Montant superieur a la dette ($${formatMoney(totalDebt)}).`;
                } else if (remaining > 0.009) {
                    debtInvoicesSummary.textContent = `Reste non affecte: $${formatMoney(remaining)}.`;
                } else {
                    debtInvoicesSummary.textContent = `Total dette: $${formatMoney(totalDebt)}.`;
                }
            }
        };

        const fetchDebtInvoices = async () => {
            if (!activeDebtClient || !isDebtPayment()) {
                return;
            }
            debtInvoicesLoaded = false;
            renderDebtInvoices();
            const params = new URLSearchParams();
            if (activeDebtClient.phone) {
                params.append('phone', activeDebtClient.phone);
            }
            if (activeDebtClient.name) {
                params.append('name', activeDebtClient.name);
            }
            try {
                const response = await fetch(`/api/clients/outstanding?${params.toString()}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) {
                    throw new Error('fetch_failed');
                }
                const payload = await response.json();
                debtInvoices = Array.isArray(payload.items) ? payload.items : [];
                debtInvoicesLoaded = true;
                renderDebtInvoices();
                validateDebtClientState();
            } catch (error) {
                debtInvoices = [];
                debtInvoicesLoaded = true;
                renderDebtInvoices();
                validateDebtClientState();
            }
        };

        const syncDebtDescription = () => {
            if (!descriptionInput) {
                return;
            }
            if (!isDebtPayment()) {
                descriptionInput.readOnly = false;
                return;
            }
            const clientLabel = activeDebtClient?.name || activeDebtClient?.phone || '';
            descriptionInput.readOnly = true;
            descriptionInput.value = clientLabel !== '' ? `Remboursement de dettes - ${clientLabel}` : 'Remboursement de dettes';
        };

        const validateDebtClientState = () => {
            if (!debtClientQuery) {
                return true;
            }
            if (!isDebtPayment()) {
                clearFieldError(debtClientQuery);
                if (debtInvoicesList) {
                    debtInvoicesList.classList.remove('input-error');
                }
                return true;
            }
            if (!activeDebtClient || (!activeDebtClient.name && !activeDebtClient.phone)) {
                setFieldError(debtClientQuery, 'Selectionnez un client endette.');
                if (debtInvoicesList) {
                    debtInvoicesList.classList.remove('input-error');
                }
                return false;
            }
            if (!Array.isArray(debtInvoices) || debtInvoices.length === 0) {
                if (debtInvoicesLoaded) {
                    setFieldError(debtClientQuery, 'Aucune facture impayee pour ce client.');
                    if (debtInvoicesList) {
                        debtInvoicesList.classList.add('input-error');
                    }
                    return false;
                }
                clearFieldError(debtClientQuery);
                if (debtInvoicesList) {
                    debtInvoicesList.classList.remove('input-error');
                }
                return true;
            }
            clearFieldError(debtClientQuery);
            if (debtInvoicesList) {
                debtInvoicesList.classList.remove('input-error');
            }
            return true;
        };

        const validateDebtAmount = () => {
            if (!debtAmountInput) {
                return true;
            }
            if (!validateVisibleField(debtAmountInput)) {
                return false;
            }
            const amount = Number.parseFloat(String(debtAmountInput.value || '0').replace(',', '.')) || 0;
            if (amount <= 0) {
                setFieldError(debtAmountInput, 'Saisissez un montant valide.');
                return false;
            }
            clearFieldError(debtAmountInput);
            return true;
        };

        const validateForm = () => {
            let valid = true;
            Array.from(form.querySelectorAll('input, select, textarea')).forEach((field) => {
                if (field.type === 'hidden') {
                    return;
                }
                if (field === debtClientQuery) {
                    return;
                }
                if (field === debtAmountInput && isDebtPayment()) {
                    return;
                }
                if (!validateVisibleField(field)) {
                    valid = false;
                }
            });

            if (isDebtPayment()) {
                if (!validateDebtClientState()) {
                    valid = false;
                }
                if (!validateDebtAmount()) {
                    valid = false;
                }
            } else if (debtAmountInput && !validateVisibleField(debtAmountInput)) {
                valid = false;
            }

            return valid;
        };

        const sync = () => {
            const isExpense = typeSelect.value === 'expense';
            const isOther = subcategorySelect ? subcategorySelect.value === 'other' : false;
            const isFiscal = subcategorySelect ? subcategorySelect.value === 'fiscal' : false;
            const debtMode = isDebtPayment();

            if (subcategoryGroup && subcategorySelect) {
                subcategoryGroup.style.display = isExpense && !debtMode ? '' : 'none';
                subcategorySelect.required = isExpense && !debtMode;
                if (!isExpense || debtMode) {
                    subcategorySelect.value = '';
                }
            }

            if (fiscalGroup && fiscalSelect) {
                fiscalGroup.style.display = isExpense && isFiscal && !debtMode ? '' : 'none';
                fiscalSelect.required = isExpense && isFiscal && !debtMode;
                if (!(isExpense && isFiscal) || debtMode) {
                    fiscalSelect.value = '';
                }
            }

            if (accountSelect && fiscalTreasuryNote) {
                accountSelect.disabled = (isExpense && isFiscal) || debtMode;
                fiscalTreasuryNote.style.display = isExpense && isFiscal && !debtMode ? '' : 'none';
                if ((isExpense && isFiscal) || debtMode) {
                    accountSelect.value = '';
                }
            }

            if (otherGroup && otherInput) {
                otherGroup.style.display = isExpense && isOther && !debtMode ? '' : 'none';
                otherInput.required = isExpense && isOther && !debtMode;
                if (!(isExpense && isOther) || debtMode) {
                    otherInput.value = '';
                }
            }

            if (debtClientGroup) {
                debtClientGroup.style.display = debtMode ? '' : 'none';
            }
            if (debtInvoicesGroup) {
                debtInvoicesGroup.style.display = debtMode ? '' : 'none';
            }
            if (amountLabel) {
                amountLabel.textContent = debtMode ? 'Somme remboursee' : defaultAmountLabel;
            }
            if (statusSelect) {
                if (debtMode) {
                    statusSelect.value = 'posted';
                    statusSelect.disabled = true;
                } else {
                    statusSelect.disabled = false;
                }
            }
            if (!debtMode) {
                resetDebtClient();
                hideDebtSuggestions();
                if (debtInvoicesList) {
                    debtInvoicesList.classList.remove('input-error');
                }
            }
            syncDebtDescription();
            if (debtMode) {
                renderDebtInvoices();
            }
        };

        if (debtClientQuery && debtClientSuggestions) {
            debtClientQuery.addEventListener('input', () => {
                if (!isDebtPayment()) {
                    return;
                }
                activeDebtClient = null;
                if (debtClientName) {
                    debtClientName.value = '';
                }
                if (debtClientPhone) {
                    debtClientPhone.value = '';
                }
                debtInvoices = [];
                debtInvoicesLoaded = false;
                renderDebtInvoices();
                validateDebtClientState();
                if (debtLookupTimer) {
                    clearTimeout(debtLookupTimer);
                }
                debtLookupTimer = setTimeout(() => {
                    fetchDebtSuggestions(debtClientQuery.value);
                }, 160);
            });

            debtClientQuery.addEventListener('focus', () => {
                if (String(debtClientQuery.value || '').trim() !== '') {
                    fetchDebtSuggestions(debtClientQuery.value);
                }
            });

            debtClientSuggestions.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) {
                    return;
                }
                const button = target.closest('.client-suggestion-item');
                if (!button) {
                    return;
                }
                const name = String(button.dataset.name || '');
                const phone = String(button.dataset.phone || '');
                activeDebtClient = { name, phone };
                if (debtClientName) {
                    debtClientName.value = name;
                }
                if (debtClientPhone) {
                    debtClientPhone.value = phone;
                }
                if (debtClientQuery) {
                    debtClientQuery.value = name !== '' ? `${name} (${phone || '-'})` : phone;
                }
                hideDebtSuggestions();
                syncDebtDescription();
                validateDebtClientState();
                fetchDebtInvoices();
            });
        }

        if (debtAmountInput) {
            debtAmountInput.addEventListener('input', () => {
                if (!isDebtPayment()) {
                    validateVisibleField(debtAmountInput);
                    return;
                }
                renderDebtInvoices();
                validateDebtAmount();
            });
            debtAmountInput.addEventListener('change', () => {
                if (isDebtPayment()) {
                    renderDebtInvoices();
                    validateDebtAmount();
                    return;
                }
                validateVisibleField(debtAmountInput);
            });
        }

        form.addEventListener('submit', (event) => {
            if (!validateForm()) {
                event.preventDefault();
                focusField(form.querySelector('.input-error'));
                return;
            }
        });

        Array.from(form.querySelectorAll('input, select, textarea')).forEach((field) => {
            if (field.type === 'hidden') {
                return;
            }
            field.addEventListener('input', () => {
                if (field === debtClientQuery) {
                    validateDebtClientState();
                    return;
                }
                if (field === debtAmountInput && isDebtPayment()) {
                    validateDebtAmount();
                    return;
                }
                validateVisibleField(field);
            });
            field.addEventListener('change', () => {
                if (field === debtClientQuery) {
                    validateDebtClientState();
                    return;
                }
                if (field === debtAmountInput && isDebtPayment()) {
                    validateDebtAmount();
                    return;
                }
                validateVisibleField(field);
            });
        });

        typeSelect.addEventListener('change', sync);
        if (subcategorySelect) {
            subcategorySelect.addEventListener('change', sync);
        }
        sync();
    });
});
</script>
