<?php
$today = $today ?? date('Y-m-d');
$dueDate = $dueDate ?? date('Y-m-d', strtotime('+15 days'));
$isTemplateMode = $isTemplateMode ?? (isset($_GET['template']) && $_GET['template'] === '1');
$invoiceNumber = $invoiceNumber ?? ('INV-' . date('Y') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT));
$flashError = $flashError ?? '';
$isEditMode = $isEditMode ?? false;
$invoiceToEdit = $invoiceToEdit ?? null;
$formAction = $formAction ?? '/invoices/store';
$submitLabel = $submitLabel ?? 'Enregistrer la vente';
$canSaveDraft = $canSaveDraft ?? true;
$invoiceProducts = $invoiceProducts ?? [];
$defaultTaxRate = isset($defaultTaxRate) ? round((float) $defaultTaxRate, 2) : 0.0;
$csrfToken = App\Core\Security::generateCSRF();
$invoiceType = 'product';
$clientType = 'known';
$clientNameValue = (string) ($invoiceToEdit['customer_name'] ?? '');
$clientPhoneValue = (string) ($invoiceToEdit['customer_phone'] ?? '');
$clientLookupValue = '';
if ($clientPhoneValue !== '' && $clientNameValue !== '') {
    $clientLookupValue = $clientPhoneValue . ' - ' . $clientNameValue;
} elseif ($clientPhoneValue !== '') {
    $clientLookupValue = $clientPhoneValue;
} else {
    $clientLookupValue = $clientNameValue;
}

$lineItems = [
    [
        'description' => $isTemplateMode ? 'Abonnement mensuel comptabilite intelligente' : '',
        'product_id' => null,
        'unit_code' => '',
        'qty' => 1,
        'price' => $isTemplateMode ? 250 : 0,
        'tax' => $defaultTaxRate,
    ],
];

if ($isTemplateMode) {
    $lineItems[] = [
        'description' => 'Support et analyse fiscale',
        'product_id' => null,
        'unit_code' => '',
        'qty' => 1,
        'price' => 120,
        'tax' => $defaultTaxRate,
    ];
}

if (is_array($invoiceToEdit) && $invoiceToEdit !== []) {
    $invoiceType = (string) (($invoiceToEdit['invoice_type'] ?? 'product') === 'service' ? 'service' : 'product');
    $existingName = trim((string) ($invoiceToEdit['customer_name'] ?? ''));
    if ($existingName === '' || strtolower($existingName) === 'client anonyme') {
        $clientType = 'anonymous';
    }
    $lineItems = [];
    foreach (($invoiceToEdit['items'] ?? []) as $item) {
        $lineItems[] = [
            'description' => (string) ($item['description'] ?? ''),
            'product_id' => isset($item['product_id']) ? (int) $item['product_id'] : null,
            'unit_code' => (string) ($item['unit_code'] ?? ''),
            'qty' => (float) ($item['quantity'] ?? 1),
            'price' => (float) ($item['unit_price'] ?? 0),
            'tax' => (float) ($item['tax_rate'] ?? $defaultTaxRate),
        ];
    }
    if ($lineItems === []) {
        $lineItems[] = ['description' => '', 'product_id' => null, 'unit_code' => '', 'qty' => 1, 'price' => 0, 'tax' => $defaultTaxRate];
    }
}
?>

