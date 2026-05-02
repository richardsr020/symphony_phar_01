<?php
$summary = $summary ?? [
    'product_count' => 0,
    'total_quantity' => 0,
    'stock_value' => 0,
    'low_stock_count' => 0,
    'expired_lot_count' => 0,
    'expired_quantity' => 0,
    'expired_stock_value' => 0,
];
$products = $products ?? [];
$openLots = $openLots ?? [];
$lotCatalog = $lotCatalog ?? [];
$recentMovements = $recentMovements ?? [];
$filters = $filters ?? ['q' => '', 'stock_state' => '', 'expiration_date' => '', 'supplier' => ''];
$exportLotsQuery = array_filter([
    'q' => (string) ($filters['q'] ?? ''),
    'stock_state' => (string) ($filters['stock_state'] ?? ''),
    'expiration_date' => (string) ($filters['expiration_date'] ?? ''),
    'supplier' => (string) ($filters['supplier'] ?? ''),
], static fn(string $value): bool => $value !== '');
$exportLotsUrl = '/stock/lots/export' . ($exportLotsQuery !== [] ? '?' . http_build_query($exportLotsQuery) : '');
$editingProduct = $editingProduct ?? null;
$nextSkuPreview = $nextSkuPreview ?? ('PRD-' . date('ym') . '-0001');
$alerts = $alerts ?? [];
$supplierOptions = $supplierOptions ?? [];
$flashSuccess = $flashSuccess ?? '';
$flashError = $flashError ?? '';
$showProductForm = is_array($editingProduct) && $editingProduct !== [];
$csrfToken = App\Core\Security::generateCSRF();
$productUnitMap = [];
$productMetaMap = [];
foreach ($products as $productItem) {
    $pid = (int) ($productItem['id'] ?? 0);
    if ($pid <= 0) {
        continue;
    }
    $options = [];
    foreach (($productItem['unit_options'] ?? []) as $unitOption) {
        $options[] = [
            'unit_code' => (string) ($unitOption['unit_code'] ?? ''),
            'unit_label' => (string) ($unitOption['unit_label'] ?? ''),
            'factor_to_base' => (float) ($unitOption['factor_to_base'] ?? 1),
            'is_base' => (bool) ($unitOption['is_base'] ?? false),
        ];
    }
    $productUnitMap[$pid] = $options;
    $baseUnitCode = (string) ($productItem['unit'] ?? 'unite');
    $baseUnitLabel = $baseUnitCode;
    $packagingUnitCode = '';
    $packagingUnitLabel = '';
    $packagingFactor = 0.0;
    foreach ($options as $option) {
        if ((bool) ($option['is_base'] ?? false) === true) {
            $baseUnitCode = (string) ($option['unit_code'] ?? $baseUnitCode);
            $baseUnitLabel = (string) ($option['unit_label'] ?? $baseUnitCode);
            continue;
        }
        if ($packagingUnitCode === '') {
            $packagingUnitCode = (string) ($option['unit_code'] ?? '');
            $packagingUnitLabel = (string) ($option['unit_label'] ?? $packagingUnitCode);
            $packagingFactor = (float) ($option['factor_to_base'] ?? 0);
        }
    }
    $productMetaMap[$pid] = [
        'name' => (string) ($productItem['name'] ?? ''),
        'brand' => (string) ($productItem['brand'] ?? ''),
        'supplier' => (string) ($productItem['supplier'] ?? ''),
        'dosage' => (string) ($productItem['dosage'] ?? ''),
        'forme' => (string) ($productItem['forme'] ?? ''),
        'presentation' => (string) ($productItem['presentation'] ?? ''),
        'base_unit_code' => $baseUnitCode,
        'base_unit_label' => $baseUnitLabel,
        'packaging_unit_code' => $packagingUnitCode,
        'packaging_unit_label' => $packagingUnitLabel,
        'packaging_factor' => $packagingFactor,
        'purchase_price' => (float) ($productItem['purchase_price'] ?? 0),
        'sale_price' => (float) ($productItem['sale_price'] ?? 0),
    ];
}

$defaultBaseUnitOptions = [
    'unite' => 'Pièce',
    'm' => 'Mètre',
    'kg' => 'Kilogramme',
    'l' => 'Litre',
    'paire' => 'Paire',
    'boite' => 'Boîte',
];

$defaultPackagingOptions = [
    '' => 'Aucune',
    'rouleau' => 'Rouleau',
    'carton' => 'Carton',
    'sac' => 'Sac',
    'palette' => 'Palette',
    'pack' => 'Pack',
];
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
$stockSummaryFootnotes = [
    'products' => ((int) ($summary['expired_lot_count'] ?? 0)) > 0
        ? $formatNumber((int) ($summary['expired_lot_count'] ?? 0), 0) . ' lots perimes declasses'
        : 'Aucun lot perime declasse',
    'quantity' => ((float) ($summary['expired_quantity'] ?? 0)) > 0
        ? $formatNumber((float) ($summary['expired_quantity'] ?? 0), 2) . ' unites perimees hors vente'
        : $formatNumber((int) ($summary['low_stock_count'] ?? 0), 0) . ' stocks en alerte',
    'value' => ((float) ($summary['expired_stock_value'] ?? 0)) > 0
        ? $formatMoney((float) ($summary['expired_stock_value'] ?? 0)) . ' immobilises en perime'
        : $formatNumber((int) ($summary['low_stock_count'] ?? 0), 0) . ' stocks en alerte',
    'alerts' => ((int) ($summary['expired_lot_count'] ?? 0)) > 0
        ? $formatNumber((int) ($summary['expired_lot_count'] ?? 0), 0) . ' lots perimes a traiter'
        : 'Surveillance stock active',
];

$lotsByProduct = [];
foreach ($openLots as $lotItem) {
    $lotProductId = (int) ($lotItem['product_id'] ?? 0);
    if ($lotProductId <= 0) {
        continue;
    }
    if (!isset($lotsByProduct[$lotProductId])) {
        $lotsByProduct[$lotProductId] = [];
    }
    $lotsByProduct[$lotProductId][] = [
        'id' => (int) ($lotItem['id'] ?? 0),
        'lot_code' => (string) ($lotItem['lot_code'] ?? ''),
        'supplier' => (string) ($lotItem['supplier'] ?? ''),
        'source_type' => (string) ($lotItem['source_type'] ?? ''),
        'source_reference' => (string) ($lotItem['source_reference'] ?? ''),
        'quantity_initial_base' => (float) ($lotItem['quantity_initial_base'] ?? 0),
        'quantity_remaining_base' => (float) ($lotItem['quantity_remaining_base'] ?? 0),
        'unit_cost_base' => (float) ($lotItem['unit_cost_base'] ?? 0),
        'expiration_date' => (string) ($lotItem['expiration_date'] ?? ''),
        'opened_at' => (string) ($lotItem['opened_at'] ?? ''),
        'base_unit' => (string) ($lotItem['base_unit'] ?? ''),
        'is_expired' => (bool) ($lotItem['is_expired'] ?? false),
        'is_in_peremption' => (bool) ($lotItem['is_in_peremption'] ?? false),
    ];
}

$productDetailsMap = [];
foreach ($products as $productItem) {
    $pid = (int) ($productItem['id'] ?? 0);
    if ($pid <= 0) {
        continue;
    }
    $packagingUnit = '';
    $packagingFactor = 0.0;
    foreach (($productItem['unit_options'] ?? []) as $unitOption) {
        if ((bool) ($unitOption['is_base'] ?? false) === true) {
            continue;
        }
        $packagingUnit = (string) ($unitOption['unit_label'] ?? ($unitOption['unit_code'] ?? ''));
        $packagingFactor = (float) ($unitOption['factor_to_base'] ?? 0);
        break;
    }
$productDetailsMap[$pid] = [
        'id' => $pid,
        'name' => (string) ($productItem['name'] ?? ''),
        'sku' => (string) ($productItem['sku'] ?? ''),
        'brand' => (string) ($productItem['brand'] ?? ''),
        'supplier' => (string) ($productItem['supplier'] ?? ''),
        'dosage' => (string) ($productItem['dosage'] ?? ''),
        'forme' => (string) ($productItem['forme'] ?? ''),
        'presentation' => (string) ($productItem['presentation'] ?? ''),
        'color_hex' => (string) ($productItem['color_hex'] ?? ''),
        'base_unit' => (string) ($productItem['unit'] ?? 'unite'),
        'quantity' => (float) ($productItem['quantity'] ?? 0),
        'min_stock' => (float) ($productItem['min_stock'] ?? 0),
        'purchase_price' => (float) ($productItem['purchase_price'] ?? 0),
        'sale_price' => (float) ($productItem['sale_price'] ?? 0),
        'expiration_date' => (string) ($productItem['expiration_date'] ?? ''),
        'packaging_unit' => $packagingUnit,
        'packaging_factor' => $packagingFactor,
        'open_lots' => (int) ($productItem['open_lots'] ?? 0),
        'lots_qty' => (float) ($productItem['lots_qty'] ?? 0),
        'lots' => $lotsByProduct[$pid] ?? [],
    ];
}

$lotDetailsMap = [];
foreach ($openLots as $lotItem) {
    $lotId = (int) ($lotItem['id'] ?? 0);
    if ($lotId <= 0) {
        continue;
    }
    $lotDetailsMap[$lotId] = [
        'id' => $lotId,
        'product_id' => (int) ($lotItem['product_id'] ?? 0),
        'product_name' => (string) ($lotItem['product_name'] ?? ''),
        'product_sku' => (string) ($lotItem['product_sku'] ?? ''),
        'lot_code' => (string) ($lotItem['lot_code'] ?? ''),
        'supplier' => (string) ($lotItem['supplier'] ?? ''),
        'quantity_initial_base' => (float) ($lotItem['quantity_initial_base'] ?? 0),
        'quantity_remaining_base' => (float) ($lotItem['quantity_remaining_base'] ?? 0),
        'unit_cost_base' => (float) ($lotItem['unit_cost_base'] ?? 0),
        'expiration_date' => (string) ($lotItem['expiration_date'] ?? ''),
        'base_unit' => (string) ($lotItem['base_unit'] ?? 'unite'),
        'is_expired' => (bool) ($lotItem['is_expired'] ?? false),
        'is_in_peremption' => (bool) ($lotItem['is_in_peremption'] ?? false),
    ];
}

$productFormConfig = is_array($productFormConfig ?? null) ? $productFormConfig : [];
if ($productFormConfig === []) {
    $productFormConfig = \App\Models\ProductFormSettings::defaultConfig();
}
$pfFields = is_array($productFormConfig['fields'] ?? null) ? $productFormConfig['fields'] : [];
$pfBaseUnits = is_array($productFormConfig['base_units'] ?? null) ? $productFormConfig['base_units'] : [];
$pfFormes = is_array($productFormConfig['formes'] ?? null) ? $productFormConfig['formes'] : [];
$pfDefaults = is_array($productFormConfig['defaults'] ?? null) ? $productFormConfig['defaults'] : [];
$pfDefaultBaseUnit = (string) ($pfDefaults['base_unit_code'] ?? 'unite');

$pfName = is_array($pfFields['name'] ?? null) ? $pfFields['name'] : [];
$pfSupplier = is_array($pfFields['supplier'] ?? null) ? $pfFields['supplier'] : [];
$pfDosage = is_array($pfFields['dosage'] ?? null) ? $pfFields['dosage'] : [];
$pfForme = is_array($pfFields['forme'] ?? null) ? $pfFields['forme'] : [];
$pfPresentation = is_array($pfFields['presentation'] ?? null) ? $pfFields['presentation'] : [];
$pfBaseUnit = is_array($pfFields['base_unit'] ?? null) ? $pfFields['base_unit'] : [];

$showSupplierField = (bool) ($pfSupplier['enabled'] ?? true);
$showDosageField = (bool) ($pfDosage['enabled'] ?? true);
$showFormeField = (bool) ($pfForme['enabled'] ?? true);
$showPresentationField = (bool) ($pfPresentation['enabled'] ?? true);

$supplierRequired = $showSupplierField && (bool) ($pfSupplier['required'] ?? false);
$dosageRequired = $showDosageField && (bool) ($pfDosage['required'] ?? false);
$formeRequired = $showFormeField && (bool) ($pfForme['required'] ?? false);
$presentationRequired = $showPresentationField && (bool) ($pfPresentation['required'] ?? false);

$formeInputType = strtolower(trim((string) ($pfForme['input'] ?? 'text')));
if (!in_array($formeInputType, ['text', 'select'], true)) {
    $formeInputType = 'text';
}

$brandSuggestions = [];
$dosageSuggestions = [];
$presentationSuggestions = [];
foreach ($products as $productItem) {
    $brand = trim((string) ($productItem['brand'] ?? ''));
    if ($brand !== '') {
        $brandSuggestions[$brand] = true;
    }
    $dosage = trim((string) ($productItem['dosage'] ?? ''));
    if ($dosage !== '') {
        $dosageSuggestions[$dosage] = true;
    }
    $presentation = trim((string) ($productItem['presentation'] ?? ''));
    if ($presentation !== '') {
        $presentationSuggestions[$presentation] = true;
    }
}
$brandSuggestions = array_keys($brandSuggestions);
sort($brandSuggestions);
$dosageSuggestions = array_keys($dosageSuggestions);
sort($dosageSuggestions);
$presentationSuggestions = array_keys($presentationSuggestions);
sort($presentationSuggestions);

$canManageStock = $canManageStock ?? true;
$stockAlerts = [];
$outOfStockCount = 0;
$lowStockBannerCount = 0;
$expiryAlertCount = 0;
$criticalStockAlertCount = 0;
if ($alerts !== []) {
    foreach ($alerts as $alert) {
        $type = (string) ($alert['type'] ?? '');
        if (!in_array($type, ['stock', 'expiry'], true)) {
            continue;
        }

        $stockAlerts[] = $alert;
        $statusKey = (string) ($alert['status_key'] ?? '');
        $severity = (string) ($alert['severity'] ?? '');
        if ($statusKey === 'out_of_stock') {
            $outOfStockCount++;
        }
        if ($statusKey === 'low_stock') {
            $lowStockBannerCount++;
        }
        if ($type === 'expiry') {
            $expiryAlertCount++;
        }
        if ($severity === 'critical') {
            $criticalStockAlertCount++;
        }
    }
}
$stockAlertCount = count($stockAlerts);
?>