<div class="invoice-create-page">
    <div class="page-header invoice-header">
        <div>
            <p class="invoice-breadcrumb">Ventes / <?= $isEditMode ? 'Modifier brouillon' : 'Nouvelle vente' ?></p>
            <h1 class="page-title invoice-title-hero"><?= $isEditMode ? 'Modifier la vente brouillon' : 'Enregistrer une vente' ?></h1>
            <p class="page-subtitle invoice-subtitle-hero">
                <?= $isEditMode ? 'Completez le brouillon puis validez la vente.' : 'Renseigne rapidement le client, les produits et valide la vente.' ?>
            </p>
        </div>
        <a href="/invoices" class="btn btn-soft"><i class="fa-solid fa-arrow-left"></i> Retour aux ventes</a>
    </div>

    <?php if ($flashError !== ''): ?>
    <div class="flash-message flash-error" style="margin-bottom: 16px;">
        <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <form id="invoice-form" action="<?= htmlspecialchars((string) $formAction, ENT_QUOTES, 'UTF-8') ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" id="invoice-status-field" name="status" value="<?= htmlspecialchars((string) (($invoiceToEdit['status'] ?? 'envoyee')), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" id="summary-subtotal-input" name="summary_subtotal" value="0">
        <input type="hidden" id="summary-tax-input" name="summary_tax" value="0">
        <input type="hidden" id="summary-total-input" name="summary_total" value="0">

        <div class="invoice-create-layout">
            <div class="invoice-form-column">
                <section class="card invoice-section">
                    <h3 class="section-title">Informations vente</h3>
                    <div class="form-grid form-grid-three">
                        <label class="form-field">
                            <span>Numero vente</span>
                            <input type="text" name="invoice_number" value="<?= htmlspecialchars((string) ($invoiceToEdit['invoice_number'] ?? $invoiceNumber), ENT_QUOTES, 'UTF-8') ?>" readonly required>
                            <small class="text-secondary" style="font-size:11px;">Numero genere automatiquement.</small>
                        </label>
                        <label class="form-field">
                            <span>Date emission</span>
                            <input type="date" name="issue_date" value="<?= htmlspecialchars((string) ($invoiceToEdit['invoice_date'] ?? $today), ENT_QUOTES, 'UTF-8') ?>" required>
                        </label>
                        <label class="form-field">
                            <span>Date echeance</span>
                            <input type="date" name="due_date" value="<?= htmlspecialchars((string) ($invoiceToEdit['due_date'] ?? $dueDate), ENT_QUOTES, 'UTF-8') ?>" required>
                        </label>
                        <label class="form-field">
                            <span>Type de vente</span>
                            <select name="invoice_type" id="invoice-type-select">
                                <option value="product" <?= $invoiceType === 'product' ? 'selected' : '' ?>>Produits (stock)</option>
                                <option value="service" <?= $invoiceType === 'service' ? 'selected' : '' ?>>Service rendu</option>
                            </select>
                        </label>
                        <label class="form-field">
                            <span>Devise</span>
                            <input type="hidden" name="currency" id="invoice-currency" value="USD">
                            <input type="text" value="USD" readonly>
                        </label>
                        <label class="form-field">
                            <span>Mode paiement</span>
                            <select name="payment_method">
                                <option value="virement">Virement bancaire</option>
                                <option value="mobile-money">Mobile Money</option>
                                <option value="carte">Carte</option>
                                <option value="especes">Especes</option>
                            </select>
                        </label>
                     
                    </div>
                </section>

                <section class="card invoice-section">
                    <h3 class="section-title">Client</h3>
                    <div class="form-grid form-grid-two">
                        <label class="form-field form-field-full">
                            <span>Type de client</span>
                            <div class="client-type-toggle">
                                <label>
                                    <input type="radio" name="client_type" value="known" <?= $clientType === 'known' ? 'checked' : '' ?>>
                                    Client connu
                                </label>
                                <label>
                                    <input type="radio" name="client_type" value="anonymous" <?= $clientType === 'anonymous' ? 'checked' : '' ?>>
                                    Client anonyme
                                </label>
                            </div>
                        </label>
                        <label class="form-field client-known-field form-field-full">
                            <span>Nom ou telephone</span>
                            <input type="text" id="client-lookup-input" name="client_lookup" placeholder="Nom ou numero de telephone" value="<?= htmlspecialchars($clientLookupValue, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                            <input type="hidden" name="client_name" id="client-name-hidden" value="<?= htmlspecialchars($clientNameValue, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="client_phone" id="client-phone-hidden" value="<?= htmlspecialchars($clientPhoneValue, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="client-suggestions" id="client-suggestions" hidden></div>
                            <small class="text-secondary" style="font-size:11px;">Tapez un nom ou un numero. Les suggestions s'affichent en temps reel.</small>
                        </label>
                        
                    </div>
                </section>

                <section class="card invoice-section">
                    <div class="section-header">
                        <h3 class="section-title">Lignes de vente</h3>
                        <button type="button" class="btn btn-add" id="add-line-btn"> + Ajouter une ligne</button>
                    </div>

                    <div class="line-items-wrap">
                        <table class="line-items-table">
                            <colgroup>
                                <col class="line-col-description">
                                <col class="line-col-qty">
                                <col class="line-col-unit">
                                <col class="line-col-price">
                                <col class="line-col-tax">
                                <col class="line-col-total">
                                <col class="line-col-actions">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th>Quantite</th>
                                    <th>Unite</th>
                                    <th>Prix unitaire</th>
                                    <th>TVA (%)</th>
                                    <th>Total ligne</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="line-items-body">
                                <?php foreach ($lineItems as $item): ?>
                                <tr class="line-item-row">
                                    <td>
                                        <div class="line-product-wrap">
                                            <select class="line-product-id js-line-input">
                                                <option value="">Choisir un produit...</option>
                                                <option value="__search__">Rechercher un produit...</option>
                                                <?php foreach ($invoiceProducts as $product): ?>
                                                <?php $selectedProduct = ((int) ($item['product_id'] ?? 0)) === (int) ($product['id'] ?? 0); ?>
                                                <option
                                                    value="<?= (int) ($product['id'] ?? 0) ?>"
                                                    data-name="<?= htmlspecialchars((string) ($product['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-price="<?= htmlspecialchars(number_format((float) ($product['sale_price'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-stock="<?= htmlspecialchars(number_format((float) ($product['quantity'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                                                    <?= $selectedProduct ? 'selected' : '' ?>
                                                >
                                                    <?= htmlspecialchars((string) ($product['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?><?= !empty($product['supplier']) ? ' - ' . htmlspecialchars((string) $product['supplier'], ENT_QUOTES, 'UTF-8') : '' ?><?= !empty($product['sku']) ? ' (' . htmlspecialchars((string) $product['sku'], ENT_QUOTES, 'UTF-8') . ')' : '' ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="line-stock-hint text-secondary"></small>
                                            <div class="line-product-search" style="display:none;">
                                                <input type="text" class="line-product-search-input" placeholder="Rechercher un produit...">
                                                <div class="line-product-search-results"></div>
                                            </div>
                                        </div>
                                        <input
                                            type="text"
                                            class="line-description js-line-input"
                                            placeholder="Service"
                                            value="<?= htmlspecialchars((string) $item['description'], ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                        <input type="hidden" name="line_description[]" class="line-description-hidden" value="<?= htmlspecialchars((string) $item['description'], ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="line_product_id[]" class="line-product-hidden" value="<?= htmlspecialchars((string) ((int) ($item['product_id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>">
                                    </td>
                                    <td>
                                        <input
                                            type="number"
                                            name="line_qty[]"
                                            class="line-qty js-line-input"
                                            min="0.000001"
                                            step="0.000001"
                                            value="<?= htmlspecialchars((string) $item['qty'], ENT_QUOTES, 'UTF-8') ?>"
                                            required
                                        >
                                    </td>
                                    <td>
                                        <select name="line_unit_code[]" class="line-unit-code js-line-input" data-selected-unit="<?= htmlspecialchars((string) ($item['unit_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                            <option value="">Unite...</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input
                                            type="number"
                                            name="line_price[]"
                                            class="line-price js-line-input"
                                            min="0"
                                            step="0.01"
                                            value="<?= htmlspecialchars((string) $item['price'], ENT_QUOTES, 'UTF-8') ?>"
                                            required
                                            readonly
                                        >
                                    </td>
                                    <td>
                                        <select name="line_tax[]" class="line-tax js-line-input">
                                            <option value="0" <?= ((int) $item['tax']) === 0 ? 'selected' : '' ?>>0</option>
                                            <option value="5" <?= ((int) $item['tax']) === 5 ? 'selected' : '' ?>>5</option>
                                            <option value="10" <?= ((int) $item['tax']) === 10 ? 'selected' : '' ?>>10</option>
                                            <option value="16" <?= ((float) $item['tax']) === 16.0 ? 'selected' : '' ?>>16</option>
                                        </select>
                                    </td>
                                    <td>
                                        <span class="line-total" data-line-total>0.00</span>
                                    </td>
                                    <td>
                                        <button type="button" class="remove-line-btn" aria-label="Supprimer ligne">x</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

            </div>

            <aside class="invoice-side-column">
                <section class="card invoice-section summary-card">
                    <h3 class="section-title">Résumé vente</h3>
                    <div class="summary-group">
                        <div class="summary-row">
                            <span>Sous-total</span>
                            <strong id="summary-subtotal">0.00</strong>
                        </div>
                        <div class="summary-row">
                            <span>TVA</span>
                            <strong id="summary-tax">0.00</strong>
                        </div>
                        <div class="summary-row summary-inline">
                            <label for="discount-value">Remise</label>
                            <div class="summary-inline-controls">
                                <select id="discount-type" name="discount_type">
                                    <option value="percent">%</option>
                                    <option value="fixed">Fixe</option>
                                </select>
                                <input type="number" id="discount-value" name="discount_value" min="0" step="0.01" value="0">
                            </div>
                        </div>
                        <div class="summary-row">
                            <span>Acompte recu</span>
                            <input type="number" id="deposit-value" name="deposit_value" min="0" step="0.01" value="0">
                        </div>
                    </div>

                    <div class="summary-total">
                        <span>Total vente</span>
                        <strong id="summary-total">0.00</strong>
                    </div>
                    <div class="summary-balance">
                        <span>Reste a payer</span>
                        <strong id="summary-balance">0.00</strong>
                    </div>
                </section>

                <section class="card invoice-section">
                    <h3 class="section-title">Actions</h3>
                    <div class="side-actions">
                        <button type="button" class="btn btn-soft" id="preview-btn"><i class="fa-regular fa-eye"></i> Apercu rapide</button>
                        <?php if ($canSaveDraft): ?>
                        <button type="button" class="btn" id="save-draft-btn"><i class="fa-regular fa-floppy-disk"></i> Sauvegarder brouillon</button>
                        <?php endif; ?>
                        <button type="submit" class="btn <?= $isEditMode ? 'btn-primary' : 'btn-add' ?>" id="submit-invoice-btn"><i class="fa-regular fa-paper-plane"></i> <?= htmlspecialchars((string) $submitLabel, ENT_QUOTES, 'UTF-8') ?></button>
                    </div>
                </section>
            </aside>
        </div>
    </form>
</div>

<style>
.invoice-create-page {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.flash-message {
    border-radius: var(--radius-md);
    padding: 12px 14px;
}

.flash-error {
    background: rgba(239, 68, 68, 0.14);
    color: var(--danger);
}

.invoice-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 8px;
}

.invoice-breadcrumb {
    color: var(--text-secondary);
    font-size: 12px;
    margin-bottom: 8px;
}

.invoice-title-hero {
    margin-bottom: 8px;
    letter-spacing: 0.01em;
}

.invoice-subtitle-hero {
    max-width: 620px;
    color: var(--text-secondary);
    line-height: 1.55;
}

.invoice-create-layout {
    display: grid;
    grid-template-columns: minmax(0, 2.4fr) minmax(320px, 1fr);
    gap: 20px;
    align-items: start;
}

.invoice-form-column,
.invoice-side-column {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.invoice-side-column {
    position: sticky;
    top: 24px;
}

.invoice-section {
    padding: 22px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
}

.section-title {
    font-size: 18px;
    margin-bottom: 14px;
}

.section-header .section-title {
    margin-bottom: 0;
}

.form-grid {
    display: grid;
    gap: 14px;
}

.form-grid-two {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.form-grid-three {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.form-field {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.form-field span {
    color: var(--text-secondary);
    font-size: 12px;
    font-weight: 500;
}

.form-field input,
.form-field select,
.form-field textarea {
    width: 100%;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    background: var(--bg-surface);
    color: var(--text-primary);
    padding: 11px 12px;
    font: inherit;
}

.form-field textarea {
    resize: vertical;
}
if ($clientType === 'anonymous' && trim($clientNameValue) === '') {
    $clientNameValue = 'Client anonyme';
}

.client-type-toggle {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    padding: 8px 10px;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    background: var(--bg-primary);
}

.client-type-toggle label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--text-secondary);
}

.client-phone-row {
    display: flex;
    gap: 8px;
    align-items: center;
}

.client-phone-row select {
    flex: 0 0 140px;
}

.client-phone-row input {
    flex: 1;
}

.client-suggestions {
    margin-top: 8px;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    background: var(--bg-surface);
    max-height: 220px;
    overflow-y: auto;
    padding: 6px;
}

.client-suggestion-item {
    width: 100%;
    text-align: left;
    padding: 8px 10px;
    border: none;
    background: transparent;
    cursor: pointer;
    border-radius: 8px;
}

.client-suggestion-item:hover {
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
    font-size: 13px;
}

.client-suggestion-meta {
    display: block;
    font-size: 12px;
    color: var(--text-secondary);
}

.form-field-full {
    grid-column: 1 / -1;
}

.line-items-wrap {
    overflow-x: auto;
}

.line-items-table {
    width: 100%;
    min-width: 810px;
    border-collapse: collapse;
}

.line-items-table .line-col-qty {
    width: 94px;
}

.line-items-table .line-col-unit {
    width: 138px;
}

.line-items-table .line-col-price {
    width: 112px;
}

.line-items-table .line-col-tax {
    width: 82px;
}

.line-items-table .line-col-total {
    width: 146px;
}

.line-items-table .line-col-actions {
    width: 46px;
}

.line-items-table th,
.line-items-table td {
    padding: 10px 8px;
    border-bottom: 1px solid var(--border-light);
    text-align: left;
}

.line-items-table th {
    color: var(--text-secondary);
    font-size: 12px;
    font-weight: 600;
}

.line-items-table input,
.line-items-table select {
    width: 100%;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-sm);
    background: var(--bg-surface);
    color: var(--text-primary);
    padding: 8px 10px;
}

.line-items-table .line-qty,
.line-items-table .line-price,
.line-items-table .line-tax {
    min-width: 0;
    padding-left: 8px;
    padding-right: 8px;
}

.line-items-table .line-unit-code {
    min-width: 128px;
}

.line-total {
    display: inline-block;
    min-width: 120px;
}

.line-product-wrap {
    display: none;
    margin-bottom: 6px;
}

.line-product-search {
    margin-top: 6px;
    display: grid;
    gap: 6px;
}

.line-product-search-results {
    border: 1px solid var(--border-light);
    border-radius: 10px;
    background: var(--bg-surface);
    max-height: 180px;
    overflow-y: auto;
}

.line-product-search-item {
    width: 100%;
    text-align: left;
    padding: 8px 10px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 12px;
}

.line-product-search-item:hover {
    background: var(--accent-soft);
}

.line-product-search-empty {
    padding: 8px 10px;
    font-size: 12px;
    color: var(--text-secondary);
}

.invoice-type-product .line-product-wrap {
    display: block;
}

.invoice-type-product .line-description {
    display: none;
}

.invoice-type-service .line-product-wrap {
    display: none;
}

.invoice-type-service .line-description {
    display: block;
}

.line-total {
    font-weight: 600;
}

.remove-line-btn {
    border: 1px solid var(--border-light);
    background: var(--bg-surface);
    color: var(--danger);
    width: 30px;
    height: 30px;
    border-radius: 8px;
    cursor: pointer;
}

.remove-line-btn:hover {
    background: rgba(239, 68, 68, 0.1);
}

.summary-card {
    background: linear-gradient(180deg, var(--bg-surface) 0%, rgba(15, 157, 88, 0.07) 100%);
}

.summary-group {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 16px;
}

.summary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.summary-row strong {
    font-size: 16px;
}

.summary-inline {
    align-items: flex-start;
    flex-direction: column;
}

.summary-inline-controls {
    display: grid;
    grid-template-columns: 100px 1fr;
    gap: 8px;
    width: 100%;
}

.summary-inline-controls select,
.summary-inline-controls input,
#deposit-value {
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    background: var(--bg-surface);
    color: var(--text-primary);
    padding: 8px 10px;
    font: inherit;
}

#deposit-value {
    width: 140px;
}

.summary-total,
.summary-balance {
    border-top: 1px solid var(--border-light);
    padding-top: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.summary-total strong {
    font-size: 22px;
}

.summary-balance strong {
    color: var(--warning);
}

.side-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.side-actions .btn {
    width: 100%;
}

@media (max-width: 1300px) {
    .invoice-create-layout {
        grid-template-columns: 1fr;
    }

    .invoice-side-column {
        position: static;
    }
}

@media (max-width: 900px) {
    .form-grid-three {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 680px) {
    .invoice-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .form-grid-two,
    .form-grid-three {
        grid-template-columns: 1fr;
    }

    #deposit-value {
        width: 100%;
    }
}
</style>

<script>
(() => {
    const form = document.getElementById('invoice-form');
    if (!form) {
        return;
    }
    const lineItemsBody = document.getElementById('line-items-body');
    const addLineBtn = document.getElementById('add-line-btn');
    const invoiceTypeSelect = document.getElementById('invoice-type-select');
    const currencySelect = document.getElementById('invoice-currency');
    const issueDateInput = form.querySelector('input[name="issue_date"]');
    const dueDateInput = form.querySelector('input[name="due_date"]');
    const paymentMethodSelect = form.querySelector('select[name="payment_method"]');
    const clientLookupInput = document.getElementById('client-lookup-input');
    const clientNameInput = document.getElementById('client-name-hidden') || form.querySelector('input[name="client_name"]');
    const clientEmailInput = form.querySelector('input[name="client_email"]');
    const clientPhoneHiddenInput = document.getElementById('client-phone-hidden');
    const clientSuggestions = document.getElementById('client-suggestions');
    const clientTypeInputs = Array.from(form.querySelectorAll('input[name="client_type"]'));
    const clientKnownFields = Array.from(form.querySelectorAll('.client-known-field'));
    const discountType = document.getElementById('discount-type');
    const discountValue = document.getElementById('discount-value');
    const depositValue = document.getElementById('deposit-value');
    const statusField = document.getElementById('invoice-status-field');
    const saveDraftBtn = document.getElementById('save-draft-btn');
    const submitInvoiceBtn = document.getElementById('submit-invoice-btn');
    const previewBtn = document.getElementById('preview-btn');
    const summarySubtotal = document.getElementById('summary-subtotal');
    const summaryTax = document.getElementById('summary-tax');
    const summaryTotal = document.getElementById('summary-total');
    const summaryBalance = document.getElementById('summary-balance');
    const summarySubtotalInput = document.getElementById('summary-subtotal-input');
    const summaryTaxInput = document.getElementById('summary-tax-input');
    const summaryTotalInput = document.getElementById('summary-total-input');
    const defaultTaxRate = <?= json_encode((float) $defaultTaxRate, JSON_UNESCAPED_UNICODE) ?>;
    const isEditMode = <?= $isEditMode ? 'true' : 'false' ?>;
    const productCatalog = <?= json_encode(array_map(static function (array $product): array {
        return [
            'id' => (int) ($product['id'] ?? 0),
            'name' => (string) ($product['name'] ?? ''),
            'sku' => (string) ($product['sku'] ?? ''),
            'brand' => (string) ($product['brand'] ?? ''),
            'supplier' => (string) ($product['supplier'] ?? ''),
            'dosage' => (string) ($product['dosage'] ?? ''),
            'forme' => (string) ($product['forme'] ?? ''),
            'presentation' => (string) ($product['presentation'] ?? ''),
            'unit' => (string) ($product['unit'] ?? 'unite'),
            'unit_options' => array_values(array_map(static function (array $unitOption): array {
                return [
                    'unit_code' => (string) ($unitOption['unit_code'] ?? ''),
                    'unit_label' => (string) ($unitOption['unit_label'] ?? ''),
                    'factor_to_base' => round((float) ($unitOption['factor_to_base'] ?? 1), 6),
                    'is_base' => (bool) ($unitOption['is_base'] ?? false),
                ];
            }, (array) ($product['unit_options'] ?? []))),
            'purchase_price' => round((float) ($product['purchase_price'] ?? 0), 2),
            'sale_price' => round((float) ($product['sale_price'] ?? 0), 2),
            'quantity' => round((float) ($product['quantity'] ?? 0), 6),
        ];
    }, $invoiceProducts), JSON_UNESCAPED_UNICODE) ?>;
    const productById = new Map(productCatalog.map((product) => [String(product.id), product]));
    const SEARCH_OPTION_VALUE = '__search__';
    const SALES_STORAGE_KEY = 'sales.smart.client_names';
    let dueDateManuallyEdited = isEditMode;
    let clientEmailManuallyEdited = false;
    let lastKnownName = String(clientNameInput?.value || '').trim();
    let lastKnownEmail = String(clientEmailInput?.value || '').trim();
    let lastKnownLookup = String(clientLookupInput?.value || '').trim();
    let activeClient = null;
    let clientLookupTimer = null;
    let clientLookupAbortController = null;

    // Nouvelle fonction pour masquer/afficher le champ acompte
    // Modifiez la fonction toggleDepositFieldVisibility() existante comme ceci :

    const toggleDepositFieldVisibility = () => {
        const depositField = document.getElementById('deposit-value');
        const depositRow = depositField?.closest('.summary-row');
        const balanceRow = document.querySelector('.summary-balance');
        
        if (isAnonymousClient()) {
            // Si client inconnu, masquer le champ acompte et le reste à payer
            if (depositRow) {
                depositRow.style.display = 'none';
            }
            if (balanceRow) {
                balanceRow.style.display = 'none';
            }
            // Optionnel : remettre à zéro la valeur quand on masque
            if (depositField) {
                depositField.value = '0';
            }
        } else {
            // Si client connu, afficher le champ acompte et le reste à payer
            if (depositRow) {
                depositRow.style.display = 'flex';
            }
            if (balanceRow) {
                balanceRow.style.display = 'flex';
            }
        }
        
        // Recalculer le résumé après modification
        if (typeof computeSummary === 'function') {
            computeSummary();
        }
    };

    const notify = (message, type = 'info') => {
        if (window.Symphony && typeof window.Symphony.showNotification === 'function') {
            window.Symphony.showNotification(message, type);
            return;
        }

        window.alert(message);
    };

    const parseNumber = (value) => {
        const number = parseFloat(String(value).replace(',', '.'));
        return Number.isFinite(number) ? number : 0;
    };

    const formatNumberCompact = (value, maxDecimals = 2) => {
        const numeric = parseNumber(value);
        return new Intl.NumberFormat('fr-FR', {
            minimumFractionDigits: 0,
            maximumFractionDigits: maxDecimals,
        }).format(numeric).replace(/\u202f|\u00a0/g, ' ');
    };

    const formatMoney = (amount) => {
        const currency = currencySelect.value || 'USD';
        return `${formatNumberCompact(amount, 2)} ${currency}`;
    };

    const setInputValue = (input, value) => {
        if (!input) {
            return;
        }
        input.dataset.autoSet = '1';
        input.value = value;
        delete input.dataset.autoSet;
    };

    const wasUserEdited = (input) => input && input.dataset.autoSet !== '1';

    const readStringList = (key) => {
        try {
            const parsed = JSON.parse(localStorage.getItem(key) || '[]');
            if (!Array.isArray(parsed)) {
                return [];
            }
            return parsed
                .map((item) => String(item || '').trim())
                .filter((item) => item !== '');
        } catch {
            return [];
        }
    };

    const writeStringList = (key, values) => {
        localStorage.setItem(key, JSON.stringify(values.slice(0, 12)));
    };

    const pushToRecentList = (key, value) => {
        const normalized = String(value || '').trim();
        if (normalized === '') {
            return;
        }
        const current = readStringList(key);
        const next = [normalized, ...current.filter((item) => item.toLowerCase() !== normalized.toLowerCase())];
        writeStringList(key, next);
    };

    const addDaysToDate = (inputDate, days) => {
        const date = new Date(`${inputDate}T00:00:00`);
        if (Number.isNaN(date.getTime())) {
            return '';
        }
        date.setDate(date.getDate() + days);
        return date.toISOString().slice(0, 10);
    };

    const escapeHtml = (value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const normalizePhone = (value) => String(value || '').replace(/[^0-9]+/g, '');

    const buildClientLabel = (name, phone) => {
        const safeName = String(name || '').trim();
        const safePhone = String(phone || '').trim();
        if (safePhone && safeName) {
            return `${safePhone} - ${safeName}`;
        }
        return safeName || safePhone || '';
    };

    const extractNameFromLookup = (value) => {
        const withoutDigits = String(value || '').replace(/[0-9+]+/g, ' ');
        return withoutDigits.replace(/\s+/g, ' ').trim();
    };

    const isLikelyPhone = (value) => {
        const raw = String(value || '').trim();
        if (raw === '') {
            return false;
        }
        const hasLetters = /[a-z]/i.test(raw);
        const digits = normalizePhone(raw);
        return !hasLetters && digits.length >= 6;
    };

    const syncClientLookupHidden = () => {
        if (!clientNameInput || !clientPhoneHiddenInput) {
            return '';
        }
        if (isAnonymousClient()) {
            clientNameInput.value = 'Client anonyme';
            clientPhoneHiddenInput.value = '';
            return '';
        }

        const rawLookup = String(clientLookupInput?.value || '').trim();
        if (activeClient && (activeClient.name || activeClient.phone)) {
            clientNameInput.value = String(activeClient.name || '').trim();
            clientPhoneHiddenInput.value = normalizePhone(activeClient.phone || '');
            return rawLookup;
        }

        if (rawLookup === '') {
            clientNameInput.value = '';
            clientPhoneHiddenInput.value = '';
            return '';
        }

        const digits = normalizePhone(rawLookup);
        if (isLikelyPhone(rawLookup)) {
            clientPhoneHiddenInput.value = digits;
            clientNameInput.value = rawLookup;
            return rawLookup;
        }

        const extractedName = extractNameFromLookup(rawLookup);
        clientNameInput.value = extractedName !== '' ? extractedName : rawLookup;
        clientPhoneHiddenInput.value = digits.length >= 6 ? digits : '';
        return rawLookup;
    };

    const hideClientSuggestions = () => {
        if (!clientSuggestions) {
            return;
        }
        clientSuggestions.hidden = true;
        clientSuggestions.innerHTML = '';
    };

    const renderClientSuggestions = (items) => {
        if (!clientSuggestions) {
            return;
        }
        if (!Array.isArray(items) || items.length === 0) {
            clientSuggestions.hidden = false;
            clientSuggestions.innerHTML = '<div class="client-suggestion-empty">Aucun client trouve.</div>';
            return;
        }
        clientSuggestions.hidden = false;
        clientSuggestions.innerHTML = items.map((item) => `
            <button
                type="button"
                class="client-suggestion-item"
                data-phone="${escapeHtml(String(item.phone || ''))}"
                data-name="${escapeHtml(String(item.name || ''))}"
                data-label="${escapeHtml(String(item.label || ''))}"
                data-debt-count="${escapeHtml(String(item.debt_count || 0))}"
                data-debt-total="${escapeHtml(String(item.debt_total || 0))}"
            >
                <span class="client-suggestion-name">${escapeHtml(String(item.label || buildClientLabel(item.name, item.phone) || 'Client'))}</span>
                <span class="client-suggestion-meta">${escapeHtml(String(item.debt_count || 0))} dette(s) · ${escapeHtml(formatMoney(item.debt_total || 0))}</span>
            </button>
        `).join('');
    };

    const applyClientSuggestion = (client) => {
        if (!client || isAnonymousClient()) {
            return;
        }
        activeClient = {
            name: String(client.name || '').trim(),
            phone: String(client.phone || '').trim(),
        };
        if (clientLookupInput) {
            const label = String(client.label || buildClientLabel(activeClient.name, activeClient.phone));
            setInputValue(clientLookupInput, label);
            lastKnownLookup = label;
        }
        if (clientNameInput) {
            setInputValue(clientNameInput, activeClient.name);
            lastKnownName = activeClient.name;
        }
        if (clientPhoneHiddenInput) {
            setInputValue(clientPhoneHiddenInput, normalizePhone(activeClient.phone));
        }
        hideClientSuggestions();
    };

    const fetchClientSuggestions = async (rawQuery) => {
        const query = String(rawQuery || '').trim();
        if (isAnonymousClient() || query === '') {
            hideClientSuggestions();
            return;
        }

        if (clientLookupAbortController) {
            clientLookupAbortController.abort();
        }

        clientLookupAbortController = new AbortController();
        try {
            const response = await fetch(`/api/clients/search?q=${encodeURIComponent(query)}&limit=6`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: clientLookupAbortController.signal,
            });
            if (!response.ok) {
                throw new Error('Client lookup failed');
            }
            const payload = await response.json();
            const items = Array.isArray(payload.items) ? payload.items : [];
            renderClientSuggestions(items);
        } catch (error) {
            if (error && error.name === 'AbortError') {
                return;
            }
            hideClientSuggestions();
        }
    };

    const inferClientEmail = () => '';
    const syncClientEmailFromName = () => {};

    const getClientType = () => {
        const selected = clientTypeInputs.find((input) => input.checked);
        return selected ? String(selected.value || 'known') : 'known';
    };

    const isAnonymousClient = () => getClientType() === 'anonymous';

    // Fonction applyClientType modifiée pour inclure toggleDepositFieldVisibility
    const applyClientType = () => {
        const anonymous = isAnonymousClient();
        if (anonymous) {
            activeClient = null;
        }
        if (clientLookupInput) {
            if (anonymous) {
                const currentLookup = String(clientLookupInput.value || '').trim();
                if (currentLookup !== '' && currentLookup.toLowerCase() !== 'client anonyme') {
                    lastKnownLookup = currentLookup;
                }
                setInputValue(clientLookupInput, 'Client anonyme');
                clientLookupInput.readOnly = true;
            } else {
                clientLookupInput.readOnly = false;
                if (String(clientLookupInput.value || '').trim().toLowerCase() === 'client anonyme') {
                    setInputValue(clientLookupInput, lastKnownLookup || '');
                }
            }
        }
        clientKnownFields.forEach((field) => {
            field.style.display = anonymous ? 'none' : '';
        });
        syncClientLookupHidden();
        if (anonymous) {
            hideClientSuggestions();
        }
        
        // Ajout de l'appel pour masquer/afficher le champ acompte
        toggleDepositFieldVisibility();
    };

    const getRows = () => Array.from(lineItemsBody.querySelectorAll('.line-item-row'));
    const isProductInvoice = () => invoiceTypeSelect.value === 'product';
    const composeProductDescription = (product) => {
        const chunks = [String(product?.name || '').trim()];
        const supplier = String(product?.supplier || '').trim();
        if (supplier !== '') {
            chunks.push(`- ${supplier}`);
        }
        const dosage = String(product?.dosage || '').trim();
        const forme = String(product?.forme || '').trim();
        const presentation = String(product?.presentation || '').trim();
        if (dosage !== '') {
            chunks.push(dosage);
        }
        if (forme !== '') {
            chunks.push(`- ${forme}`);
        }
        if (presentation !== '') {
            chunks.push(`(${presentation})`);
        }
        return chunks.filter((item) => item !== '').join(' ').replace(/\s+/g, ' ').trim();
    };

    const productOptionsHtml = () => {
        const options = [
            '<option value="">Choisir un produit...</option>',
            `<option value="${SEARCH_OPTION_VALUE}">Rechercher un produit...</option>`,
        ];
        productCatalog.forEach((product) => {
            const supplierSuffix = product.supplier ? ` - ${escapeHtml(product.supplier)}` : '';
            const label = `${escapeHtml(product.name)}${supplierSuffix}${product.sku ? ` (${escapeHtml(product.sku)})` : ''}`;
            options.push(
                `<option value="${product.id}" data-name="${escapeHtml(product.name)}" data-price="${Number(product.sale_price).toFixed(2)}" data-stock="${Number(product.quantity).toFixed(2)}">${label}</option>`
            );
        });
        return options.join('');
    };

    const buildProductSearchLabel = (product) => {
        const chunks = [String(product?.name || '').trim()];
        const supplier = String(product?.supplier || '').trim();
        if (supplier !== '') {
            chunks.push(`- ${supplier}`);
        }
        const meta = [product?.dosage, product?.forme, product?.presentation]
            .map((value) => String(value || '').trim())
            .filter((value) => value !== '');
        if (meta.length > 0) {
            chunks.push(meta.join(' '));
        }
        if (product?.sku) {
            chunks.push(`(${String(product.sku).trim()})`);
        }
        return chunks.filter((value) => value !== '').join(' ');
    };

    const matchesProductSearch = (product, term) => {
        if (!term) {
            return false;
        }
        const haystack = [
            product?.name,
            product?.sku,
            product?.brand,
            product?.supplier,
            product?.dosage,
            product?.forme,
            product?.presentation,
        ].join(' ').toLowerCase();
        return haystack.includes(term);
    };

    const renderSearchResults = (row, query) => {
        const resultsWrap = row.querySelector('.line-product-search-results');
        if (!resultsWrap) {
            return;
        }
        const term = String(query || '').trim().toLowerCase();
        if (term === '') {
            resultsWrap.innerHTML = '<div class="line-product-search-empty">Commencez a taper pour chercher un produit.</div>';
            return;
        }
        const matches = productCatalog.filter((product) => matchesProductSearch(product, term)).slice(0, 8);
        if (matches.length === 0) {
            resultsWrap.innerHTML = '<div class="line-product-search-empty">Aucun produit ne correspond.</div>';
            return;
        }
        resultsWrap.innerHTML = matches.map((product) => `
            <button type="button" class="line-product-search-item" data-product-id="${product.id}">
                ${escapeHtml(buildProductSearchLabel(product))}
            </button>
        `).join('');
    };

    const markRowManual = (row, key) => {
        row.dataset[key] = '1';
    };

    const isRowManual = (row, key) => row.dataset[key] === '1';

    const unitOptionsHtml = (product, selectedUnit = '') => {
        const options = ['<option value="">Unite...</option>'];
        const normalizedSelected = String(selectedUnit || '').toLowerCase();
        const unitOptions = Array.isArray(product?.unit_options) ? product.unit_options : [];

        unitOptions.forEach((unit) => {
            const code = String(unit.unit_code || '').toLowerCase();
            if (!code) {
                return;
            }
            const label = String(unit.unit_label || code);
            const factor = Number(unit.factor_to_base || 1);
            const suffix = unit.is_base ? ' (base)' : ` x${factor.toFixed(6).replace(/0+$/, '').replace(/\.$/, '')}`;
            const selected = code === normalizedSelected ? ' selected' : '';
            options.push(`<option value="${escapeHtml(code)}"${selected}>${escapeHtml(label)}${escapeHtml(suffix)}</option>`);
        });

        if (unitOptions.length === 0 && product?.unit) {
            const code = String(product.unit).toLowerCase();
            const selected = code === normalizedSelected ? ' selected' : '';
            options.push(`<option value="${escapeHtml(code)}"${selected}>${escapeHtml(code)} (base)</option>`);
        }

        return options.join('');
    };

    const getUnitFactorToBase = (product, unitCode) => {
        const normalizedUnitCode = String(unitCode || '').toLowerCase();
        if (!normalizedUnitCode) {
            return 1;
        }
        const unitOptions = Array.isArray(product?.unit_options) ? product.unit_options : [];
        const matched = unitOptions.find((item) => String(item.unit_code || '').toLowerCase() === normalizedUnitCode);
        const factor = Number(matched?.factor_to_base || 1);
        return Number.isFinite(factor) && factor > 0 ? factor : 1;
    };

    const getReservedStockBaseQty = (productId, currentRow = null) => {
        if (!productId) {
            return 0;
        }
        let reserved = 0;
        getRows().forEach((candidateRow) => {
            if (currentRow && candidateRow === currentRow) {
                return;
            }
            const candidateProductId = String(candidateRow.querySelector('.line-product-id')?.value || '');
            if (candidateProductId !== String(productId)) {
                return;
            }
            const product = productById.get(candidateProductId);
            if (!product) {
                return;
            }
            const qty = parseNumber(candidateRow.querySelector('.line-qty')?.value || '0');
            const unitCode = String(candidateRow.querySelector('.line-unit-code')?.value || '').toLowerCase();
            const factor = getUnitFactorToBase(product, unitCode || product.unit);
            reserved += qty * factor;
        });
        return reserved;
    };

    const syncRowDescription = (row) => {
        const descriptionInput = row.querySelector('.line-description');
        const descriptionHidden = row.querySelector('.line-description-hidden');
        const productSelect = row.querySelector('.line-product-id');
        const productHidden = row.querySelector('.line-product-hidden');
        const unitSelect = row.querySelector('.line-unit-code');
        const stockHint = row.querySelector('.line-stock-hint');
        const qtyInput = row.querySelector('.line-qty');

        if (isProductInvoice()) {
            const rawProductId = productSelect.value;
            const productId = rawProductId === SEARCH_OPTION_VALUE ? '' : rawProductId;
            const product = productById.get(productId) || null;
            const selectedUnit = String(unitSelect?.value || unitSelect?.dataset.selectedUnit || '').toLowerCase();
            productHidden.value = productId;
            descriptionInput.readOnly = false;
            if (product) {
                const smartDescription = composeProductDescription(product);
                if (!isRowManual(row, 'manualDescription') || String(descriptionInput.value || '').trim() === '') {
                    setInputValue(descriptionInput, smartDescription);
                }
                descriptionHidden.value = String(descriptionInput.value || '').trim();
                if (unitSelect) {
                    unitSelect.innerHTML = unitOptionsHtml(product, selectedUnit);
                    if (!unitSelect.value) {
                        const fallbackUnit = String(product.unit || 'unite').toLowerCase();
                        setInputValue(unitSelect, fallbackUnit);
                    }
                    unitSelect.dataset.selectedUnit = unitSelect.value;
                }
                const selectedUnitCode = String(unitSelect?.value || product.unit || '').toLowerCase();
                const reservedByOtherRowsInBase = getReservedStockBaseQty(productId, row);
                const availableInBase = Math.max(Number(product.quantity) - reservedByOtherRowsInBase, 0);
                const selectedUnitFactor = getUnitFactorToBase(product, selectedUnitCode);
                const availableInSelectedUnit = selectedUnitFactor > 0
                    ? (availableInBase / selectedUnitFactor)
                    : availableInBase;
                stockHint.textContent = `Stock actuel: ${formatNumberCompact(availableInSelectedUnit, 2)}`;
                const priceInput = row.querySelector('.line-price');
                const taxInput = row.querySelector('.line-tax');
                const currentPrice = parseNumber(priceInput?.value || '0');
                if (priceInput && (!isRowManual(row, 'manualPrice') || currentPrice <= 0)) {
                    const unitFactorForPrice = getUnitFactorToBase(product, selectedUnitCode || product.unit);
                    const factorSafe = Number.isFinite(unitFactorForPrice) && unitFactorForPrice > 0 ? unitFactorForPrice : 1;
                    const salePrice = Number(product.sale_price || 0) * factorSafe;
                    const purchasePrice = Number(product.purchase_price || 0) * factorSafe;
                    const safePrice = purchasePrice > 0 ? Math.max(salePrice, purchasePrice) : salePrice;
                    setInputValue(priceInput, Number(safePrice).toFixed(2));
                }
                if (qtyInput && !isRowManual(row, 'manualQty') && String(qtyInput.value || '').trim() === '') {
                    setInputValue(qtyInput, '1');
                }
                if (taxInput && !isRowManual(row, 'manualTax')) {
                    const taxCandidate = Number(defaultTaxRate).toFixed(0);
                    const hasCandidate = !!taxInput.querySelector(`option[value="${taxCandidate}"]`);
                    setInputValue(taxInput, hasCandidate ? taxCandidate : '0');
                }
            } else {
                if (!isRowManual(row, 'manualDescription')) {
                    setInputValue(descriptionInput, '');
                }
                descriptionHidden.value = String(descriptionInput.value || '').trim();
                stockHint.textContent = '';
                if (unitSelect) {
                    unitSelect.innerHTML = '<option value="">Unite...</option>';
                    unitSelect.dataset.selectedUnit = '';
                }
            }
            return;
        }

        productHidden.value = '';
        productSelect.value = '';
        stockHint.textContent = '';
        descriptionInput.readOnly = false;
        descriptionHidden.value = String(descriptionInput.value || '').trim();
        if (unitSelect) {
            unitSelect.innerHTML = '<option value="">Unite...</option>';
            unitSelect.value = '';
            unitSelect.dataset.selectedUnit = '';
        }
    };

    const updateRow = (row) => {
        syncRowDescription(row);
        const qty = Math.max(parseNumber(row.querySelector('.line-qty').value), 0);
        const price = parseNumber(row.querySelector('.line-price').value);
        const taxRate = parseNumber(row.querySelector('.line-tax').value);
        const subtotal = qty * price;
        const tax = subtotal * (taxRate / 100);
        const total = subtotal + tax;

        row.dataset.subtotal = String(subtotal);
        row.dataset.tax = String(tax);
        row.querySelector('[data-line-total]').textContent = formatMoney(total);
    };

    const computeSummary = () => {
        let subtotal = 0;
        let tax = 0;

        getRows().forEach((row) => {
            updateRow(row);
            subtotal += parseNumber(row.dataset.subtotal);
            tax += parseNumber(row.dataset.tax);
        });

        const discountRaw = parseNumber(discountValue.value);
        let discount = discountRaw;

        if (discountType.value === 'percent') {
            discount = (subtotal + tax) * (discountRaw / 100);
        }

        const grossTotal = subtotal + tax;
        discount = Math.min(discount, grossTotal);

        const total = Math.max(grossTotal - discount, 0);
        const deposit = parseNumber(depositValue.value);
        const balance = Math.max(total - deposit, 0);

        summarySubtotal.textContent = formatMoney(subtotal);
        summaryTax.textContent = formatMoney(tax);
        summaryTotal.textContent = formatMoney(total);
        summaryBalance.textContent = formatMoney(balance);

        summarySubtotalInput.value = subtotal.toFixed(2);
        summaryTaxInput.value = tax.toFixed(2);
        summaryTotalInput.value = total.toFixed(2);
    };

    const toggleSearchUi = (row, isVisible) => {
        const searchWrap = row.querySelector('.line-product-search');
        if (!searchWrap) {
            return;
        }
        searchWrap.style.display = isVisible ? 'grid' : 'none';
        if (isVisible) {
            const input = searchWrap.querySelector('.line-product-search-input');
            if (input) {
                input.focus();
                input.select();
            }
        }
    };

    const setupSearchUi = (row) => {
        if (row.dataset.searchReady === '1') {
            return;
        }
        row.dataset.searchReady = '1';
        const productSelect = row.querySelector('.line-product-id');
        const searchWrap = row.querySelector('.line-product-search');
        const searchInput = row.querySelector('.line-product-search-input');
        const resultsWrap = row.querySelector('.line-product-search-results');

        if (!productSelect || !searchWrap || !searchInput || !resultsWrap) {
            return;
        }

        const refreshVisibility = () => {
            const isSearchMode = productSelect.value === SEARCH_OPTION_VALUE;
            toggleSearchUi(row, isSearchMode);
            if (isSearchMode) {
                renderSearchResults(row, searchInput.value);
            }
        };

        searchInput.addEventListener('input', () => {
            renderSearchResults(row, searchInput.value);
        });

        resultsWrap.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            const button = target.closest('.line-product-search-item');
            if (!button) {
                return;
            }
            const productId = String(button.dataset.productId || '');
            if (productId !== '') {
                setInputValue(productSelect, productId);
                toggleSearchUi(row, false);
                searchInput.value = '';
                renderSearchResults(row, '');
                computeSummary();
            }
        });

        productSelect.addEventListener('change', refreshVisibility);
        productSelect.addEventListener('input', refreshVisibility);
        refreshVisibility();
    };

    const attachRowEvents = (row) => {
        setupSearchUi(row);
        row.querySelectorAll('.js-line-input').forEach((input) => {
            input.addEventListener('input', computeSummary);
            input.addEventListener('change', computeSummary);
        });

        const descriptionInput = row.querySelector('.line-description');
        if (descriptionInput) {
            descriptionInput.addEventListener('input', () => {
                if (wasUserEdited(descriptionInput)) {
                    markRowManual(row, 'manualDescription');
                }
            });
            descriptionInput.addEventListener('change', () => {
                if (wasUserEdited(descriptionInput)) {
                    markRowManual(row, 'manualDescription');
                }
            });
        }

        const priceInput = row.querySelector('.line-price');
        if (priceInput) {
            priceInput.addEventListener('input', () => {
                if (wasUserEdited(priceInput)) {
                    markRowManual(row, 'manualPrice');
                }
            });
            priceInput.addEventListener('change', () => {
                if (wasUserEdited(priceInput)) {
                    markRowManual(row, 'manualPrice');
                }
            });
        }

        const qtyInput = row.querySelector('.line-qty');
        if (qtyInput) {
            qtyInput.addEventListener('input', () => {
                if (wasUserEdited(qtyInput)) {
                    markRowManual(row, 'manualQty');
                }
                const currentValue = parseNumber(qtyInput.value || '0');
                if (currentValue < 0) {
                    setInputValue(qtyInput, '0');
                }
            });
            qtyInput.addEventListener('change', () => {
                if (wasUserEdited(qtyInput)) {
                    markRowManual(row, 'manualQty');
                }
                const currentValue = parseNumber(qtyInput.value || '0');
                if (currentValue <= 0) {
                    setInputValue(qtyInput, '1');
                }
            });
            qtyInput.addEventListener('blur', () => {
                const currentValue = parseNumber(qtyInput.value || '0');
                if (currentValue <= 0) {
                    setInputValue(qtyInput, '1');
                }
            });
        }

        const taxInput = row.querySelector('.line-tax');
        if (taxInput) {
            taxInput.addEventListener('input', () => {
                if (wasUserEdited(taxInput)) {
                    markRowManual(row, 'manualTax');
                }
            });
            taxInput.addEventListener('change', () => {
                if (wasUserEdited(taxInput)) {
                    markRowManual(row, 'manualTax');
                }
            });
        }

        const unitSelect = row.querySelector('.line-unit-code');
        if (unitSelect) {
            unitSelect.addEventListener('change', () => {
                unitSelect.dataset.selectedUnit = unitSelect.value || '';
                syncRowDescription(row);
                computeSummary();
            });
        }

        const removeBtn = row.querySelector('.remove-line-btn');
        removeBtn.addEventListener('click', () => {
            if (getRows().length <= 1) {
                notify('Au moins une ligne est requise.', 'warning');
                return;
            }

            row.remove();
            computeSummary();
        });
    };

    const createRowHtml = () => `
        <tr class="line-item-row">
            <td>
                <div class="line-product-wrap">
                    <select class="line-product-id js-line-input">
                        ${productOptionsHtml()}
                    </select>
                    <small class="line-stock-hint text-secondary"></small>
                    <div class="line-product-search" style="display:none;">
                        <input type="text" class="line-product-search-input" placeholder="Rechercher un produit...">
                        <div class="line-product-search-results"></div>
                    </div>
                </div>
                <input type="text" class="line-description js-line-input" placeholder="Article / service">
                <input type="hidden" name="line_description[]" class="line-description-hidden" value="">
                <input type="hidden" name="line_product_id[]" class="line-product-hidden" value="">
            </td>
            <td><input type="number" name="line_qty[]" class="line-qty js-line-input" min="0.000001" step="0.000001" value="1" required></td>
            <td>
                <select name="line_unit_code[]" class="line-unit-code js-line-input">
                    <option value="">Unite...</option>
                </select>
            </td>
            <td><input type="number" name="line_price[]" class="line-price js-line-input" min="0" step="0.01" value="0" required readonly></td>
            <td>
                <select name="line_tax[]" class="line-tax js-line-input">
                    <option value="0" ${defaultTaxRate === 0 ? 'selected' : ''}>0</option>
                    <option value="5" ${defaultTaxRate === 5 ? 'selected' : ''}>5</option>
                    <option value="10" ${defaultTaxRate === 10 ? 'selected' : ''}>10</option>
                    <option value="16" ${defaultTaxRate === 16 ? 'selected' : ''}>16</option>
                </select>
            </td>
            <td><span class="line-total" data-line-total>0.00</span></td>
            <td><button type="button" class="remove-line-btn" aria-label="Supprimer ligne">x</button></td>
        </tr>
    `;

    const setInvoiceTypeUi = () => {
        const isProduct = isProductInvoice();
        form.classList.toggle('invoice-type-product', isProduct);
        form.classList.toggle('invoice-type-service', !isProduct);
        getRows().forEach((row) => {
            syncRowDescription(row);
        });
        computeSummary();
    };

    addLineBtn.addEventListener('click', () => {
        lineItemsBody.insertAdjacentHTML('beforeend', createRowHtml());
        attachRowEvents(getRows()[getRows().length - 1]);
        setInvoiceTypeUi();
    });

    invoiceTypeSelect.addEventListener('change', setInvoiceTypeUi);
    discountType.addEventListener('change', computeSummary);
    discountValue.addEventListener('input', computeSummary);
    depositValue.addEventListener('input', computeSummary);
    if (currencySelect) {
        setInputValue(currencySelect, 'USD');
        currencySelect.addEventListener('change', computeSummary);
    }

    if (saveDraftBtn) {
        saveDraftBtn.addEventListener('click', () => {
            statusField.value = 'brouillon';
            form.requestSubmit();
        });
    }

    submitInvoiceBtn.addEventListener('click', () => {
        if (statusField.value === 'draft' || statusField.value === 'brouillon') {
            statusField.value = 'envoyee';
        }
    });

    form.addEventListener('submit', (event) => {
        let isValid = true;
        syncClientLookupHidden();
        if (!isAnonymousClient()) {
            const clientNameValue = String(clientNameInput?.value || '').trim();
            const clientPhoneValue = String(clientPhoneHiddenInput?.value || '').trim();
            if (clientNameValue === '' && clientPhoneValue === '') {
                event.preventDefault();
                notify('Renseignez un nom ou un telephone pour le client.', 'warning');
                return;
            }
        }
        getRows().forEach((row) => {
            syncRowDescription(row);
            const qtyValue = parseNumber(row.querySelector('.line-qty')?.value || '0');
            if (qtyValue <= 0) {
                isValid = false;
            }
            const description = row.querySelector('.line-description-hidden').value.trim();
            if (description === '') {
                isValid = false;
            }
            if (isProductInvoice()) {
                const productId = row.querySelector('.line-product-hidden').value;
                const unitCode = row.querySelector('.line-unit-code')?.value || '';
                if (!productId) {
                    isValid = false;
                }
                if (!unitCode) {
                    isValid = false;
                }
            }
        });

        if (!isValid) {
            event.preventDefault();
            notify('Completez toutes les lignes de vente avant de continuer.', 'warning');
            return;
        }

        if (paymentMethodSelect) {
            localStorage.setItem('sales.smart.last_payment_method', String(paymentMethodSelect.value || ''));
        }
        if (clientLookupInput && !isAnonymousClient()) {
            pushToRecentList(SALES_STORAGE_KEY, clientLookupInput.value);
        }
    });

    const buildPreviewHtml = () => {
        const invoiceNumber = String(form.querySelector('input[name="invoice_number"]')?.value || '').trim();
        const issueDate = String(form.querySelector('input[name="issue_date"]')?.value || '').trim();
        const issueTime = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        const dueDate = String(form.querySelector('input[name="due_date"]')?.value || '').trim();
        const customerName = String(clientNameInput?.value || '').trim() || 'Client';
        const customerEmail = String(clientEmailInput?.value || '').trim() || '-';
        const currency = String(currencySelect?.value || 'USD');
        const paymentMethod = String(paymentMethodSelect?.value || '-');
        const rowsHtml = getRows().map((row) => {
            syncRowDescription(row);
            const description = String(row.querySelector('.line-description-hidden')?.value || '').trim() || '-';
            const qty = parseNumber(row.querySelector('.line-qty')?.value || '0');
            const unitCode = String(row.querySelector('.line-unit-code')?.value || '-').toUpperCase();
            const price = parseNumber(row.querySelector('.line-price')?.value || '0');
            const taxRate = parseNumber(row.querySelector('.line-tax')?.value || '0');
            const rowSubtotal = qty * price;
            const rowTax = rowSubtotal * (taxRate / 100);
            const rowTotal = rowSubtotal + rowTax;
            return `
                <tr>
                    <td>${escapeHtml(description)}</td>
                    <td style="text-align:right;">${escapeHtml(formatNumberCompact(qty, 6))}</td>
                    <td style="text-align:center;">${escapeHtml(unitCode)}</td>
                    <td style="text-align:right;">${escapeHtml(formatMoney(price))}</td>
                    <td style="text-align:right;">${escapeHtml(formatNumberCompact(taxRate, 2))}%</td>
                    <td style="text-align:right;">${escapeHtml(formatMoney(rowTotal))}</td>
                </tr>
            `;
        }).join('');

        return `<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Apercu vente ${escapeHtml(invoiceNumber)}</title>
<style>
body{font-family:Inter,Arial,sans-serif;background:#f6f8fb;color:#0f172a;padding:24px;}
.sheet{max-width:980px;margin:0 auto;background:#fff;border-radius:14px;padding:24px;box-shadow:0 8px 24px rgba(15,23,42,.08);}
h1{margin:0 0 10px;font-size:24px;}
.meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 18px;margin:14px 0 18px;}
.meta div{font-size:14px;color:#334155;}
table{width:100%;border-collapse:collapse;margin-top:8px;}
th,td{border-bottom:1px solid #e2e8f0;padding:10px 8px;font-size:13px;}
th{background:#f8fafc;text-align:left;color:#475569;}
.totals{margin-top:18px;display:grid;gap:8px;max-width:320px;margin-left:auto;}
.totals div{display:flex;justify-content:space-between;font-size:14px;}
.totals strong{font-size:18px;color:#0f9d58;}
</style>
</head>
<body>
    <div class="sheet">
        <h1>Apercu de vente ${escapeHtml(invoiceNumber)}</h1>
        <div class="meta">
            <div><strong>Client:</strong> ${escapeHtml(customerName)}</div>
            <div><strong>Email:</strong> ${escapeHtml(customerEmail)}</div>
            <div><strong>Date emission:</strong> ${escapeHtml(issueDate || '-')}</div>
            <div><strong>Heure emission:</strong> ${escapeHtml(issueTime || '-')}</div>
            <div><strong>Date echeance:</strong> ${escapeHtml(dueDate || '-')}</div>
            <div><strong>Mode paiement:</strong> ${escapeHtml(paymentMethod)}</div>
            <div><strong>Devise:</strong> ${escapeHtml(currency)}</div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th style="text-align:right;">Quantite</th>
                    <th style="text-align:center;">Unite</th>
                    <th style="text-align:right;">Prix unit.</th>
                    <th style="text-align:right;">TVA</th>
                    <th style="text-align:right;">Total ligne</th>
                </tr>
            </thead>
            <tbody>${rowsHtml}</tbody>
        </table>
        <div class="totals">
            <div><span>Sous-total</span><span>${escapeHtml(summarySubtotal.textContent || '0.00')}</span></div>
            <div><span>TVA</span><span>${escapeHtml(summaryTax.textContent || '0.00')}</span></div>
            <div><strong>Total</strong><strong>${escapeHtml(summaryTotal.textContent || '0.00')}</strong></div>
            <div><span>Reste a payer</span><span>${escapeHtml(summaryBalance.textContent || '0.00')}</span></div>
        </div>
    </div>
</body>
</html>`;
    };

    previewBtn.addEventListener('click', () => {
        computeSummary();
        const previewWindow = window.open('', '_blank');
        if (!previewWindow) {
            notify('Apercu bloque par le navigateur. Autorisez les popups puis reessayez.', 'warning');
            return;
        }
        previewWindow.document.open();
        previewWindow.document.write(buildPreviewHtml());
        previewWindow.document.close();
    });

    const syncDueDateFromIssueDate = () => {
        if (!issueDateInput || !dueDateInput || dueDateManuallyEdited) {
            return;
        }
        const issueDate = String(issueDateInput.value || '').trim();
        if (issueDate === '') {
            return;
        }
        const autoDueDate = addDaysToDate(issueDate, 15);
        if (autoDueDate !== '') {
            setInputValue(dueDateInput, autoDueDate);
        }
    };

    if (!isEditMode) {
        if (paymentMethodSelect) {
            const lastPaymentMethod = String(localStorage.getItem('sales.smart.last_payment_method') || '').trim();
            if (lastPaymentMethod !== '' && paymentMethodSelect.querySelector(`option[value="${lastPaymentMethod}"]`)) {
                setInputValue(paymentMethodSelect, lastPaymentMethod);
            }
        }
        if (clientLookupInput && String(clientLookupInput.value || '').trim() === '') {
            const recentClients = readStringList(SALES_STORAGE_KEY);
            if (recentClients.length > 0) {
                setInputValue(clientLookupInput, recentClients[0]);
            }
        }
    }

    if (clientLookupInput) {
        clientLookupInput.addEventListener('input', () => {
            activeClient = null;
            lastKnownLookup = String(clientLookupInput.value || '').trim();
            syncClientLookupHidden();
            hideClientSuggestions();
            if (clientLookupTimer) {
                window.clearTimeout(clientLookupTimer);
            }
            clientLookupTimer = window.setTimeout(() => {
                fetchClientSuggestions(String(clientLookupInput.value || ''));
            }, 180);
        });
        clientLookupInput.addEventListener('change', () => {
            activeClient = null;
            syncClientLookupHidden();
            fetchClientSuggestions(String(clientLookupInput.value || ''));
        });
        clientLookupInput.addEventListener('focus', () => {
            const current = String(clientLookupInput.value || '').trim();
            if (current !== '') {
                fetchClientSuggestions(current);
            }
        });
    }

    if (clientEmailInput) {
        const initialInferredEmail = inferClientEmail(clientNameInput ? clientNameInput.value : '');
        const initialEmail = String(clientEmailInput.value || '').trim().toLowerCase();
        if (initialEmail !== '' && initialEmail === initialInferredEmail.toLowerCase()) {
            clientEmailInput.dataset.generatedFromName = '1';
        } else if (initialEmail !== '') {
            clientEmailManuallyEdited = true;
        }

        const syncEmailManualState = () => {
            if (!wasUserEdited(clientEmailInput)) {
                return;
            }
            const currentEmail = String(clientEmailInput.value || '').trim().toLowerCase();
            const inferredEmail = inferClientEmail(clientNameInput ? clientNameInput.value : '').toLowerCase();
            if (currentEmail === '') {
                clientEmailManuallyEdited = false;
                delete clientEmailInput.dataset.generatedFromName;
                return;
            }
            if (inferredEmail !== '' && currentEmail === inferredEmail) {
                clientEmailManuallyEdited = false;
                clientEmailInput.dataset.generatedFromName = '1';
                return;
            }
            clientEmailManuallyEdited = true;
            delete clientEmailInput.dataset.generatedFromName;
        };

        clientEmailInput.addEventListener('input', syncEmailManualState);
        clientEmailInput.addEventListener('change', syncEmailManualState);
    }

    if (clientTypeInputs.length > 0) {
        clientTypeInputs.forEach((input) => {
            input.addEventListener('change', () => {
                applyClientType();
                syncClientEmailFromName();
            });
            // Ajouter un événement change supplémentaire pour la visibilité du champ acompte
            input.addEventListener('change', toggleDepositFieldVisibility);
        });
        applyClientType();
    }

    if (dueDateInput) {
        dueDateInput.addEventListener('input', () => {
            if (wasUserEdited(dueDateInput)) {
                dueDateManuallyEdited = true;
            }
        });
        dueDateInput.addEventListener('change', () => {
            if (wasUserEdited(dueDateInput)) {
                dueDateManuallyEdited = true;
            }
        });
    }
    if (issueDateInput) {
        issueDateInput.addEventListener('input', syncDueDateFromIssueDate);
        issueDateInput.addEventListener('change', syncDueDateFromIssueDate);
    }
    syncDueDateFromIssueDate();
    syncClientEmailFromName();
    syncClientLookupHidden();

    if (clientSuggestions) {
        clientSuggestions.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            const button = target.closest('.client-suggestion-item');
            if (!button) {
                return;
            }
            applyClientSuggestion({
                name: String(button.dataset.name || ''),
                phone: String(button.dataset.phone || ''),
                label: String(button.dataset.label || ''),
                debt_count: parseNumber(button.dataset.debtCount || '0'),
                debt_total: parseNumber(button.dataset.debtTotal || '0'),
            });
        });
    }

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Node)) {
            return;
        }
        if (clientSuggestions && !clientSuggestions.contains(target) && !clientLookupInput?.contains(target)) {
            hideClientSuggestions();
        }
    });

    if (isEditMode) {
        getRows().forEach((row) => {
            const desc = String(row.querySelector('.line-description')?.value || '').trim();
            const qty = parseNumber(row.querySelector('.line-qty')?.value || '0');
            const price = parseNumber(row.querySelector('.line-price')?.value || '0');
            const tax = parseNumber(row.querySelector('.line-tax')?.value || '');
            if (desc !== '') {
                row.dataset.manualDescription = '1';
            }
            if (qty > 0) {
                row.dataset.manualQty = '1';
            }
            if (price > 0) {
                row.dataset.manualPrice = '1';
            }
            if (Number.isFinite(tax) && tax >= 0) {
                row.dataset.manualTax = '1';
            }
        });
    }

    getRows().forEach(attachRowEvents);
    setInvoiceTypeUi();
    
    // Appeler la fonction au chargement initial pour appliquer l'état correct
    setTimeout(() => {
        toggleDepositFieldVisibility();
    }, 100);
})();
</script>