<div class="stocks-page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Stock Produits</h1>
            <p class="page-subtitle">Suivi des quantites, seuils bas et mouvements de stock</p>
        </div>
    </div>

    <?php if ($flashSuccess !== ''): ?>
    <div class="flash-message flash-success"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($flashError !== ''): ?>
    <div class="flash-message flash-error"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($stockAlertCount > 0): ?>
    <div class="alert-banner">
        <div class="alert-banner-head">
            <div>
                <div class="alert-banner-title">Alertes stock (<?= htmlspecialchars((string) $stockAlertCount, ENT_QUOTES, 'UTF-8') ?>)</div>
                <div class="alert-banner-summary">Le resume reste compact ici. Ouvrez la page detail pour voir chaque alerte proprement.</div>
            </div>
            <a class="btn-icon alert-banner-detail-btn" href="/stock/alerts" title="Voir le detail des alertes stock" aria-label="Voir le detail des alertes stock">
                <i class="fa-solid fa-circle-info"></i>
            </a>
        </div>
        <div class="alert-banner-items">
            <?php if ($criticalStockAlertCount > 0): ?>
            <span class="alert-banner-item alert-banner-item-critical">Critiques: <?= (int) $criticalStockAlertCount ?></span>
            <?php endif; ?>
            <?php if ($outOfStockCount > 0): ?>
            <span class="alert-banner-item alert-banner-item-critical">Ruptures: <?= (int) $outOfStockCount ?></span>
            <?php endif; ?>
            <?php if ($lowStockBannerCount > 0): ?>
            <span class="alert-banner-item alert-banner-item-warning">Stock bas: <?= (int) $lowStockBannerCount ?></span>
            <?php endif; ?>
            <?php if ($expiryAlertCount > 0): ?>
            <span class="alert-banner-item alert-banner-item-warning">Expirations: <?= (int) $expiryAlertCount ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="stats-row stock-kpi-row" style="display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-bottom:20px;">
        <div class="stat-card stock-kpi-card" style="padding:20px;">
            <div class="stat-label">Produits actifs</div>
            <div class="stat-value"><?= htmlspecialchars($formatNumber((int) $summary['product_count'], 0), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="stock-kpi-footnote"><?= htmlspecialchars((string) ($stockSummaryFootnotes['products'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="stat-card stock-kpi-card" style="padding:20px;">
            <div class="stat-label">Quantite totale</div>
            <div class="stat-value"><?= htmlspecialchars($formatNumber((float) $summary['total_quantity'], 2), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="stock-kpi-footnote"><?= htmlspecialchars((string) ($stockSummaryFootnotes['quantity'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="stat-card stock-kpi-card" style="padding:20px;">
            <div class="stat-label">Valeur de stock (achat)</div>
            <div class="stat-value"><?= htmlspecialchars($formatMoney((float) $summary['stock_value']), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="stock-kpi-footnote"><?= htmlspecialchars((string) ($stockSummaryFootnotes['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="stat-card stock-kpi-card" style="padding:20px;">
            <div class="stat-label">Articles en alerte</div>
            <div class="stat-value"><?= htmlspecialchars($formatNumber((int) $summary['low_stock_count'], 0), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="stock-kpi-footnote"><?= htmlspecialchars((string) ($stockSummaryFootnotes['alerts'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>

	    <?php if ($canManageStock): ?>
	    <div class="card stock-action-row" style="margin-bottom: 20px;">
	        <div style="display:flex;gap:10px;flex-wrap:wrap;">
	            <button type="button" class="btn btn-add" id="toggle-product-form-btn">
	                <i class="fa-solid fa-plus"></i> Creer un lot (nouveau produit)
	            </button>
	            <form method="POST" action="/stock/purchase-orders/generate-critical" style="display:inline-flex;gap:8px;align-items:center;">
	                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
	                <input type="hidden" name="horizon_days" value="21">
	                <button type="submit" class="btn btn-soft" title="Genere un bon de commande avec les produits proches du seuil de rupture">
	                    <i class="fa-solid fa-cart-shopping"></i> Generer bon de commande (seuil)
	                </button>
	            </form>
	        </div>
	        <p class="text-secondary" style="font-size:12px;margin-top:8px;">
	            Les formulaires sont masques par defaut pour eviter l'encombrement.
	        </p>
	    </div>
	    <?php endif; ?>

    <?php if ($canManageStock): ?>
    <div class="stock-modal-overlay" id="stock-new-lot-modal" aria-hidden="true">
        <div class="stock-modal-dialog stock-modal-dialog-lg" role="dialog" aria-modal="true" aria-labelledby="stock-new-lot-title">
            <div class="stock-modal-head">
                <h3 id="stock-new-lot-title" style="margin:0;"><?= is_array($editingProduct) ? 'Modifier le produit' : 'Creer un lot (nouveau produit)' ?></h3>
                <button type="button" class="btn-icon" id="stock-new-lot-close" aria-label="Fermer">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="stock-modal-body">
                <div id="product-form-card">
                    <form method="POST" action="<?= is_array($editingProduct) ? '/stock/update/' . (int) $editingProduct['id'] : '/stock/store' ?>" data-async="true" data-async-success="<?= is_array($editingProduct) ? 'Produit mis a jour.' : 'Lot cree.' ?>" class="stock-product-form" id="stock-product-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="purchase_price" id="product-purchase-unit-base" value="<?= htmlspecialchars(number_format((float) ($editingProduct['purchase_price'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="min_stock" id="product-min-stock" value="<?= htmlspecialchars(number_format((float) ($editingProduct['min_stock'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" id="product-sku-preview" value="<?= htmlspecialchars((string) (is_array($editingProduct) ? ($editingProduct['sku'] ?? '-') : $nextSkuPreview), ENT_QUOTES, 'UTF-8') ?>">

                        <div class="stock-form-layout">
                            <div class="stock-form-columns">
                            <section class="stock-form-panel">
                                <h4>Produit</h4>
                                <div class="stock-grid">
                                    <label class="filter-group stock-field-large">
                                        <span><?= htmlspecialchars((string) ($pfName['label'] ?? 'Nom du produit'), ENT_QUOTES, 'UTF-8') ?> *</span>
                                        <input class="filter-input" id="product-name" type="text" name="name" required value="<?= htmlspecialchars((string) ($editingProduct['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= htmlspecialchars((string) ($pfName['placeholder'] ?? 'Ex: Produit A'), ENT_QUOTES, 'UTF-8') ?>">
                                        <div class="field-error" data-error-for="product-name"></div>
                                    </label>

                                    <?php if ($showSupplierField): ?>
                                    <label class="filter-group stock-field-medium">
                                        <span><?= htmlspecialchars((string) ($pfSupplier['label'] ?? 'Fournisseur'), ENT_QUOTES, 'UTF-8') ?><?= $supplierRequired ? ' *' : '' ?></span>
                                        <input class="filter-input" id="product-supplier" type="text" name="supplier" value="<?= htmlspecialchars((string) ($editingProduct['supplier'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= htmlspecialchars((string) ($pfSupplier['placeholder'] ?? 'Ex: Fournisseur A'), ENT_QUOTES, 'UTF-8') ?>" <?= $supplierRequired ? 'required' : '' ?>>
                                    </label>
                                    <?php endif; ?>

                                    <?php if ($showDosageField): ?>
                                    <label class="filter-group stock-field-medium">
                                        <span><?= htmlspecialchars((string) ($pfDosage['label'] ?? 'Spécification'), ENT_QUOTES, 'UTF-8') ?><?= $dosageRequired ? ' *' : '' ?></span>
                                        <input class="filter-input" id="product-dosage" type="text" list="product-dosage-suggestions" name="dosage" value="<?= htmlspecialchars((string) ($editingProduct['dosage'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= htmlspecialchars((string) ($pfDosage['placeholder'] ?? 'Ex: Variante / dimension / référence'), ENT_QUOTES, 'UTF-8') ?>" <?= $dosageRequired ? 'required' : '' ?>>
                                    </label>
                                    <?php endif; ?>

                                    <?php if ($showFormeField): ?>
                                    <label class="filter-group stock-field-medium">
                                        <span><?= htmlspecialchars((string) ($pfForme['label'] ?? 'Forme'), ENT_QUOTES, 'UTF-8') ?><?= $formeRequired ? ' *' : '' ?></span>
                                        <?php
                                            $editingFormeRaw = trim((string) ($editingProduct['forme'] ?? ''));
                                            $hasEditingFormeInList = $editingFormeRaw !== '' && in_array($editingFormeRaw, $pfFormes, true);
                                        ?>
                                        <?php if ($formeInputType === 'select'): ?>
                                            <select class="filter-select" id="product-forme" name="forme" <?= $formeRequired ? 'required' : '' ?>>
                                                <option value="">Choisir...</option>
                                                <?php if ($editingFormeRaw !== '' && !$hasEditingFormeInList): ?>
                                                    <option value="<?= htmlspecialchars($editingFormeRaw, ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars($editingFormeRaw, ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endif; ?>
                                                <?php foreach ($pfFormes as $formeOption): ?>
                                                    <?php $formeOption = (string) $formeOption; ?>
                                                    <option value="<?= htmlspecialchars($formeOption, ENT_QUOTES, 'UTF-8') ?>" <?= $formeOption === $editingFormeRaw ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($formeOption, ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php else: ?>
                                            <input class="filter-input" id="product-forme" type="text" list="product-forme-suggestions" name="forme" value="<?= htmlspecialchars($editingFormeRaw, ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= htmlspecialchars((string) ($pfForme['placeholder'] ?? 'Ex: Type / variante'), ENT_QUOTES, 'UTF-8') ?>" <?= $formeRequired ? 'required' : '' ?>>
                                        <?php endif; ?>
                                    </label>
                                    <?php endif; ?>

                                    <?php if ($showPresentationField): ?>
                                    <label class="filter-group stock-field-large">
                                        <span><?= htmlspecialchars((string) ($pfPresentation['label'] ?? 'Présentation'), ENT_QUOTES, 'UTF-8') ?><?= $presentationRequired ? ' *' : '' ?></span>
                                        <input class="filter-input" id="product-presentation" type="text" name="presentation" list="product-presentation-suggestions" value="<?= htmlspecialchars((string) ($editingProduct['presentation'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= htmlspecialchars((string) ($pfPresentation['placeholder'] ?? 'Ex: Conditionnement / détail'), ENT_QUOTES, 'UTF-8') ?>" <?= $presentationRequired ? 'required' : '' ?>>
                                    </label>
                                    <?php endif; ?>

                                    <label class="filter-group stock-field-medium">
                                        <span><?= htmlspecialchars((string) ($pfBaseUnit['label'] ?? 'Unité de base'), ENT_QUOTES, 'UTF-8') ?> *</span>
                                        <?php
                                            $editingBaseUnit = trim((string) ($editingProduct['unit'] ?? ''));
                                            $selectedBaseUnit = $editingBaseUnit !== '' ? $editingBaseUnit : $pfDefaultBaseUnit;
                                            $baseUnitCodes = [];
                                            foreach ($pfBaseUnits as $u) {
                                                $code = trim((string) ($u['code'] ?? ''));
                                                if ($code !== '') {
                                                    $baseUnitCodes[$code] = true;
                                                }
                                            }
                                        ?>
                                        <select class="filter-select" id="product-base-unit" name="base_unit_code" required>
                                            <?php if ($selectedBaseUnit !== '' && !isset($baseUnitCodes[$selectedBaseUnit])): ?>
                                                <option value="<?= htmlspecialchars($selectedBaseUnit, ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars($selectedBaseUnit, ENT_QUOTES, 'UTF-8') ?> (actuel)</option>
                                            <?php endif; ?>
                                            <?php foreach ($pfBaseUnits as $u): ?>
                                                <?php
                                                    $code = trim((string) ($u['code'] ?? ''));
                                                    if ($code === '') {
                                                        continue;
                                                    }
                                                    $label = trim((string) ($u['label'] ?? ''));
                                                    if ($label === '') {
                                                        $label = $code;
                                                    }
                                                ?>
                                                <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" <?= $code === $selectedBaseUnit ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>
                            </section>

                            <section class="stock-form-panel">
                                <h4>Lot / Prix</h4>
                                <div class="stock-grid">
                                    <label class="filter-group stock-field-medium">
                                        <span>Quantite du lot <?= is_array($editingProduct) ? '' : '*' ?></span>
                                        <input class="filter-input" id="product-stock-quantity" type="number" step="0.000001" min="<?= is_array($editingProduct) ? '0' : '0.000001' ?>" name="quantity" <?= is_array($editingProduct) ? 'disabled' : 'required' ?> value="<?= htmlspecialchars(number_format((float) ($editingProduct['quantity'] ?? 0), 6, '.', ''), ENT_QUOTES, 'UTF-8') ?>">
                                    </label>

                                    <label class="filter-group stock-field-medium">
                                        <span>Prix achat du lot</span>
                                        <input class="filter-input" id="product-lot-purchase-price" type="number" step="0.01" min="0" value="<?= htmlspecialchars(number_format((float) ($editingProduct['purchase_price'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Ex: 2500">
                                        <div class="field-error" data-error-for="product-lot-purchase-price"></div>
                                    </label>

                                    <label class="filter-group stock-field-medium">
                                        <span>Prix vente unitaire</span>
                                        <input class="filter-input" id="product-sale-price" type="number" step="0.01" min="0" name="sale_price" value="<?= htmlspecialchars(number_format((float) ($editingProduct['sale_price'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <div class="field-error" data-error-for="product-sale-price"></div>
                                    </label>
                                    <label class="filter-group stock-field-medium">
                                        <span>Date d'expiration <?= is_array($editingProduct) ? '' : '(lot initial)' ?></span>
                                        <input class="filter-input" id="product-expiration-date" type="date" name="expiration_date" value="<?= htmlspecialchars((string) ($editingProduct['expiration_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <div class="field-error" data-error-for="product-expiration-date"></div>
                                    </label>
                                    <?php if (!is_array($editingProduct)): ?>
                                    <label class="filter-group stock-field-medium">
                                        <span>N° lot *</span>
                                        <input class="filter-input" id="product-initial-lot-code" type="text" name="initial_lot_code" placeholder="Ex: LOT-2026-001">
                                        <div class="field-error" data-error-for="product-initial-lot-code"></div>
                                    </label>
                                    <?php endif; ?>
                                </div>
                            </section>
                            </div>

                            <aside class="stock-form-summary" id="product-smart-summary" aria-live="polite">
                                <h4 style="margin:0 0 10px 0;">Resume en langage simple</h4>
                                <p id="product-summary-text" style="margin:0 0 10px 0;color:var(--text-primary);font-size:13px;line-height:1.45;"></p>
                                <div class="stock-summary-grid">
                                    <div><span>SKU auto:</span> <strong id="summary-sku">-</strong></div>
                                    <div><span>Unite base:</span> <strong id="summary-base-unit">-</strong></div>
                                    <div><span>Seuil mini auto:</span> <strong id="summary-min-stock">-</strong></div>
                                    <div><span>N° lot:</span> <strong id="summary-lot">-</strong></div>
                                    <div><span>Prix achat unitaire:</span> <strong id="summary-purchase-unit">-</strong></div>
                                    <div><span>Prix de vente unitaire:</span> <strong id="summary-sale-unit">-</strong></div>
                                    <div><span>Marge:</span> <strong id="summary-margin">-</strong></div>
                                </div>
                            </aside>
                        </div>

                        <div style="display:flex;align-items:flex-end;gap:8px;grid-column:1 / -1;margin-top:10px;">
                            <button type="submit" id="stock-product-submit" class="btn <?= is_array($editingProduct) ? 'btn-primary' : 'btn-add' ?>"><?= is_array($editingProduct) ? 'Mettre a jour' : 'Enregistrer' ?></button>
                            <?php if (is_array($editingProduct)): ?>
                            <a class="btn btn-soft" href="/stock">Annuler</a>
                            <?php endif; ?>
                        </div>
                        <div class="field-error" id="duplicate-lot-message"></div>
                    </form>

                    <datalist id="product-dosage-suggestions">
                        <?php foreach ($dosageSuggestions as $dosageSuggestion): ?>
                        <option value="<?= htmlspecialchars((string) $dosageSuggestion, ENT_QUOTES, 'UTF-8') ?>">
                        <?php endforeach; ?>
                    </datalist>

                    <?php if ($showPresentationField): ?>
                    <datalist id="product-presentation-suggestions">
                        <?php foreach ($presentationSuggestions as $presentationSuggestion): ?>
                        <option value="<?= htmlspecialchars((string) $presentationSuggestion, ENT_QUOTES, 'UTF-8') ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <?php endif; ?>

                    <?php if ($showFormeField && $formeInputType === 'text'): ?>
                    <datalist id="product-forme-suggestions">
                        <?php foreach ($pfFormes as $formeOption): ?>
                        <option value="<?= htmlspecialchars((string) $formeOption, ENT_QUOTES, 'UTF-8') ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <?php endif; ?>

                    <?php if (!is_array($editingProduct)): ?>
                    <hr style="margin:18px 0;border:none;border-top:1px solid var(--border-light);">
                    <h3 style="margin-bottom: 8px;">Ajouter une quantite a un lot existant</h3>
                    <p class="text-secondary" style="font-size:12px;margin:0 0 12px 0;">
                        Si le lot recu existe deja, on ajoute seulement une quantite. Les autres proprietes restent verrouillees et l'ajout reste trace dans les mouvements.
                    </p>
                    <form method="POST" action="/stock/lots/0/update" data-action-template="/stock/lots/{id}/update" data-async="true" data-async-success="Quantite ajoutee au lot." id="stock-add-lot-form" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <label class="filter-group" style="grid-column:1 / -1;">
                            <span>Lot existant *</span>
                            <select class="filter-select" id="add-lot-existing-id" required <?= $openLots === [] ? 'disabled' : '' ?>>
                                <option value="">Selectionner un lot...</option>
                                <?php foreach ($openLots as $lot): ?>
                                <?php
                                    $lotOptionId = (int) ($lot['id'] ?? 0);
                                    $lotOptionLabel = trim((string) ($lot['product_name'] ?? '')) . ' | Lot ' . trim((string) ($lot['lot_code'] ?? '-'));
                                    $lotOptionLabel .= ' | Stock ' . $formatNumber((float) ($lot['quantity_remaining_base'] ?? 0), 2) . ' ' . trim((string) ($lot['base_unit'] ?? 'unite'));
                                    if (!empty($lot['supplier'])) {
                                        $lotOptionLabel .= ' | ' . trim((string) $lot['supplier']);
                                    }
                                    $lotOptionExpired = (bool) ($lot['is_expired'] ?? false);
                                    $lotOptionPeremption = (!$lotOptionExpired) && (bool) ($lot['is_in_peremption'] ?? false);
                                    if ($lotOptionExpired) {
                                        $lotOptionLabel .= ' | Perime';
                                    } elseif ($lotOptionPeremption) {
                                        $lotOptionLabel .= ' | Peremption';
                                    }
                                ?>
                                <option value="<?= $lotOptionId ?>" <?= $lotOptionExpired ? 'disabled' : '' ?>>
                                    <?= htmlspecialchars($lotOptionLabel, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="filter-group">
                            <span>Quantite a ajouter *</span>
                            <input class="filter-input" id="add-lot-quantity" type="number" step="0.000001" min="0.000001" name="quantity_add" required placeholder="Ex: 10" <?= $openLots === [] ? 'disabled' : '' ?>>
                        </label>

                        <label class="filter-group">
                            <span>Unite *</span>
                            <select class="filter-select" id="add-lot-unit" name="unit_code" required <?= $openLots === [] ? 'disabled' : '' ?>>
                                <option value="">Choisir l unite...</option>
                            </select>
                        </label>

                        <label class="filter-group">
                            <span>Statut</span>
                            <input class="filter-input" id="add-lot-status" type="text" value="-" readonly>
                        </label>

                        <label class="filter-group">
                            <span>Produit</span>
                            <input class="filter-input" id="add-lot-product-name" type="text" value="-" readonly>
                        </label>

                        <label class="filter-group">
                            <span>N° lot</span>
                            <input class="filter-input" id="add-lot-code" type="text" value="-" readonly>
                        </label>

                        <label class="filter-group">
                            <span>Fournisseur</span>
                            <input class="filter-input" id="add-lot-supplier" type="text" value="-" readonly>
                        </label>

                        <label class="filter-group">
                            <span>Stock actuel du lot</span>
                            <input class="filter-input" id="add-lot-current-quantity" type="text" value="-" readonly>
                        </label>

                        <label class="filter-group" style="grid-column:1 / -1;">
                            <span>Expiration / Cout unitaire</span>
                            <input class="filter-input" id="add-lot-readonly-meta" type="text" value="-" readonly>
                        </label>

                        <div style="display:flex;align-items:flex-end;gap:8px;grid-column:1 / -1;">
                            <button type="submit" class="btn btn-add" <?= $openLots === [] ? 'disabled' : '' ?>>Ajouter la quantite</button>
                            <?php if ($openLots === []): ?>
                            <span class="text-secondary" style="font-size:12px;">Aucun lot actif disponible.</span>
                            <?php else: ?>
                            <span class="text-secondary" style="font-size:12px;">Modification du lot interdite ici: seule la quantite peut augmenter.</span>
                            <?php endif; ?>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

    <div class="card stock-sections-toolbar">
        <div class="stock-sections-toolbar-head">
            <strong>Sections affichables</strong>
            <span class="text-secondary">Affichez uniquement les tableaux dont vous avez besoin.</span>
        </div>
        <div class="stock-sections-toolbar-actions">
            <button type="button" class="btn btn-soft stock-section-btn is-active" data-target="stock-products-card">Produits</button>
            <button type="button" class="btn btn-soft stock-section-btn" data-target="stock-lots-card">Lots</button>
            <button type="button" class="btn btn-soft stock-section-btn" data-target="stock-movements-card">Derniers mouvements</button>
        </div>
    </div>
    <div class="card" style="margin-bottom: 20px;">
        <form method="GET" action="/stock" data-auto-filter="true" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:10px;align-items:end;">
            <label class="filter-group">
                <span>Recherche</span>
                <input class="filter-input" type="text" name="q" value="<?= htmlspecialchars((string) $filters['q'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Nom ou SKU">
            </label>
            <label class="filter-group">
                <span>Fournisseur</span>
                <select class="filter-select" name="supplier">
                    <option value="" <?= ($filters['supplier'] ?? '') === '' ? 'selected' : '' ?>>Tous</option>
                    <?php foreach ($supplierOptions as $supplierOption): ?>
                    <option value="<?= htmlspecialchars((string) $supplierOption, ENT_QUOTES, 'UTF-8') ?>" <?= ((string) ($filters['supplier'] ?? '') === (string) $supplierOption) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $supplierOption, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="filter-group">
                <span>Etat stock</span>
                <select class="filter-select" name="stock_state">
                    <option value="" <?= ($filters['stock_state'] ?? '') === '' ? 'selected' : '' ?>>Tous</option>
                    <option value="low" <?= ($filters['stock_state'] ?? '') === 'low' ? 'selected' : '' ?>>Stock bas</option>
                    <option value="out" <?= ($filters['stock_state'] ?? '') === 'out' ? 'selected' : '' ?>>Rupture</option>
                </select>
            </label>
            <label class="filter-group">
                <span>Expiration avant</span>
                <input class="filter-input" type="date" name="expiration_date" value="<?= htmlspecialchars((string) ($filters['expiration_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button class="btn btn-soft js-auto-filter-submit" type="submit">Filtrer</button>
                <a class="btn" href="/stock">Reinitialiser</a>
                <a class="btn btn-soft" href="<?= htmlspecialchars($exportLotsUrl, ENT_QUOTES, 'UTF-8') ?>" data-no-async="true">Exporter lots</a>
            </div>
        </form>
    </div>

    <div class="card stock-section-card" id="stock-products-card" style="margin-bottom: 20px;">
        <h3 style="margin-bottom: 12px;">Produits</h3>
        <?php if ($canManageStock): ?>
        <form method="POST" action="/stock/delete-bulk" id="stock-products-bulk-form" data-async="true" data-async-success="Produits supprimes." style="margin-bottom:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn-soft btn-xs" id="stock-products-bulk-delete-btn">
                <i class="fa-regular fa-trash-can"></i> Supprimer selection produits
            </button>
            <span class="text-secondary" style="font-size:12px;">Selectionnez un ou plusieurs produits.</span>
        </form>
        <?php endif; ?>
        <table class="table">
            <thead>
                <tr>
                    <th style="width:36px;"><input type="checkbox" id="stock-products-select-all" aria-label="Selectionner tous les produits"></th>
                    <th>Produit</th>
                    <th>SKU</th>
                    <th>Stock</th>
                    <th>Seuil</th>
                    <th>Prix vente</th>
                    <th>Lots ouverts</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($products === []): ?>
                <tr><td colspan="9" style="text-align:center;color:var(--text-secondary);padding:20px;">Aucun produit trouve.</td></tr>
                <?php endif; ?>
                <?php foreach ($products as $product): ?>
                <?php
                    $qty = (float) ($product['quantity'] ?? 0);
                    $min = (float) ($product['min_stock'] ?? 0);
                    $isLow = $qty <= $min;
                ?>
                <tr>
                    <td>
                        <input
                            type="checkbox"
                            class="stock-product-checkbox"
                            <?= $canManageStock ? 'form="stock-products-bulk-form"' : '' ?>
                            name="product_ids[]"
                            value="<?= (int) ($product['id'] ?? 0) ?>"
                            <?= $canManageStock ? '' : 'disabled' ?>
                            aria-label="Selection produit <?= htmlspecialchars((string) ($product['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </td>
                    <td>
                        <strong><?= htmlspecialchars((string) $product['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <?php
                            $metaChunks = array_values(array_filter([
                                trim((string) ($product['dosage'] ?? '')),
                                trim((string) ($product['forme'] ?? '')),
                                trim((string) ($product['presentation'] ?? '')),
                            ], static fn(string $value): bool => $value !== ''));
                        ?>
                        <?php if ($metaChunks !== []): ?>
                        <div class="text-secondary" style="font-size:12px;"><?= htmlspecialchars(implode(' | ', $metaChunks), ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars((string) ($product['sku'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <strong class="<?= $isLow ? 'text-danger' : '' ?>"><?= htmlspecialchars($formatNumber($qty, 2), ENT_QUOTES, 'UTF-8') ?></strong>
                        <span class="text-secondary"><?= htmlspecialchars((string) ($product['unit'] ?? 'unite'), ENT_QUOTES, 'UTF-8') ?></span>
                    </td>
                    <td><?= htmlspecialchars($formatNumber($min, 2), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($formatMoney((float) ($product['sale_price'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <strong><?= (int) ($product['open_lots'] ?? 0) ?></strong>
                        <span class="text-secondary">actifs</span>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;align-items:center;">
                            <button type="button" class="btn-icon js-open-product-modal" title="Voir details" data-product-id="<?= (int) ($product['id'] ?? 0) ?>">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                            <?php if ($canManageStock): ?>
                            <a class="btn-icon" title="Modifier produit" href="/stock?edit=<?= (int) $product['id'] ?>"><i class="fa-regular fa-pen-to-square"></i></a>
                            <form method="POST" action="/stock/delete/<?= (int) $product['id'] ?>" class="js-delete-product-form" data-product-name="<?= htmlspecialchars((string) ($product['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="margin:0;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="btn-icon btn-icon-danger" title="Supprimer produit">
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
    </div>

    <div class="stock-modal-overlay" id="stock-product-modal" aria-hidden="true">
        <div class="stock-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="stock-product-modal-title">
            <div class="stock-modal-head">
                <h3 id="stock-product-modal-title" style="margin:0;">Details produit</h3>
                <button type="button" class="btn-icon" id="stock-product-modal-close" aria-label="Fermer">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="stock-modal-body" id="stock-product-modal-body"></div>
        </div>
    </div>

    <div class="card stock-section-card is-collapsed" id="stock-lots-card" style="margin-bottom: 20px;">
        <h3 style="margin-bottom: 12px;">Lots</h3>
        <?php if ($canManageStock): ?>
        <form method="POST" action="/stock/lots/delete-bulk" id="stock-lots-bulk-form" data-async="true" data-async-success="Lots supprimes." style="margin-bottom:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn-soft btn-xs" id="stock-lots-bulk-delete-btn">
                <i class="fa-regular fa-trash-can"></i> Supprimer selection lots
            </button>
            <span class="text-secondary" style="font-size:12px;">Selectionnez un ou plusieurs lots.</span>
        </form>
        <?php endif; ?>
        <table class="table">
            <thead>
                <tr>
                    <th style="width:36px;"><input type="checkbox" id="stock-lots-select-all" aria-label="Selectionner tous les lots"></th>
                    <th>Produit</th>
                    <th>N° lot</th>
                    <th>Fournisseur</th>
                    <th>Source</th>
                    <th>Qté initiale</th>
                    <th>Qté restante</th>
                    <th>Cout unitaire base</th>
                    <th>Expiration</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($openLots === []): ?>
                <tr><td colspan="12" style="text-align:center;color:var(--text-secondary);padding:20px;">Aucun lot enregistre.</td></tr>
                <?php endif; ?>
                <?php foreach ($openLots as $lot): ?>
                <?php
                    $lotId = (int) ($lot['id'] ?? 0);
                    $remaining = (float) ($lot['quantity_remaining_base'] ?? 0);
                    $isExpired = (bool) ($lot['is_expired'] ?? false);
                    $isPeremption = (!$isExpired) && (bool) ($lot['is_in_peremption'] ?? false);
                ?>
                <tr class="<?= $isExpired ? 'stock-lot-expired' : ($isPeremption ? 'stock-lot-peremption' : '') ?>">
                    <td>
                        <input
                            type="checkbox"
                            class="stock-lot-checkbox"
                            <?= $canManageStock ? 'form="stock-lots-bulk-form"' : '' ?>
                            name="lot_ids[]"
                            value="<?= $lotId ?>"
                            <?= $canManageStock ? '' : 'disabled' ?>
                            aria-label="Selection lot <?= htmlspecialchars((string) ($lot['lot_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </td>
                    <td>
                        <strong><?= htmlspecialchars((string) ($lot['product_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                        <div class="text-secondary" style="font-size:12px;"><?= htmlspecialchars((string) ($lot['product_sku'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                    </td>
                    <td><?= htmlspecialchars((string) ($lot['lot_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($lot['supplier'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($lot['source_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($formatNumber((float) ($lot['quantity_initial_base'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><strong><?= htmlspecialchars($formatNumber($remaining, 2), ENT_QUOTES, 'UTF-8') ?></strong> <?= htmlspecialchars((string) ($lot['base_unit'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($formatNumber((float) ($lot['unit_cost_base'] ?? 0), 6), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= !empty($lot['expiration_date']) ? htmlspecialchars(date('d/m/Y', strtotime((string) $lot['expiration_date'])), ENT_QUOTES, 'UTF-8') : '-' ?></td>
                    <td>
                        <?php if ($isExpired): ?>
                            <span class="lot-status lot-status-expired">Perime</span>
                        <?php elseif ($isPeremption): ?>
                            <span class="lot-status lot-status-peremption">Peremption</span>
                        <?php else: ?>
                            <span class="lot-status lot-status-ok">Actif</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) ($lot['opened_at'] ?? date('Y-m-d H:i:s')))), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if ($canManageStock): ?>
                        <form method="POST" action="/stock/lots/<?= $lotId ?>/update" data-async="true" data-async-success="Quantite ajoutee." style="display:grid;grid-template-columns:1fr 1fr auto;gap:6px;align-items:center;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <input class="filter-input" type="number" step="0.000001" min="0.000001" name="quantity_add" placeholder="Ajouter quantite">
                            <select class="filter-select" name="unit_code">
                                <?php
                                    $lotProductId = (int) ($lot['product_id'] ?? 0);
                                    $unitOptions = $productUnitMap[$lotProductId] ?? [];
                                ?>
                                <?php if ($unitOptions === []): ?>
                                <option value="<?= htmlspecialchars((string) ($lot['base_unit'] ?? 'unite'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($lot['base_unit'] ?? 'unite'), ENT_QUOTES, 'UTF-8') ?> (base)</option>
                                <?php else: ?>
                                    <?php foreach ($unitOptions as $unitOption): ?>
                                        <?php
                                            $unitCode = (string) ($unitOption['unit_code'] ?? '');
                                            $unitLabel = (string) ($unitOption['unit_label'] ?? $unitCode);
                                            $factor = (float) ($unitOption['factor_to_base'] ?? 1);
                                            $isBase = (bool) ($unitOption['is_base'] ?? false);
                                            $suffix = $isBase ? ' (base)' : (' x' . rtrim(rtrim(number_format($factor, 6, '.', ''), '0'), '.'));
                                        ?>
                                        <option value="<?= htmlspecialchars($unitCode, ENT_QUOTES, 'UTF-8') ?>" <?= $isBase ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($unitLabel . $suffix, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <button type="submit" class="btn btn-soft btn-xs">Ajouter</button>
                        </form>
                        <div class="text-secondary" style="font-size:11px;margin-top:6px;">Lot verrouille: ajout de quantite uniquement.</div>
                        <form method="POST" action="/stock/lots/<?= $lotId ?>/declass" class="js-declass-lot-form" data-lot-code="<?= htmlspecialchars((string) ($lot['lot_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="margin-top:6px;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn-icon btn-icon-warning" title="Declassement du lot">
                                <i class="fa-solid fa-box-archive"></i>
                            </button>
                        </form>
                        <form method="POST" action="/stock/lots/<?= $lotId ?>/delete" class="js-delete-lot-form" data-lot-code="<?= htmlspecialchars((string) ($lot['lot_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="margin-top:6px;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn btn-soft btn-xs btn-icon-danger">Supprimer</button>
                        </form>
                        <?php else: ?>
                        <span class="text-secondary">Lecture seule</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card stock-section-card is-collapsed" id="stock-movements-card">
        <h3 style="margin-bottom: 12px;">Derniers mouvements</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Produit</th>
                    <th>Type</th>
                    <th>Variation</th>
                    <th>Avant</th>
                    <th>Apres</th>
                    <th>Motif</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recentMovements === []): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--text-secondary);padding:20px;">Aucun mouvement enregistre.</td></tr>
                <?php endif; ?>
                <?php foreach ($recentMovements as $movement): ?>
                <?php $delta = (float) ($movement['quantity_change'] ?? 0); ?>
                <tr>
                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $movement['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($movement['product_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) strtoupper((string) ($movement['movement_type'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="<?= $delta < 0 ? 'text-danger' : 'text-success' ?>"><?= $delta > 0 ? '+' : '' ?><?= htmlspecialchars($formatNumber($delta, 2), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($formatNumber((float) ($movement['quantity_before'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($formatNumber((float) ($movement['quantity_after'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) (($movement['reason'] ?? '') !== '' ? $movement['reason'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.stocks-page .page-header {
    margin-bottom: 24px;
}

.stocks-page .page-title {
    font-size: 28px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.stocks-page .page-subtitle {
    color: var(--text-secondary);
}

.flash-message {
    border-radius: var(--radius-md);
    padding: 12px 14px;
    margin-bottom: 16px;
}

.flash-success { background: rgba(16, 185, 129, 0.14); color: var(--success); }
.flash-error { background: rgba(239, 68, 68, 0.14); color: var(--danger); }
.alert-banner {
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
    background: #fef3c7;
    border: 1px solid #facc15;
    color: #92400e;
    padding: 12px 16px;
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
.text-danger { color: var(--danger); }
.text-success { color: var(--success); }
.stock-lot-expired {
    background: rgba(239, 68, 68, 0.08);
}
.stock-lot-expired td {
    color: var(--danger);
}
.lot-status {
    display: inline-flex;
    align-items: center;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
}
.lot-status-ok {
    background: rgba(16, 185, 129, 0.12);
    color: var(--success);
}
.lot-status-expired {
    background: rgba(239, 68, 68, 0.16);
    color: var(--danger);
}
.lot-status-peremption {
    background: rgba(250, 204, 21, 0.12);
    color: #92400e;
}

.stock-product-form {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.stock-form-layout {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr);
    gap: 14px;
    align-items: start;
}

.stock-form-columns {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
}

.stock-form-panel {
    border: 1px solid var(--border-light);
    border-radius: 10px;
    background: var(--bg-surface);
    padding: 16px;
}

.stock-form-panel h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: var(--text-primary);
}

.stock-form-summary {
    border: 1px solid var(--border-light);
    border-radius: 10px;
    background: var(--bg-surface);
    padding: 14px;
    position: sticky;
    top: 88px;
}

.stock-summary-grid {
    display: grid;
    gap: 8px;
    font-size: 12px;
    color: var(--text-secondary);
}

.stock-summary-grid div {
    display: flex;
    justify-content: space-between;
    gap: 8px;
}

.stock-summary-grid strong {
    color: var(--text-primary);
}

.stock-grid {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 12px;
}

.stock-field-large { grid-column: span 12; }
.stock-field-medium { grid-column: span 6; }
.stock-field-small { grid-column: span 6; }

.stock-color-picker-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
}

.stock-color-chip {
    width: 26px;
    height: 26px;
    border-radius: 6px;
    border: 1px solid var(--border-light);
    display: inline-block;
}

.stock-mix-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.collapse-card {
    display: none;
}

.collapse-card.open {
    display: block;
}

.text-secondary { color: var(--text-secondary); }

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

.filter-input.is-invalid,
.filter-select.is-invalid {
    border-color: var(--danger);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--danger) 18%, transparent);
}

.field-error {
    margin-top: 4px;
    font-size: 11px;
    color: var(--danger);
    display: none;
}

.field-error.is-visible {
    display: block;
}

.filter-input[type="color"] {
    min-height: 42px;
    padding: 6px;
}

.btn-xs {
    padding: 7px 10px;
    font-size: 12px;
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

.btn-icon-danger {
    color: var(--danger);
    border-color: color-mix(in srgb, var(--danger) 45%, var(--border-light));
}

.btn-icon-danger:hover {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.btn-icon-warning {
    color: var(--warning);
    border-color: color-mix(in srgb, var(--warning) 45%, var(--border-light));
}

.btn-icon-warning:hover {
    background: rgba(245, 158, 11, 0.12);
    color: var(--warning);
}

.stock-sections-toolbar {
    margin-bottom: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.stock-sections-toolbar-head {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.stock-sections-toolbar-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.stock-section-btn.is-active {
    background: var(--accent);
    color: #fff;
    border-color: var(--accent);
}

.stock-kpi-card {
    min-height: 118px;
    display: flex;
    flex-direction: column;
}

.stock-kpi-footnote {
    margin-top: auto;
    padding-top: 12px;
    text-align: right;
    font-size: 11px;
    color: var(--text-secondary);
    opacity: 0.88;
}

.stock-section-card.is-collapsed {
    display: none;
}

.stock-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
    z-index: 1200;
}

.stock-modal-overlay.open {
    display: flex;
}

.stock-modal-dialog {
    width: min(900px, 100%);
    max-height: 88vh;
    overflow: auto;
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    border-radius: 12px;
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.2);
}

.stock-modal-dialog-lg {
    width: min(1100px, 100%);
}

.stock-modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border-light);
}

.stock-modal-body {
    padding: 14px 16px 16px;
}

.stock-modal-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 12px;
}

.stock-modal-kpi {
    border: 1px solid var(--border-light);
    border-radius: 10px;
    padding: 10px;
    background: var(--bg-body);
}

.stock-modal-kpi .label {
    font-size: 12px;
    color: var(--text-secondary);
    margin-bottom: 4px;
}

.stock-modal-kpi .value {
    font-size: 14px;
    color: var(--text-primary);
    font-weight: 600;
}

@media (max-width: 980px) {
    .stocks-page .stats-row {
        grid-template-columns: repeat(2, 1fr) !important;
    }

    .stock-form-layout {
        grid-template-columns: 1fr;
    }

    .stock-form-summary {
        position: static;
    }

    .stock-mix-grid {
        grid-template-columns: 1fr;
    }

    .stock-form-columns {
        grid-template-columns: 1fr;
    }

    .stock-modal-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 680px) {
    .stocks-page .stats-row {
        grid-template-columns: 1fr !important;
    }

    .stock-field-medium,
    .stock-field-small {
        grid-column: span 12;
    }

    .stock-modal-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
(function () {
    const productFormCard = document.getElementById('product-form-card');
    const toggleProductBtn = document.getElementById('toggle-product-form-btn');
    const stockProductForm = document.getElementById('stock-product-form');
    const productNameInput = document.getElementById('product-name');
    const productBrandInput = document.getElementById('product-brand');
    const productSupplierInput = document.getElementById('product-supplier');
    const productDosageInput = document.getElementById('product-dosage');
    const productFormeInput = document.getElementById('product-forme');
    const productPresentationInput = document.getElementById('product-presentation');
    const productPresentationChoiceSelect = document.getElementById('product-presentation-choice');
    const productPresentationOtherInput = document.getElementById('product-presentation-other');
    const productSkuPreviewInput = document.getElementById('product-sku-preview');
    const productColorInput = document.getElementById('product-color');
    const productColorChip = document.getElementById('product-color-chip');
    const productBaseUnitSelect = document.getElementById('product-base-unit');
    const productPackagingUnitSelect = document.getElementById('product-packaging-unit-code');
    const productPackagingFactorInput = document.getElementById('product-packaging-factor');
    const productPackagingLabelInput = document.getElementById('product-packaging-label');
    const productStockQuantityInput = document.getElementById('product-stock-quantity');
    const productMinStockInput = document.getElementById('product-min-stock');
    const productLotPurchaseInput = document.getElementById('product-lot-purchase-price');
    const productSalePriceInput = document.getElementById('product-sale-price');
    const productPurchaseUnitBaseInput = document.getElementById('product-purchase-unit-base');
    const productInitialLotInput = document.getElementById('product-initial-lot-code');
    const productPackagingQtyInput = document.getElementById('product-packaging-quantity');
    const productPackagingUnitPriceInput = document.getElementById('product-packaging-unit-price');
    const productSaleTotalInput = document.getElementById('product-sale-total');
    const productExpirationInput = document.getElementById('product-expiration-date');
    const stockProductSubmit = document.getElementById('stock-product-submit');
    const duplicateLotMessage = document.getElementById('duplicate-lot-message');
    const stockProductFields = stockProductForm
        ? Array.from(stockProductForm.querySelectorAll('.filter-input, .filter-select'))
        : [];
    const addLotForm = document.getElementById('stock-add-lot-form');
    const addLotExistingSelect = document.getElementById('add-lot-existing-id');
    const addLotUnitSelect = document.getElementById('add-lot-unit');
    const addLotQuantityInput = document.getElementById('add-lot-quantity');
    const addLotCodeInput = document.getElementById('add-lot-code');
    const addLotSupplierInput = document.getElementById('add-lot-supplier');
    const addLotProductNameInput = document.getElementById('add-lot-product-name');
    const addLotCurrentQuantityInput = document.getElementById('add-lot-current-quantity');
    const addLotReadonlyMetaInput = document.getElementById('add-lot-readonly-meta');
    const addLotStatusInput = document.getElementById('add-lot-status');
    const newLotModal = document.getElementById('stock-new-lot-modal');
    const newLotModalCloseBtn = document.getElementById('stock-new-lot-close');
    const productModal = document.getElementById('stock-product-modal');
    const productModalBody = document.getElementById('stock-product-modal-body');
    const productModalCloseBtn = document.getElementById('stock-product-modal-close');
    const productSummaryText = document.getElementById('product-summary-text');
    const summarySkuNode = document.getElementById('summary-sku');
    const summaryBaseUnitNode = document.getElementById('summary-base-unit');
    const summaryMinStockNode = document.getElementById('summary-min-stock');
    const summaryLotNode = document.getElementById('summary-lot');
    const summaryPurchaseUnitNode = document.getElementById('summary-purchase-unit');
    const summarySaleUnitNode = document.getElementById('summary-sale-unit');
    const summaryMarginNode = document.getElementById('summary-margin');
    const sectionButtons = Array.from(document.querySelectorAll('.stock-section-btn'));
	    const unitMap = <?= json_encode($productUnitMap, JSON_UNESCAPED_UNICODE) ?>;
	    const productMetaMap = <?= json_encode($productMetaMap, JSON_UNESCAPED_UNICODE) ?>;
	    const productDetailsMap = <?= json_encode($productDetailsMap, JSON_UNESCAPED_UNICODE) ?>;
	    const lotDetailsMap = <?= json_encode($lotDetailsMap, JSON_UNESCAPED_UNICODE) ?>;
	    const lotCatalog = <?= json_encode($lotCatalog, JSON_UNESCAPED_UNICODE) ?>;
	    const productFormFields = <?= json_encode([
	        'name' => [
	            'enabled' => true,
	            'label' => (string) (trim((string) ($pfName['label'] ?? '')) !== '' ? $pfName['label'] : 'Nom du produit'),
	        ],
	        'supplier' => [
	            'enabled' => $showSupplierField,
	            'label' => (string) (trim((string) ($pfSupplier['label'] ?? '')) !== '' ? $pfSupplier['label'] : 'Fournisseur'),
	        ],
	        'dosage' => [
	            'enabled' => $showDosageField,
	            'label' => (string) (trim((string) ($pfDosage['label'] ?? '')) !== '' ? $pfDosage['label'] : 'Spécification'),
	        ],
	        'forme' => [
	            'enabled' => $showFormeField,
	            'label' => (string) (trim((string) ($pfForme['label'] ?? '')) !== '' ? $pfForme['label'] : 'Forme'),
	        ],
	        'presentation' => [
	            'enabled' => $showPresentationField,
	            'label' => (string) (trim((string) ($pfPresentation['label'] ?? '')) !== '' ? $pfPresentation['label'] : 'Présentation'),
	        ],
	        'base_unit' => [
	            'enabled' => true,
	            'label' => (string) (trim((string) ($pfBaseUnit['label'] ?? '')) !== '' ? $pfBaseUnit['label'] : 'Unité de base'),
	        ],
	    ], JSON_UNESCAPED_UNICODE) ?>;
	    const baseUnitLabelMap = <?= json_encode(array_reduce($pfBaseUnits, static function (array $carry, $u): array {
	        if (!is_array($u)) {
	            return $carry;
	        }
	        $code = trim((string) ($u['code'] ?? ''));
	        if ($code === '') {
	            return $carry;
	        }
	        $label = trim((string) ($u['label'] ?? ''));
	        if ($label === '') {
	            $label = $code;
	        }
	        $carry[$code] = $label;
	        return $carry;
	    }, []), JSON_UNESCAPED_UNICODE) ?>;
	    const isEditingProduct = <?= is_array($editingProduct) ? 'true' : 'false' ?>;
	    const canManageStock = <?= $canManageStock ? 'true' : 'false' ?>;
	    const stockCsrfToken = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE) ?>;
	    const defaultSaleMargin = 0.3;
	    const savedMarginRateRaw = parseFloat(localStorage.getItem('stock.form.margin_rate') || '');
    const lastMarginRate = Number.isFinite(savedMarginRateRaw) ? Math.max(0, savedMarginRateRaw) : defaultSaleMargin;
    let salePriceManuallyEdited = isEditingProduct;
    let minStockManuallyEdited = isEditingProduct;
    let dosageManuallyEdited = isEditingProduct;
    let formeManuallyEdited = isEditingProduct;
    let baseUnitManuallyEdited = isEditingProduct;
    const parseNumber = (value) => {
        const n = parseFloat(String(value).replace(',', '.'));
        return Number.isFinite(n) ? n : 0;
    };

    const formatDisplay = (value, maxDecimals = 2) => {
        const formatted = new Intl.NumberFormat('fr-FR', {
            minimumFractionDigits: 0,
            maximumFractionDigits: maxDecimals,
        }).format(parseNumber(value));
        return formatted.replace(/\u202f|\u00a0/g, ' ');
    };

    const formatMoney = (value) => parseNumber(value).toFixed(2);
    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const slugify = (value) => String(value || '')
        .toUpperCase()
        .replace(/[^A-Z0-9]+/g, '')
        .slice(0, 4);

    const generateSKU = (productName) => {
        const prefix = (slugify(productName) || 'PRD').padEnd(3, 'X');
        const date = new Date();
        const ym = String(date.getFullYear()).slice(-2) + String(date.getMonth() + 1).padStart(2, '0');
        const serial = String(Math.floor(1000 + Math.random() * 9000));
        return `${prefix}-${ym}-${serial}`;
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

    const plusDays = (days) => {
        const date = new Date();
        date.setDate(date.getDate() + days);
        return date.toISOString().slice(0, 10);
    };

    const normalizeUnitCodeClient = (value) => String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9_-]+/g, '')
        .slice(0, 30);

    const normalizeText = (value) => String(value || '')
        .toLowerCase()
        .replace(/\s+/g, '');
    const buildDisplayName = (name, supplier) => {
        const baseName = String(name || '').trim();
        const supplierName = String(supplier || '').trim();
        if (baseName === '' || supplierName === '') {
            return baseName;
        }
        if (baseName.toLowerCase().includes(supplierName.toLowerCase())) {
            return baseName;
        }
        return `${baseName} - ${supplierName}`;
    };
    const normalizeDateValue = (value) => {
        const raw = String(value || '').trim();
        if (raw === '') {
            return '';
        }
        return raw.slice(0, 10);
    };
    const normalizeNumberFixed = (value, decimals = 6) => {
        const n = parseNumber(value);
        return n.toFixed(decimals);
    };
    const buildLotKey = (data) => {
        const name = normalizeText(buildDisplayName(data.product_name, data.supplier));
        const code = normalizeText(data.lot_code);
        if (name === '' || code === '') {
            return '';
        }
        const supplier = normalizeText(data.supplier);
        const expiration = normalizeDateValue(data.expiration_date);
        const unitCost = normalizeNumberFixed(data.unit_cost_base, 6);
        const qty = normalizeNumberFixed(data.quantity_initial_base, 6);
        return [name, code, supplier, expiration, unitCost, qty].join('|');
    };
    const existingLotKeys = new Set(
        Array.isArray(lotCatalog)
            ? lotCatalog.map((item) => buildLotKey(item || {})).filter((key) => key !== '')
            : []
    );

    const syncPresentationAndBaseUnit = () => {
        // Presentation is a free text field and base unit is explicitly selected by the user.
        // This function is kept to avoid breaking older client logic paths.
    };

    const inferDosageFromName = (name) => {
        const normalized = String(name || '');
        const match = normalized.match(/(\d+(?:[.,]\d+)?)\s*(mg|g|mcg|ug|ml|iu|ui|%)/i);
        if (!match) {
            return '';
        }
        return `${String(match[1] || '').replace(',', '.')} ${String(match[2] || '').toLowerCase()}`;
    };

	    const refreshProductSummary = () => {
	        if (!productSummaryText) {
	            return;
	        }
	        const nameValue = String(productNameInput?.value || '').trim() || 'Produit sans nom';
	        const supplierValue = String(productSupplierInput?.value || '').trim();
	        const dosageValue = String(productDosageInput?.value || '').trim();
	        const formeValue = String(productFormeInput?.value || '').trim();
	        const presentationValue = String(productPresentationInput?.value || '').trim();
	        const qty = parseNumber(productStockQuantityInput?.value || '0');
	        const lotPurchase = parseNumber(productLotPurchaseInput?.value || '0');
	        const salePrice = parseNumber(productSalePriceInput?.value || '0');
	        const minStock = parseNumber(productMinStockInput?.value || '0');
	        const purchaseUnit = parseNumber(productPurchaseUnitBaseInput?.value || '0');
	        const baseUnit = String(productBaseUnitSelect?.value || 'unite');
	        const sku = String(productSkuPreviewInput?.value || '-').trim() || '-';
	        const lotCode = String(productInitialLotInput?.value || '').trim();
	        const marginRate = purchaseUnit > 0 ? ((salePrice - purchaseUnit) / purchaseUnit) * 100 : 0;

	        const getFieldLabel = (key, fallback) => String(productFormFields?.[key]?.label || fallback).trim() || fallback;
	        const isFieldEnabled = (key) => !!productFormFields?.[key]?.enabled;

	        const baseUnitDisplay = String(baseUnitLabelMap?.[baseUnit] || baseUnit || '-').trim() || '-';

	        const productChunks = [
	            `${getFieldLabel('name', 'Nom du produit')}: ${nameValue}`,
	            isFieldEnabled('supplier') && supplierValue !== '' ? `${getFieldLabel('supplier', 'Fournisseur')}: ${supplierValue}` : '',
	            isFieldEnabled('dosage') && dosageValue !== '' ? `${getFieldLabel('dosage', 'Spécification')}: ${dosageValue}` : '',
	            isFieldEnabled('forme') && formeValue !== '' ? `${getFieldLabel('forme', 'Forme')}: ${formeValue}` : '',
	            isFieldEnabled('presentation') && presentationValue !== '' ? `${getFieldLabel('presentation', 'Présentation')}: ${presentationValue}` : '',
	            `${getFieldLabel('base_unit', 'Unité de base')}: ${baseUnitDisplay}`,
	        ].filter((chunk) => chunk !== '');

	        const sentenceParts = [
	            `${productChunks.join(' — ')}.`,
	            `Lot initial ${formatDisplay(qty, 2)} ${baseUnitDisplay}.`,
	            `Achat lot ${formatDisplay(lotPurchase, 2)} USD, vente unitaire ${formatDisplay(salePrice, 2)} USD.`,
	        ].filter((chunk) => chunk !== '');

	        productSummaryText.textContent = sentenceParts.join(' ');

	        if (summarySkuNode) {
	            summarySkuNode.textContent = sku;
        }
	        if (summaryBaseUnitNode) {
	            summaryBaseUnitNode.textContent = baseUnitDisplay || '-';
	        }
        if (summaryMinStockNode) {
            summaryMinStockNode.textContent = formatDisplay(minStock, 2);
        }
        if (summaryLotNode) {
            summaryLotNode.textContent = lotCode !== '' ? lotCode : '-';
        }
        if (summaryPurchaseUnitNode) {
            summaryPurchaseUnitNode.textContent = `${formatDisplay(purchaseUnit, 2)} USD`;
        }
        if (summarySaleUnitNode) {
            summarySaleUnitNode.textContent = `${formatDisplay(salePrice, 2)} USD`;
        }
        if (summaryMarginNode) {
            summaryMarginNode.textContent = `${formatDisplay(marginRate, 1)}%`;
        }
    };

    const setSectionVisible = (sectionId, visible) => {
        const section = document.getElementById(sectionId);
        if (!section) {
            return;
        }
        section.classList.toggle('is-collapsed', !visible);
        const btn = sectionButtons.find((node) => node.dataset.target === sectionId);
        if (btn) {
            btn.classList.toggle('is-active', visible);
        }
    };

    sectionButtons.forEach((button) => {
        const sectionId = button.dataset.target || '';
        if (sectionId === '') {
            return;
        }
        button.addEventListener('click', () => {
            const section = document.getElementById(sectionId);
            if (!section) {
                return;
            }
            const isCurrentlyVisible = !section.classList.contains('is-collapsed');
            setSectionVisible(sectionId, !isCurrentlyVisible);
            if (!isCurrentlyVisible) {
                section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    const openNewLotModal = () => {
        if (!newLotModal) {
            return;
        }
        newLotModal.classList.add('open');
        newLotModal.setAttribute('aria-hidden', 'false');
    };

    const closeNewLotModal = () => {
        if (!newLotModal) {
            return;
        }
        newLotModal.classList.remove('open');
        newLotModal.setAttribute('aria-hidden', 'true');
    };

    if (toggleProductBtn) {
        toggleProductBtn.addEventListener('click', () => {
            openNewLotModal();
        });
    }

    if (newLotModalCloseBtn) {
        newLotModalCloseBtn.addEventListener('click', closeNewLotModal);
    }

    if (newLotModal) {
        newLotModal.addEventListener('click', (event) => {
            if (event.target === newLotModal) {
                closeNewLotModal();
            }
        });
    }

    if (isEditingProduct) {
        openNewLotModal();
    }

    const refreshColorChip = () => {
        if (!productColorInput || !productColorChip) {
            return;
        }
        productColorChip.style.background = productColorInput.value || '#0EA5E9';
    };

    const recalcProductValues = () => {
        const quantity = parseNumber(productStockQuantityInput?.value || '0');
        const factor = parseNumber(productPackagingFactorInput?.value || '0');
        const lotPurchasePrice = parseNumber(productLotPurchaseInput?.value || '0');
        const packagingCode = String(productPackagingUnitSelect?.value || '');
        const packagingLabel = productPackagingUnitSelect?.selectedOptions?.[0]?.textContent?.trim() || packagingCode;
        const baseUnitCode = String(productBaseUnitSelect?.value || 'unite');
        const qtyInPackaging = factor > 0 ? quantity / factor : 0;
        const purchaseBase = quantity > 0 ? (lotPurchasePrice / quantity) : 0;

        if (productPackagingQtyInput) {
            productPackagingQtyInput.value = qtyInPackaging > 0 ? qtyInPackaging.toFixed(3) : '0';
        }

        if (productPackagingLabelInput) {
            if (factor > 0 && packagingCode !== '') {
                productPackagingLabelInput.value = `${Math.floor(qtyInPackaging)} ${packagingLabel} de ${factor}${baseUnitCode}`;
            } else {
                productPackagingLabelInput.value = '';
            }
        }

        if (productPackagingUnitPriceInput) {
            const unitCond = qtyInPackaging > 0 ? (lotPurchasePrice / qtyInPackaging) : 0;
            productPackagingUnitPriceInput.value = unitCond.toFixed(2);
        }

        if (productPurchaseUnitBaseInput) {
            if (isEditingProduct) {
                productPurchaseUnitBaseInput.value = parseNumber(productPurchaseUnitBaseInput.value || '0').toFixed(2);
            } else {
                productPurchaseUnitBaseInput.value = purchaseBase.toFixed(2);
            }
        }

        if (productSalePriceInput) {
            const currentSale = parseNumber(productSalePriceInput.value || '0');
            const suggestedSale = purchaseBase > 0 ? purchaseBase * (1 + lastMarginRate) : 0;
            if (!salePriceManuallyEdited || currentSale <= 0) {
                setInputValue(productSalePriceInput, suggestedSale.toFixed(2));
            }
        }

        if (productMinStockInput) {
            const currentMinStock = parseNumber(productMinStockInput.value || '0');
            const suggestedMinStock = quantity > 0 ? Math.max(1, Math.ceil((quantity * 0.2) * 100) / 100) : 0;
            if (!minStockManuallyEdited || currentMinStock <= 0) {
                setInputValue(productMinStockInput, suggestedMinStock.toFixed(2));
            }
        }

        const saleUnitPrice = parseNumber(productSalePriceInput?.value || '0');
        if (productSaleTotalInput) {
            productSaleTotalInput.value = (saleUnitPrice * quantity).toFixed(2);
        }
        refreshProductSummary();
        refreshLiveValidation();
    };

    const setInvalidState = (input, invalid) => {
        if (!input) {
            return;
        }
        input.classList.toggle('is-invalid', invalid);
        if (invalid) {
            input.setAttribute('aria-invalid', 'true');
        } else {
            input.removeAttribute('aria-invalid');
        }
    };

    const setFieldError = (input, message) => {
        if (!stockProductForm || !input || !input.id) {
            return;
        }
        const errorEl = stockProductForm.querySelector(`.field-error[data-error-for="${input.id}"]`);
        if (!errorEl) {
            return;
        }
        const text = String(message || '').trim();
        if (text === '') {
            errorEl.textContent = '';
            errorEl.classList.remove('is-visible');
        } else {
            errorEl.textContent = text;
            errorEl.classList.add('is-visible');
        }
    };

    const setFormMessage = (message) => {
        if (!duplicateLotMessage) {
            return;
        }
        const text = String(message || '').trim();
        if (text === '') {
            duplicateLotMessage.textContent = '';
            duplicateLotMessage.classList.remove('is-visible');
        } else {
            duplicateLotMessage.textContent = text;
            duplicateLotMessage.classList.add('is-visible');
        }
    };

    const refreshLiveValidation = () => {
        if (!stockProductForm) {
            return { duplicate: false, saleBelowCost: false, expirationTooSoon: false };
        }

        const purchaseUnit = parseNumber(productPurchaseUnitBaseInput?.value || '0');
        const salePrice = parseNumber(productSalePriceInput?.value || '0');
        const saleBelowCost = purchaseUnit > 0 && salePrice < purchaseUnit;

        // Peremption information is informational only in the client. Do not block creation when expiration is within 6 months.
        let expirationTooSoon = false;
        const expirationRaw = String(productExpirationInput?.value || '').trim();
        if (!isEditingProduct && expirationRaw !== '') {
            const expirationDate = new Date(`${expirationRaw}T00:00:00`);
            if (!Number.isNaN(expirationDate.getTime())) {
                const threshold = new Date();
                threshold.setMonth(threshold.getMonth() + 6);
                threshold.setHours(0, 0, 0, 0);
                expirationTooSoon = expirationDate <= threshold;
            }
        }

        const lastFieldComplete = !isEditingProduct
            && productInitialLotInput
            && String(productInitialLotInput.value || '').trim() !== '';

        let duplicate = false;
        if (!isEditingProduct && lastFieldComplete) {
            const name = normalizeText(buildDisplayName(productNameInput?.value || '', productSupplierInput?.value || ''));
            const lotCode = normalizeText(productInitialLotInput?.value || '');
            const qty = parseNumber(productStockQuantityInput?.value || '0');
            if (name !== '' && lotCode !== '' && qty > 0 && purchaseUnit > 0) {
                const key = [
                    name,
                    lotCode,
                    normalizeText(productSupplierInput?.value || ''),
                    normalizeDateValue(productExpirationInput?.value || ''),
                    normalizeNumberFixed(purchaseUnit, 6),
                    normalizeNumberFixed(qty, 6),
                ].join('|');
                duplicate = existingLotKeys.has(key);
            }
        }

        const duplicateActive = duplicate && lastFieldComplete;

        stockProductFields.forEach((field) => setInvalidState(field, false));
        setInvalidState(productSalePriceInput, saleBelowCost);
        setInvalidState(productLotPurchaseInput, saleBelowCost);
        // Do not mark expiration as invalid on client-side
        setInvalidState(productExpirationInput, false);

        if (duplicateActive) {
            stockProductFields.forEach((field) => setInvalidState(field, true));
        }

        setFieldError(productSalePriceInput, saleBelowCost ? "Le prix de vente doit etre >= au prix d'achat." : '');
        setFieldError(productLotPurchaseInput, saleBelowCost ? "Le prix d'achat ne peut pas depasser le prix de vente." : '');
        // Do not show expiration error on client-side
        setFieldError(productExpirationInput, '');
        setFieldError(productInitialLotInput, duplicateActive ? 'Un lot identique existe deja.' : '');
        setFieldError(productNameInput, duplicateActive ? 'Un lot identique existe deja.' : '');
        setFormMessage(duplicateActive
            ? 'Ce produit existe deja. Ajoutez simplement la quantite au lot existant.'
            : '');

        const hasError = duplicateActive || saleBelowCost;
        if (stockProductSubmit) {
            stockProductSubmit.disabled = hasError;
        }

        return { duplicate: duplicateActive, saleBelowCost, expirationTooSoon };
    };

    const syncSmartIdentityFromName = () => {
        const name = String(productNameInput?.value || '');
        if (!dosageManuallyEdited && productDosageInput && String(productDosageInput.value || '').trim() === '') {
            const dosage = inferDosageFromName(name);
            if (dosage !== '') {
                setInputValue(productDosageInput, dosage);
            }
        }
    };

    const refreshProductSku = () => {
        if (isEditingProduct || !productSkuPreviewInput || !productNameInput) {
            return;
        }
        productSkuPreviewInput.value = generateSKU(productNameInput.value || '');
    };

    const formatFactor = (value) => {
        const formatted = Number(value || 1).toFixed(6);
        return formatted.replace(/\.?0+$/, '');
    };

    const getUnitFactor = (productId, unitCode) => {
        const units = Array.isArray(unitMap[String(productId)]) ? unitMap[String(productId)] : [];
        const normalizedCode = String(unitCode || '').toLowerCase();
        const match = units.find((unit) => String(unit.unit_code || '').toLowerCase() === normalizedCode);
        return parseNumber(match?.factor_to_base || 1);
    };

    const getSelectedLotData = () => {
        const lotId = String(addLotExistingSelect?.value || '');
        if (lotId === '') {
            return null;
        }
        return lotDetailsMap[lotId] || null;
    };

    const refreshLotUnitOptions = () => {
        if (!addLotExistingSelect || !addLotUnitSelect) {
            return;
        }

        const selectedLot = getSelectedLotData();
        const productId = selectedLot ? String(selectedLot.product_id || '') : '';
        const units = Array.isArray(unitMap[productId]) ? unitMap[productId] : [];
        const options = ['<option value="">Choisir l unite...</option>'];

        units.forEach((unit) => {
            const code = String(unit.unit_code || '');
            if (code === '') {
                return;
            }
            const label = String(unit.unit_label || code);
            const suffix = unit.is_base ? ' (base)' : ` x${formatFactor(unit.factor_to_base)}`;
            options.push(`<option value="${code}">${label}${suffix}</option>`);
        });

        addLotUnitSelect.innerHTML = options.join('');
        if (units.length === 1 && units[0] && units[0].unit_code) {
            addLotUnitSelect.value = String(units[0].unit_code);
        }
        if (!addLotUnitSelect.value && units.length > 0) {
            const base = units.find((unit) => unit && unit.is_base);
            if (base && base.unit_code) {
                addLotUnitSelect.value = String(base.unit_code);
            }
        }
    };

    const refreshSelectedLotSummary = () => {
        const selectedLot = getSelectedLotData();
        const actionTemplate = String(addLotForm?.dataset.actionTemplate || '/stock/lots/{id}/update');
        if (addLotForm) {
            addLotForm.action = selectedLot ? actionTemplate.replace('{id}', String(selectedLot.id || 0)) : '/stock/lots/0/update';
        }

        if (!selectedLot) {
            if (addLotStatusInput) {
                addLotStatusInput.value = '-';
            }
            if (addLotProductNameInput) {
                addLotProductNameInput.value = '-';
            }
            if (addLotCodeInput) {
                addLotCodeInput.value = '-';
            }
            if (addLotSupplierInput) {
                addLotSupplierInput.value = '-';
            }
            if (addLotCurrentQuantityInput) {
                addLotCurrentQuantityInput.value = '-';
            }
            if (addLotReadonlyMetaInput) {
                addLotReadonlyMetaInput.value = '-';
            }
            return;
        }

        const sku = String(selectedLot.product_sku || '').trim();
        const expiration = String(selectedLot.expiration_date || '').trim();
        const expirationLabel = expiration !== '' ? expiration : 'Sans expiration';
        const costLabel = `${formatDisplay(selectedLot.unit_cost_base || 0, 6)} USD/${selectedLot.base_unit || 'unite'}`;

        if (addLotStatusInput) {
            if (selectedLot.is_expired) {
                addLotStatusInput.value = 'Perime';
            } else if (selectedLot.is_in_peremption) {
                addLotStatusInput.value = 'Peremption';
            } else {
                addLotStatusInput.value = 'Actif';
            }
        }
        if (addLotProductNameInput) {
            addLotProductNameInput.value = sku !== ''
                ? `${selectedLot.product_name || '-'} (${sku})`
                : String(selectedLot.product_name || '-');
        }
        if (addLotCodeInput) {
            addLotCodeInput.value = String(selectedLot.lot_code || '-');
        }
        if (addLotSupplierInput) {
            addLotSupplierInput.value = String(selectedLot.supplier || '-');
        }
        if (addLotCurrentQuantityInput) {
            addLotCurrentQuantityInput.value = `${formatDisplay(selectedLot.quantity_remaining_base || 0, 2)} ${selectedLot.base_unit || 'unite'}`;
        }
        if (addLotReadonlyMetaInput) {
            addLotReadonlyMetaInput.value = `${expirationLabel} | ${costLabel}`;
        }
    };

    if (stockProductForm) {
        if (!isEditingProduct) {
            const lastBrand = String(localStorage.getItem('stock.form.last_brand') || '').trim();
            if (productBrandInput && lastBrand !== '' && String(productBrandInput.value || '').trim() === '') {
                setInputValue(productBrandInput, lastBrand);
            }
        }

        refreshColorChip();
        refreshProductSku();
        syncSmartIdentityFromName();
        recalcProductValues();

        [productStockQuantityInput, productPackagingFactorInput, productPackagingUnitSelect, productBaseUnitSelect, productLotPurchaseInput].forEach((node) => {
            if (!node) {
                return;
            }
            node.addEventListener('input', recalcProductValues);
            node.addEventListener('change', recalcProductValues);
        });

        if (productSalePriceInput) {
            productSalePriceInput.addEventListener('input', () => {
                if (wasUserEdited(productSalePriceInput)) {
                    salePriceManuallyEdited = true;
                }
                recalcProductValues();
            });
            productSalePriceInput.addEventListener('change', () => {
                if (wasUserEdited(productSalePriceInput)) {
                    salePriceManuallyEdited = true;
                }
                recalcProductValues();
            });
        }
        if (productMinStockInput) {
            productMinStockInput.addEventListener('input', () => {
                if (wasUserEdited(productMinStockInput)) {
                    minStockManuallyEdited = true;
                }
            });
            productMinStockInput.addEventListener('change', () => {
                if (wasUserEdited(productMinStockInput)) {
                    minStockManuallyEdited = true;
                }
            });
        }
        if (productDosageInput) {
            productDosageInput.addEventListener('input', () => {
                if (wasUserEdited(productDosageInput)) {
                    dosageManuallyEdited = true;
                }
            });
            productDosageInput.addEventListener('change', () => {
                if (wasUserEdited(productDosageInput)) {
                    dosageManuallyEdited = true;
                }
            });
        }
        if (productFormeInput) {
            productFormeInput.addEventListener('input', () => {
                if (wasUserEdited(productFormeInput)) {
                    formeManuallyEdited = true;
                }
                syncSmartIdentityFromName();
                recalcProductValues();
            });
            productFormeInput.addEventListener('change', () => {
                if (wasUserEdited(productFormeInput)) {
                    formeManuallyEdited = true;
                }
                syncSmartIdentityFromName();
                recalcProductValues();
            });
        }
        if (productPresentationInput) {
            productPresentationInput.addEventListener('input', recalcProductValues);
            productPresentationInput.addEventListener('change', recalcProductValues);
        }
        if (productBaseUnitSelect) {
            productBaseUnitSelect.addEventListener('input', () => {
                if (wasUserEdited(productBaseUnitSelect)) {
                    baseUnitManuallyEdited = true;
                }
            });
            productBaseUnitSelect.addEventListener('change', () => {
                if (wasUserEdited(productBaseUnitSelect)) {
                    baseUnitManuallyEdited = true;
                }
            });
        }

        if (productNameInput) {
            productNameInput.addEventListener('input', () => {
                refreshProductSku();
                syncSmartIdentityFromName();
                recalcProductValues();
            });
        }
        if (productSupplierInput) {
            productSupplierInput.addEventListener('input', refreshLiveValidation);
            productSupplierInput.addEventListener('change', refreshLiveValidation);
        }
        if (productColorInput) {
            productColorInput.addEventListener('input', refreshColorChip);
            productColorChip.addEventListener('change', refreshColorChip);
        }
        if (productInitialLotInput) {
            productInitialLotInput.addEventListener('input', () => {
                refreshProductSummary();
                refreshLiveValidation();
            });
            productInitialLotInput.addEventListener('change', () => {
                refreshProductSummary();
                refreshLiveValidation();
            });
        }
        if (productExpirationInput) {
            productExpirationInput.addEventListener('input', refreshLiveValidation);
            productExpirationInput.addEventListener('change', refreshLiveValidation);
        }
        stockProductForm.addEventListener('submit', (event) => {
            const liveState = refreshLiveValidation();
            if (liveState.duplicate) {
                event.preventDefault();
                window.alert('Un lot identique existe deja dans la base.');
                return;
            }
            if (liveState.saleBelowCost) {
                event.preventDefault();
                window.alert('Le prix de vente ne peut pas etre inferieur au prix d achat.');
                return;
            }
            // expirationTooSoon check removed: peremption is informational only
            const name = String(productNameInput?.value || '').trim();
            const qty = parseNumber(productStockQuantityInput?.value || '0');
            const salePrice = parseNumber(productSalePriceInput?.value || '0');
            const purchaseLot = parseNumber(productLotPurchaseInput?.value || '0');
            const purchaseUnit = parseNumber(productPurchaseUnitBaseInput?.value || '0');
            const lotCode = String(productInitialLotInput?.value || '').trim();

            if (name === '') {
                event.preventDefault();
                window.alert('Le nom du produit est obligatoire.');
                return;
            }
            if (productBaseUnitSelect && String(productBaseUnitSelect.value || '').trim() === '') {
                event.preventDefault();
                window.alert('L unite de base est obligatoire.');
                return;
            }
            if (productPresentationInput && productPresentationInput.required && String(productPresentationInput.value || '').trim() === '') {
                event.preventDefault();
                window.alert('La presentation est obligatoire.');
                return;
            }
            if (productSupplierInput && productSupplierInput.required && String(productSupplierInput.value || '').trim() === '') {
                event.preventDefault();
                window.alert('Le fournisseur est obligatoire.');
                return;
            }
            if (productDosageInput && productDosageInput.required && String(productDosageInput.value || '').trim() === '') {
                event.preventDefault();
                window.alert('La specification est obligatoire.');
                return;
            }
            if (productFormeInput && productFormeInput.required && String(productFormeInput.value || '').trim() === '') {
                event.preventDefault();
                window.alert('La forme est obligatoire.');
                return;
            }
            if (!isEditingProduct && qty <= 0) {
                event.preventDefault();
                window.alert('La quantite doit etre superieure a 0.');
                return;
            }
            if (!isEditingProduct && qty > 0 && lotCode === '') {
                event.preventDefault();
                window.alert('Le numero de lot est obligatoire pour le stock initial.');
                return;
            }
            if (salePrice < 0 || purchaseLot < 0) {
                event.preventDefault();
                window.alert('Les prix doivent etre >= 0.');
                return;
            }
            if (purchaseUnit > 0 && salePrice < purchaseUnit) {
                event.preventDefault();
                window.alert('Le prix de vente ne peut pas etre inferieur au prix d achat.');
                return;
            }

            if (productBrandInput) {
                const brand = String(productBrandInput.value || '').trim();
                if (brand !== '') {
                    localStorage.setItem('stock.form.last_brand', brand);
                }
            }
            if (purchaseUnit > 0 && salePrice > 0) {
                const marginRate = Math.max(0, (salePrice - purchaseUnit) / purchaseUnit);
                localStorage.setItem('stock.form.margin_rate', String(marginRate));
            }
        });
    }

    if (addLotExistingSelect && addLotUnitSelect) {
        addLotExistingSelect.addEventListener('change', () => {
            refreshLotUnitOptions();
            refreshSelectedLotSummary();
        });
        refreshLotUnitOptions();
        refreshSelectedLotSummary();
    }

    if (addLotForm) {
        addLotForm.addEventListener('submit', (event) => {
            const selectedLot = getSelectedLotData();
            const qty = parseNumber(addLotQuantityInput?.value || '0');
            const lotCode = String(addLotCodeInput?.value || '').trim();
            if (!selectedLot) {
                event.preventDefault();
                window.alert('Selectionnez d abord un lot existant.');
                return;
            }
            if (qty <= 0) {
                event.preventDefault();
                window.alert('La quantite du lot doit etre superieure a 0.');
                return;
            }
            if (lotCode === '' || lotCode === '-') {
                event.preventDefault();
                window.alert('Le lot selectionne est invalide.');
                return;
            }
            if (selectedLot.is_expired) {
                event.preventDefault();
                window.alert('Ce lot est perime. Ajout impossible.');
                return;
            }
        });
    }

	    const openProductModal = (productId) => {
	        if (!productModal || !productModalBody) {
	            return;
	        }

        const data = productDetailsMap[String(productId)] || null;
        if (!data) {
            return;
        }

        const color = String(data.color_hex || '').trim();
        const lots = Array.isArray(data.lots) ? data.lots : [];
        const lotsRows = lots.length === 0
            ? '<tr><td colspan="10" style="text-align:center;color:var(--text-secondary);padding:12px;">Aucun lot enregistre.</td></tr>'
            : lots.map((lot) => {
                const lotId = Number(lot.id || 0);
                const expirationRaw = String(lot.expiration_date || '').trim();
                const expirationDisplay = expirationRaw !== ''
                    ? expirationRaw
                    : '-';
                const isExpired = !!lot.is_expired;
                const actionCell = canManageStock
                    ? `<div style="display:flex;gap:6px;align-items:center;">
                                <form method="POST" action="/stock/lots/${lotId}/declass" class="js-declass-lot-form-inline" data-lot-code="${escapeHtml(lot.lot_code || '')}" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="${escapeHtml(stockCsrfToken)}">
                                    <button type="submit" class="btn-icon btn-icon-warning" title="Declassement du lot">
                                        <i class="fa-solid fa-box-archive"></i>
                                    </button>
                                </form>
                                <form method="POST" action="/stock/lots/${lotId}/delete" class="js-delete-lot-form-inline" data-lot-code="${escapeHtml(lot.lot_code || '')}" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="${escapeHtml(stockCsrfToken)}">
                                    <button type="submit" class="btn btn-soft btn-xs btn-icon-danger">Supprimer</button>
                                </form>
                            </div>`
                    : '<span class="text-secondary">Lecture seule</span>';
                const isPeremption = !!lot.is_in_peremption && !isExpired;
                return `
                    <tr class="${isExpired ? 'stock-lot-expired' : (isPeremption ? 'stock-lot-peremption' : '')}">
                        <td>${escapeHtml(lot.lot_code || '-')}</td>
                        <td>${escapeHtml(lot.supplier || '-')}</td>
                        <td>${escapeHtml(lot.source_type || '-')}</td>
                        <td>${formatDisplay(lot.quantity_initial_base || 0, 2)}</td>
                        <td>${formatDisplay(lot.quantity_remaining_base || 0, 2)} ${escapeHtml(lot.base_unit || data.base_unit || '')}</td>
                        <td>$${formatDisplay(lot.unit_cost_base || 0, 6)}</td>
                        <td>${escapeHtml(expirationDisplay)}</td>
                        <td><span class="lot-status ${isExpired ? 'lot-status-expired' : (isPeremption ? 'lot-status-peremption' : 'lot-status-ok')}">${isExpired ? 'Perime' : (isPeremption ? 'Peremption' : 'Actif')}</span></td>
                        <td>${escapeHtml((lot.opened_at || '').replace('T', ' '))}</td>
                        <td>${actionCell}</td>
                    </tr>
                `;
            }).join('');

        const colorHtml = color !== ''
            ? `<span style="display:inline-block;width:16px;height:16px;border-radius:999px;background:${escapeHtml(color)};border:1px solid var(--border-light);vertical-align:middle;"></span> <span>${escapeHtml(color)}</span>`
            : '<span class="text-secondary">-</span>';

	        const packagingLabel = String(data.packaging_unit || '').trim() !== ''
	            ? `${escapeHtml(data.packaging_unit)} (x${formatDisplay(data.packaging_factor || 0, 3)})`
	            : '-';

	        const supplierKpi = productFormFields?.supplier?.enabled
	            ? `<div class="stock-modal-kpi"><div class="label">${escapeHtml(productFormFields?.supplier?.label || 'Fournisseur')}</div><div class="value">${escapeHtml(data.supplier || '-')}</div></div>`
	            : '';
	        const dosageKpi = productFormFields?.dosage?.enabled
	            ? `<div class="stock-modal-kpi"><div class="label">${escapeHtml(productFormFields?.dosage?.label || 'Spécification')}</div><div class="value">${escapeHtml(data.dosage || '-')}</div></div>`
	            : '';
	        const formeKpi = productFormFields?.forme?.enabled
	            ? `<div class="stock-modal-kpi"><div class="label">${escapeHtml(productFormFields?.forme?.label || 'Forme')}</div><div class="value">${escapeHtml(data.forme || '-')}</div></div>`
	            : '';
	        const presentationKpi = productFormFields?.presentation?.enabled
	            ? `<div class="stock-modal-kpi"><div class="label">${escapeHtml(productFormFields?.presentation?.label || 'Présentation')}</div><div class="value">${escapeHtml(data.presentation || '-')}</div></div>`
	            : '';

	        productModalBody.innerHTML = `
	            <div class="stock-modal-grid">
	                <div class="stock-modal-kpi"><div class="label">Produit</div><div class="value">${escapeHtml(data.name || '-')}</div></div>
	                <div class="stock-modal-kpi"><div class="label">SKU</div><div class="value">${escapeHtml(data.sku || '-')}</div></div>
	                <div class="stock-modal-kpi"><div class="label">Marque</div><div class="value">${escapeHtml(data.brand || '-')}</div></div>
	                ${supplierKpi}
	                ${dosageKpi}
	                ${formeKpi}
	                ${presentationKpi}
	                <div class="stock-modal-kpi"><div class="label">Couleur</div><div class="value">${colorHtml}</div></div>
	                <div class="stock-modal-kpi"><div class="label">Unite base</div><div class="value">${escapeHtml(data.base_unit || '-')}</div></div>
	                <div class="stock-modal-kpi"><div class="label">Conditionnement</div><div class="value">${packagingLabel}</div></div>
	                <div class="stock-modal-kpi"><div class="label">Stock actuel</div><div class="value">${formatDisplay(data.quantity || 0, 2)} ${escapeHtml(data.base_unit || '')}</div></div>
	                <div class="stock-modal-kpi"><div class="label">Seuil mini</div><div class="value">${formatDisplay(data.min_stock || 0, 2)}</div></div>
	                <div class="stock-modal-kpi"><div class="label">Prix achat / vente</div><div class="value">$${formatDisplay(data.purchase_price || 0, 2)} / $${formatDisplay(data.sale_price || 0, 2)}</div></div>
	                <div class="stock-modal-kpi"><div class="label">Expiration produit</div><div class="value">${escapeHtml(String(data.expiration_date || '').trim() || '-')}</div></div>
	            </div>
	            <h4 style="margin:0 0 8px 0;">Lots du produit (FEFO: expiration la plus proche en premier)</h4>
	            <table class="table">
	                <thead>
	                    <tr>
	                        <th>N° lot</th>
	                        <th>Fournisseur</th>
	                        <th>Source</th>
	                        <th>Qté initiale</th>
	                        <th>Qté restante</th>
	                        <th>Cout unitaire base</th>
	                        <th>Expiration</th>
	                        <th>Statut</th>
	                        <th>Date ouverture</th>
	                        <th>Action</th>
	                    </tr>
	                </thead>
	                <tbody>${lotsRows}</tbody>
	            </table>
	        `;

        productModal.classList.add('open');
        productModal.setAttribute('aria-hidden', 'false');

        productModalBody.querySelectorAll('.js-delete-lot-form-inline').forEach((form) => {
            form.addEventListener('submit', (event) => {
                const lotCode = form.getAttribute('data-lot-code') || 'ce lot';
                const ok = window.confirm(`Supprimer ${lotCode} ? Le stock restant de ce lot sera retire.`);
                if (!ok) {
                    event.preventDefault();
                }
            });
        });

        productModalBody.querySelectorAll('.js-declass-lot-form-inline').forEach((form) => {
            form.addEventListener('submit', (event) => {
                const lotCode = form.getAttribute('data-lot-code') || 'ce lot';
                const ok = window.confirm(`Declasser ${lotCode} ? Ce lot sera retire du stock actif.`);
                if (!ok) {
                    event.preventDefault();
                }
            });
        });
    };

    const closeProductModal = () => {
        if (!productModal) {
            return;
        }
        productModal.classList.remove('open');
        productModal.setAttribute('aria-hidden', 'true');
    };

    document.querySelectorAll('.js-open-product-modal').forEach((button) => {
        button.addEventListener('click', () => {
            const productId = button.getAttribute('data-product-id') || '';
            openProductModal(productId);
        });
    });

    if (productModalCloseBtn) {
        productModalCloseBtn.addEventListener('click', closeProductModal);
    }

    if (productModal) {
        productModal.addEventListener('click', (event) => {
            if (event.target === productModal) {
                closeProductModal();
            }
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }
        if (productModal && productModal.classList.contains('open')) {
            closeProductModal();
        }
        if (newLotModal && newLotModal.classList.contains('open')) {
            closeNewLotModal();
        }
    });

    document.querySelectorAll('.js-delete-product-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const productName = form.getAttribute('data-product-name') || 'ce produit';
            const ok = window.confirm(`Supprimer ${productName} du stock actif ?`);
            if (!ok) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('.js-delete-lot-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const lotCode = form.getAttribute('data-lot-code') || 'ce lot';
            const ok = window.confirm(`Supprimer ${lotCode} ? Le stock restant de ce lot sera retire.`);
            if (!ok) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('.js-declass-lot-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const lotCode = form.getAttribute('data-lot-code') || 'ce lot';
            const ok = window.confirm(`Declasser ${lotCode} ? Ce lot sera retire du stock actif.`);
            if (!ok) {
                event.preventDefault();
            }
        });
    });

    const bindBulkSelection = ({
        formId,
        selectAllId,
        checkboxSelector,
        submitBtnId,
        emptyMessage,
        confirmMessage,
    }) => {
        const form = document.getElementById(formId);
        const selectAll = document.getElementById(selectAllId);
        const submitBtn = document.getElementById(submitBtnId);
        const checkboxes = Array.from(document.querySelectorAll(checkboxSelector));

        if (!form || !submitBtn || checkboxes.length === 0) {
            return;
        }

        const syncState = () => {
            const selectedCount = checkboxes.filter((box) => box.checked).length;
            submitBtn.disabled = selectedCount <= 0;
            if (selectAll) {
                selectAll.checked = selectedCount > 0 && selectedCount === checkboxes.length;
                selectAll.indeterminate = selectedCount > 0 && selectedCount < checkboxes.length;
            }
        };

        if (selectAll) {
            selectAll.addEventListener('change', () => {
                checkboxes.forEach((box) => {
                    box.checked = selectAll.checked;
                });
                syncState();
            });
        }

        checkboxes.forEach((box) => {
            box.addEventListener('change', syncState);
        });

        form.addEventListener('submit', (event) => {
            const selectedCount = checkboxes.filter((box) => box.checked).length;
            if (selectedCount <= 0) {
                event.preventDefault();
                window.alert(emptyMessage);
                return;
            }
            const ok = window.confirm(confirmMessage);
            if (!ok) {
                event.preventDefault();
            }
        });

        syncState();
    };

    bindBulkSelection({
        formId: 'stock-products-bulk-form',
        selectAllId: 'stock-products-select-all',
        checkboxSelector: '.stock-product-checkbox',
        submitBtnId: 'stock-products-bulk-delete-btn',
        emptyMessage: 'Selectionnez au moins un produit.',
        confirmMessage: 'Supprimer les produits selectionnes du stock actif ?',
    });

    bindBulkSelection({
        formId: 'stock-lots-bulk-form',
        selectAllId: 'stock-lots-select-all',
        checkboxSelector: '.stock-lot-checkbox',
        submitBtnId: 'stock-lots-bulk-delete-btn',
        emptyMessage: 'Selectionnez au moins un lot.',
        confirmMessage: 'Supprimer les lots selectionnes ? Le stock restant de ces lots sera retire.',
    });
})();
</script>
