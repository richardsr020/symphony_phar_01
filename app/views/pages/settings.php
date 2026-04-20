<?php
$user = $currentUser ?? [];
$companyUsers = $companyUsers ?? [];
$activeTab = (string) ($_GET['tab'] ?? 'profile');
$allowedTabs = ['profile', 'company', 'stock', 'team', 'users', 'fiscal', 'security'];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'profile';
}

$role = \App\Core\RolePermissions::normalizeRole((string) ($user['role'] ?? ''));
$isAdmin = $role === \App\Core\RolePermissions::ROLE_ADMIN;
$roleLabel = \App\Core\RolePermissions::label($role);
$fullName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
$currentAvatar = trim((string) ($user['avatar'] ?? ''));
if ($currentAvatar === '') {
    $currentAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($fullName !== '' ? $fullName : 'Utilisateur') . '&background=0F9D58&color=fff&size=80';
}

$company = $company ?? [];
$aiPrompts = $aiPrompts ?? [];
$aiKnowledge = $aiKnowledge ?? [];
$company = [
    'name' => (string) ($company['name'] ?? ($user['company_name'] ?? 'Entreprise')),
    'legal_name' => (string) ($company['legal_name'] ?? ($user['company_name'] ?? 'Entreprise')),
    'tax_id' => (string) ($company['tax_id'] ?? ''),
    'email' => (string) ($company['email'] ?? ''),
    'phone' => (string) ($company['phone'] ?? ''),
    'address' => (string) ($company['address'] ?? ''),
    'city' => (string) ($company['city'] ?? ''),
    'country' => (string) ($company['country'] ?? 'RDC'),
    'currency' => (string) ($company['currency'] ?? 'USD'),
    'invoice_logo_url' => (string) ($company['invoice_logo_url'] ?? ''),
    'invoice_brand_color' => (string) ($company['invoice_brand_color'] ?? '#0F172A'),
    'default_tax_rate' => (float) ($company['default_tax_rate'] ?? 0),
    'fiscal_year_start' => (string) ($company['fiscal_year_start'] ?? date('Y-01-01')),
    'fiscal_period_duration_months' => (int) ($company['fiscal_period_duration_months'] ?? 12),
];

$tabs = [
    ['id' => 'profile', 'label' => 'Profil', 'icon' => 'fas fa-user'],
    ['id' => 'company', 'label' => 'Entreprise', 'icon' => 'fas fa-building'],
    ['id' => 'stock', 'label' => 'Stock', 'icon' => 'fas fa-boxes-stacked'],
    ['id' => 'team', 'label' => 'Équipe', 'icon' => 'fas fa-users'],
    ['id' => 'users', 'label' => 'Utilisateurs', 'icon' => 'fas fa-shield-alt'],
    ['id' => 'fiscal', 'label' => 'Parametres fiscaux', 'icon' => 'fas fa-file-invoice-dollar'],
    ['id' => 'security', 'label' => 'Sécurité', 'icon' => 'fas fa-lock'],
];
$roleOptions = [
    \App\Core\RolePermissions::ROLE_CASHIER => 'Caissier',
    \App\Core\RolePermissions::ROLE_STOREKEEPER => 'Magasinier',
    \App\Core\RolePermissions::ROLE_ADMIN => 'Administrateur',
];
if (!$isAdmin) {
    $tabs = array_values(array_filter($tabs, static fn(array $tab): bool => $tab['id'] !== 'users'));
    if ($activeTab === 'users') {
        $activeTab = 'profile';
    }
}

$errorCode = (string) ($_GET['error'] ?? '');
$savedCode = (string) ($_GET['saved'] ?? '');
$teamErrors = [
    'auth_required' => 'Session invalide. Reconnectez-vous.',
    'admin_required' => 'Action réservée aux administrateurs.',
    'invalid_profile_payload' => 'Veuillez verifier les champs du profil.',
    'profile_update_failed' => 'Impossible de mettre a jour le profil pour le moment.',
    'invalid_company_payload' => 'Veuillez verifier les champs entreprise.',
    'company_update_failed' => 'Impossible de mettre a jour l\'entreprise pour le moment.',
    'invalid_fiscal_payload' => 'Veuillez verifier la configuration fiscale.',
    'fiscal_update_failed' => 'Impossible de mettre a jour l\'exercice comptable.',
    'missing_fields' => 'Veuillez remplir tous les champs obligatoires.',
    'invalid_matricule' => 'Matricule invalide.',
    'weak_password' => 'Le mot de passe doit contenir au moins 8 caractères.',
    'password_mismatch' => 'Les mots de passe ne correspondent pas.',
    'company_required' => 'Impossible de retrouver l\'entreprise de rattachement.',
    'matricule_taken' => 'Ce matricule est déjà utilisé.',
    'user_create_failed' => 'Impossible de créer l\'utilisateur pour le moment.',
    'user_not_found' => 'Utilisateur introuvable dans cette entreprise.',
    'cannot_disable_self' => 'Vous ne pouvez pas desactiver votre propre compte.',
    'cannot_downgrade_self' => 'Vous ne pouvez pas retirer votre propre role administrateur.',
    'cannot_delete_self' => 'Vous ne pouvez pas supprimer votre propre compte.',
    'admin_delete_forbidden' => 'Suppression interdite: un administrateur ne peut pas etre supprime.',
    'last_admin_required' => 'Impossible de desactiver ou supprimer le dernier administrateur actif.',
    'user_delete_failed' => 'Impossible de supprimer l\'utilisateur pour le moment.',
    'invalid_role' => 'Role utilisateur invalide.',
    'ai_update_failed' => 'Impossible de sauvegarder la configuration IA.',
    'invalid_stock_form_payload' => 'Veuillez verifier la configuration du formulaire produit.',
    'stock_form_update_failed' => 'Impossible de sauvegarder le formulaire produit pour le moment.',
];
$teamSuccess = [
    'profile' => 'Profil mis a jour avec succes.',
    'company' => 'Informations entreprise mises a jour.',
    'fiscal' => 'Configuration de l\'exercice comptable mise a jour.',
    'user_created' => 'Utilisateur ajouté avec succès.',
    'user_status_updated' => 'Statut utilisateur mis a jour.',
    'user_role_updated' => 'Role utilisateur mis a jour.',
    'user_password_reset' => 'Mot de passe utilisateur reinitialise.',
    'user_deleted' => 'Utilisateur supprime avec succes.',
    'ai' => 'Configuration IA sauvegardee.',
    'stock_form' => 'Formulaire produit sauvegarde.',
];
$actionLabels = [
    'company_updated' => 'Entreprise mise a jour',
    'profile_updated' => 'Profil mis a jour',
    'fiscal_period_updated' => 'Configuration fiscale mise a jour',
    'user_created' => 'Utilisateur cree',
    'user_status_updated' => 'Statut utilisateur mis a jour',
    'user_role_updated' => 'Role utilisateur mis a jour',
    'user_password_reset' => 'Mot de passe reinitialise',
    'user_deleted' => 'Utilisateur supprime',
    'transaction_created' => 'Transaction creee',
    'transaction_updated' => 'Transaction mise a jour',
    'transaction_deleted' => 'Transaction supprimee',
    'invoice_created' => 'Facture creee',
    'invoice_updated' => 'Facture mise a jour',
    'invoice_merged' => 'Facture fusionnee',
    'invoice_sent' => 'Facture envoyee',
    'invoice_cancelled' => 'Facture annulee',
    'invoice_deleted' => 'Facture supprimee',
    'invoice_payment_recorded' => 'Paiement facture enregistre',
    'invoice_pdf_downloaded' => 'PDF facture telecharge',
    'product_created' => 'Produit cree',
    'product_updated' => 'Produit mis a jour',
    'product_deleted' => 'Produit retire du stock actif',
    'stock_adjusted' => 'Stock ajuste',
    'stock_lot_added' => 'Lot ajoute',
    'stock_lot_updated' => 'Lot reapprovisionne',
    'stock_lot_deleted' => 'Lot supprime',
    'stock_form_updated' => 'Formulaire produit mis a jour',
    'purchase_order_created' => 'Commande d achat creee',
    'purchase_order_status_updated' => 'Statut commande d achat mis a jour',
    'purchase_order_updated' => 'Commande d achat mise a jour',
    'purchase_order_deleted' => 'Commande d achat supprimee',
];
$formatAuditPayload = static function ($payload): array {
    if (!is_string($payload) || trim($payload) === '') {
        return [];
    }

    $decoded = json_decode($payload, true);
    if (!is_array($decoded)) {
        return [];
    }

    $flatten = static function (array $data, string $prefix = '') use (&$flatten): array {
        $items = [];
        foreach ($data as $key => $value) {
            $label = $prefix !== '' ? $prefix . '.' . (string) $key : (string) $key;
            if (is_array($value)) {
                $items = array_merge($items, $flatten($value, $label));
                continue;
            }
            if (is_bool($value)) {
                $value = $value ? 'oui' : 'non';
            } elseif ($value === null) {
                $value = '-';
            }
            $items[] = [
                'label' => str_replace('_', ' ', $label),
                'value' => (string) $value,
            ];
        }

        return $items;
    };

    return $flatten($decoded);
};

$productFormConfig = is_array($productFormConfig ?? null) ? $productFormConfig : [];
if ($productFormConfig === []) {
    $productFormConfig = \App\Models\ProductFormSettings::defaultConfig();
}

$pfFields = is_array($productFormConfig['fields'] ?? null) ? $productFormConfig['fields'] : [];
$pfBaseUnits = is_array($productFormConfig['base_units'] ?? null) ? $productFormConfig['base_units'] : [];
$pfFormes = is_array($productFormConfig['formes'] ?? null) ? $productFormConfig['formes'] : [];
$pfDefaults = is_array($productFormConfig['defaults'] ?? null) ? $productFormConfig['defaults'] : [];
$pfDefaultBaseUnit = (string) ($pfDefaults['base_unit_code'] ?? 'unite');
?>

<div class="settings-page">
    <!-- Header -->
    <div class="page-header">
        <div class="settings-header-copy">
            <h1 class="page-title">Paramètres</h1>
            <p class="page-subtitle">Gérez votre compte, votre entreprise et vos préférences en un seul espace.</p>
        </div>
        <button class="btn btn-primary" onclick="saveAllSettings()">
            <i class="fas fa-save"></i> Enregistrer les modifications
        </button>
    </div>

    <?php if ($errorCode !== '' && isset($teamErrors[$errorCode])): ?>
        <div class="settings-feedback settings-feedback-error"><?= htmlspecialchars($teamErrors[$errorCode], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($savedCode !== '' && isset($teamSuccess[$savedCode])): ?>
        <div class="settings-feedback settings-feedback-success"><?= htmlspecialchars($teamSuccess[$savedCode], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- Settings Layout -->
    <div style="display: grid; grid-template-columns: 280px 1fr; gap: 24px;">
        <!-- Tabs Navigation -->
        <div class="settings-sidebar card">
            <?php foreach ($tabs as $tab): ?>
            <button class="settings-tab <?= $tab['id'] === $activeTab ? 'active' : '' ?>" 
                    onclick="switchTab('<?= $tab['id'] ?>')"
                    data-tab="<?= $tab['id'] ?>">
                <span class="tab-icon"><i class="<?= $tab['icon'] ?>"></i></span>
                <span class="tab-label"><?= $tab['label'] ?></span>
            </button>
            <?php endforeach; ?>
            
            <div class="ai-status-mini" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-light);">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <span class="pulse-dot"></span>
                    <span style="font-weight: 500;">Symphony</span>
                </div>
                <div style="font-size: 12px; color: var(--text-secondary);">
                    Version 2.1.0 · Mise à jour 20/03/2026
                </div>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="settings-content">
            <!-- Profile Tab -->
            <div id="tab-profile" class="settings-tab-content <?= $activeTab === 'profile' ? 'active' : '' ?>">
                <div class="card">
                    <h3 style="margin-bottom: 25px;">Informations personnelles</h3>
                    <form id="profile-settings-form" method="POST" action="/settings/profile" enctype="multipart/form-data" data-async="true" data-async-success="Profil mis a jour.">
                        <input type="hidden" name="csrf_token" value="<?= App\Core\Security::generateCSRF() ?>">
                    
                    <div class="avatar-section" style="display: flex; align-items: center; gap: 30px; margin-bottom: 30px;">
                        <div class="avatar-large">
                            <img id="avatar-live-preview" src="<?= htmlspecialchars($currentAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="">
                        </div>
                        <div>
                            <label class="btn btn-soft" for="avatar-file-input">Changer l'avatar</label>
                            <input id="avatar-file-input" type="file" name="avatar_file" accept="image/*" class="hidden-file-input" data-preview-target="#avatar-live-preview">
                            <p style="margin-top: 8px; font-size: 12px; color: var(--text-secondary);">
                                JPG, PNG ou GIF. Max 2MB.
                            </p>
                        </div>
                    </div>
                    
                    <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label class="form-label">Prénom</label>
                            <input type="text" class="form-input" name="first_name" value="<?= htmlspecialchars((string) ($user['first_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Nom</label>
                            <input type="text" class="form-input" name="last_name" value="<?= htmlspecialchars((string) ($user['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Matricule</label>
                            <input type="text" class="form-input" name="matricule" value="<?= htmlspecialchars((string) ($user['matricule'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Téléphone</label>
                            <input type="tel" class="form-input" value="">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Langue</label>
                            <select class="form-input" name="language">
                                <option value="fr" <?= (($user['language'] ?? 'fr') === 'fr') ? 'selected' : '' ?>>Français</option>
                                <option value="en" <?= (($user['language'] ?? 'fr') === 'en') ? 'selected' : '' ?>>English</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Fuseau horaire</label>
                            <select class="form-input">
                                <option selected>Afrique/Kinshasa (GMT+1)</option>
                                <option>Afrique/Lubumbashi (GMT+2)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Theme</label>
                            <select class="form-input" name="theme">
                                <option value="light" <?= (($_SESSION['theme'] ?? 'light') === 'light') ? 'selected' : '' ?>>Clair</option>
                                <option value="dark" <?= (($_SESSION['theme'] ?? 'light') === 'dark') ? 'selected' : '' ?>>Sombre</option>
                            </select>
                        </div>
                    </div>
                        <div style="margin-top: 20px;">
                            <button class="btn btn-primary" type="submit">Enregistrer le profil</button>
                        </div>
                    </form>
                </div>

                <div class="card" style="margin-top: 20px;">
                    <h3 style="margin-bottom: 20px;">Préférences de notification</h3>
                    
                    <div class="notifications-list">
                        <label class="checkbox-item">
                            <input type="checkbox" checked>
                            <div>
                                <strong>Alertes de sécurité</strong>
                                <p>Connexions inhabituelles, modifications sensibles</p>
                            </div>
                        </label>
                        
                        <label class="checkbox-item">
                            <input type="checkbox" checked>
                            <div>
                                <strong>Rapports hebdomadaires</strong>
                                <p>Résumé de l'activité chaque lundi</p>
                            </div>
                        </label>
                        
                        <label class="checkbox-item">
                            <input type="checkbox">
                            <div>
                                <strong>Newsletter Symphony</strong>
                                <p>Nouveautés et conseils comptables</p>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Company Tab (simplifié) -->
            <div id="tab-company" class="settings-tab-content <?= $activeTab === 'company' ? 'active' : '' ?>">
                <div class="card">
                    <h3 style="margin-bottom: 25px;">Informations de l'entreprise</h3>
                    <form id="company-settings-form" method="POST" action="/settings/company" enctype="multipart/form-data" data-async="true" data-async-success="Entreprise mise a jour.">
                        <input type="hidden" name="csrf_token" value="<?= App\Core\Security::generateCSRF() ?>">
                    
                    <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group full-width" style="grid-column: span 2;">
                            <label class="form-label">Nom commercial</label>
                            <input type="text" class="form-input" name="name" value="<?= htmlspecialchars($company['name'], ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        
                        <div class="form-group full-width" style="grid-column: span 2;">
                            <label class="form-label">Raison sociale</label>
                            <input type="text" class="form-input" name="legal_name" value="<?= htmlspecialchars($company['legal_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">N° d'identification fiscale</label>
                            <input type="text" class="form-input" name="tax_id" value="<?= htmlspecialchars($company['tax_id'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Devise</label>
                            <input type="hidden" name="currency" value="USD">
                            <input type="text" class="form-input" value="USD ($)" readonly>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email entreprise</label>
                            <input type="email" class="form-input" name="email" value="<?= htmlspecialchars($company['email'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Téléphone</label>
                            <input type="text" class="form-input" name="phone" value="<?= htmlspecialchars($company['phone'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        
                        <div class="form-group full-width" style="grid-column: span 2;">
                            <label class="form-label">Adresse</label>
                            <input type="text" class="form-input" name="address" value="<?= htmlspecialchars($company['address'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Ville</label>
                            <input type="text" class="form-input" name="city" value="<?= htmlspecialchars($company['city'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Pays</label>
                            <input type="text" class="form-input" name="country" value="<?= htmlspecialchars($company['country'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="form-group full-width" style="grid-column: span 2;">
                            <label class="form-label">Logo facture</label>
                            <div class="logo-upload-row">
                                <img
                                    id="company-logo-live-preview"
                                    src="<?= htmlspecialchars($company['invoice_logo_url'] !== '' ? $company['invoice_logo_url'] : '', ENT_QUOTES, 'UTF-8') ?>"
                                    alt="Logo entreprise"
                                    class="company-logo-preview <?= $company['invoice_logo_url'] !== '' ? '' : 'is-hidden' ?>"
                                >
                                <input type="file" class="form-input" name="invoice_logo_file" accept="image/*" data-preview-target="#company-logo-live-preview">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Couleur facture</label>
                            <input type="color" class="form-input" name="invoice_brand_color" value="<?= htmlspecialchars($company['invoice_brand_color'] !== '' ? $company['invoice_brand_color'] : '#0F172A', ENT_QUOTES, 'UTF-8') ?>" style="max-width: 140px;">
                        </div>
                        <div class="form-group">
                            <label class="form-label">TVA par defaut (%)</label>
                            <input type="number" class="form-input" name="default_tax_rate" min="0" max="100" step="0.01" value="<?= htmlspecialchars(number_format((float) $company['default_tax_rate'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                        <div style="margin-top: 20px;">
                            <button class="btn btn-primary" type="submit" <?= $isAdmin ? '' : 'disabled' ?>>Enregistrer l'entreprise</button>
                            <?php if (!$isAdmin): ?>
                            <p style="margin-top:8px; font-size:12px; color: var(--text-secondary);">Seul un administrateur peut modifier ces champs.</p>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div id="tab-stock" class="settings-tab-content <?= $activeTab === 'stock' ? 'active' : '' ?>">
                <div class="card">
                    <h3 style="margin-bottom: 10px;">Formulaire de création de produit</h3>
                    <p style="margin:0 0 18px 0; color: var(--text-secondary); font-size: 13px; line-height: 1.5;">
                        Configurez les champs affichés dans le stock (par entreprise) : unités de base, formes, libellés et champs optionnels.
                    </p>

                    <?php
                        $pfBaseUnitsTextLines = [];
                        foreach ($pfBaseUnits as $u) {
                            $code = trim((string) ($u['code'] ?? ''));
                            if ($code === '') {
                                continue;
                            }
                            $label = trim((string) ($u['label'] ?? ''));
                            $pfBaseUnitsTextLines[] = $code . '|' . ($label !== '' ? $label : $code);
                        }
                        $pfBaseUnitsText = implode("\n", $pfBaseUnitsTextLines);
                        $pfFormesText = implode("\n", array_map(static fn(string $v): string => trim($v), $pfFormes));

                        $field = static function (string $key) use ($pfFields): array {
                            $def = is_array($pfFields[$key] ?? null) ? $pfFields[$key] : [];
                            return [
                                'enabled' => (bool) ($def['enabled'] ?? true),
                                'required' => (bool) ($def['required'] ?? false),
                                'label' => (string) ($def['label'] ?? ''),
                                'placeholder' => (string) ($def['placeholder'] ?? ''),
                                'input' => (string) ($def['input'] ?? 'text'),
                            ];
                        };
                        $fName = $field('name');
                        $fSupplier = $field('supplier');
                        $fDosage = $field('dosage');
                        $fForme = $field('forme');
                        $fPresentation = $field('presentation');
                        $fBaseUnit = $field('base_unit');
                    ?>

                    <form method="POST" action="/settings/stock-form" data-async="true" data-async-success="Formulaire produit sauvegarde.">
                        <input type="hidden" name="csrf_token" value="<?= App\Core\Security::generateCSRF() ?>">

                        <div class="form-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label class="form-label">Unités de base (une par ligne)</label>
                                <textarea class="form-input" name="pf_base_units" rows="6" placeholder="ex: unite|Unité&#10;kg|Kilogramme"><?= htmlspecialchars($pfBaseUnitsText, ENT_QUOTES, 'UTF-8') ?></textarea>
                                <p style="margin-top:8px; font-size:12px; color: var(--text-secondary);">Format: <code>code|libellé</code> (le libellé est optionnel).</p>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Unité de base par défaut</label>
                                <select class="form-input" name="pf_default_base_unit">
                                    <?php foreach ($pfBaseUnits as $u): ?>
                                        <?php
                                            $code = (string) ($u['code'] ?? '');
                                            $label = (string) ($u['label'] ?? $code);
                                            if (trim($code) === '') {
                                                continue;
                                            }
                                        ?>
                                        <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" <?= $code === $pfDefaultBaseUnit ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Formes (une par ligne)</label>
                                <textarea class="form-input" name="pf_formes" rows="6" placeholder="ex: Type A&#10;Type B"><?= htmlspecialchars($pfFormesText, ENT_QUOTES, 'UTF-8') ?></textarea>
                                <p style="margin-top:8px; font-size:12px; color: var(--text-secondary);">Utilisées si vous choisissez “Forme” en liste déroulante.</p>
                            </div>

                            <div class="form-group" style="grid-column: 1 / -1;">
                                <h4 style="margin: 0 0 12px 0;">Champs du formulaire</h4>
                                <div style="display:grid; grid-template-columns: 160px 1fr; gap: 10px; align-items:center;">
                                    <div style="font-weight:600;">Nom *</div>
                                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                        <input type="text" class="form-input" name="pf_field_name_label" value="<?= htmlspecialchars($fName['label'] !== '' ? $fName['label'] : 'Nom du produit', ENT_QUOTES, 'UTF-8') ?>" placeholder="Libellé">
                                        <input type="text" class="form-input" name="pf_field_name_placeholder" value="<?= htmlspecialchars($fName['placeholder'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Placeholder">
                                    </div>

                                    <div style="font-weight:600;">Fournisseur</div>
                                    <div style="display:grid; grid-template-columns: 240px 1fr; gap: 10px;">
                                        <div style="display:flex; gap:14px; align-items:center;">
                                            <label class="checkbox-item" style="display:flex; gap:10px; align-items:center;">
                                                <input type="checkbox" name="pf_field_supplier_enabled" <?= $fSupplier['enabled'] ? 'checked' : '' ?>>
                                                <span>Afficher</span>
                                            </label>
                                            <label class="checkbox-item" style="display:flex; gap:10px; align-items:center;">
                                                <input type="checkbox" name="pf_field_supplier_required" <?= $fSupplier['required'] ? 'checked' : '' ?> <?= $fSupplier['enabled'] ? '' : 'disabled' ?>>
                                                <span>Obligatoire</span>
                                            </label>
                                        </div>
                                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                            <input type="text" class="form-input" name="pf_field_supplier_label" value="<?= htmlspecialchars($fSupplier['label'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Libellé">
                                            <input type="text" class="form-input" name="pf_field_supplier_placeholder" value="<?= htmlspecialchars($fSupplier['placeholder'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Placeholder">
                                        </div>
                                    </div>

                                    <div style="font-weight:600;">Spécification</div>
                                    <div style="display:grid; grid-template-columns: 240px 1fr; gap: 10px;">
                                        <div style="display:flex; gap:14px; align-items:center;">
                                            <label class="checkbox-item" style="display:flex; gap:10px; align-items:center;">
                                                <input type="checkbox" name="pf_field_dosage_enabled" <?= $fDosage['enabled'] ? 'checked' : '' ?>>
                                                <span>Afficher</span>
                                            </label>
                                            <label class="checkbox-item" style="display:flex; gap:10px; align-items:center;">
                                                <input type="checkbox" name="pf_field_dosage_required" <?= $fDosage['required'] ? 'checked' : '' ?> <?= $fDosage['enabled'] ? '' : 'disabled' ?>>
                                                <span>Obligatoire</span>
                                            </label>
                                        </div>
                                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                            <input type="text" class="form-input" name="pf_field_dosage_label" value="<?= htmlspecialchars($fDosage['label'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Libellé">
                                            <input type="text" class="form-input" name="pf_field_dosage_placeholder" value="<?= htmlspecialchars($fDosage['placeholder'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Placeholder">
                                        </div>
                                    </div>

                                    <div style="font-weight:600;">Forme</div>
                                    <div style="display:grid; grid-template-columns: 240px 1fr; gap: 10px;">
                                        <div style="display:flex; gap:14px; align-items:center; flex-wrap:wrap;">
                                            <label class="checkbox-item" style="display:flex; gap:10px; align-items:center;">
                                                <input type="checkbox" name="pf_field_forme_enabled" <?= $fForme['enabled'] ? 'checked' : '' ?>>
                                                <span>Afficher</span>
                                            </label>
                                            <label class="checkbox-item" style="display:flex; gap:10px; align-items:center;">
                                                <input type="checkbox" name="pf_field_forme_required" <?= $fForme['required'] ? 'checked' : '' ?> <?= $fForme['enabled'] ? '' : 'disabled' ?>>
                                                <span>Obligatoire</span>
                                            </label>
                                            <select class="form-input" name="pf_field_forme_input" style="max-width: 160px;">
                                                <option value="text" <?= ($fForme['input'] ?? 'text') === 'text' ? 'selected' : '' ?>>Texte</option>
                                                <option value="select" <?= ($fForme['input'] ?? 'text') === 'select' ? 'selected' : '' ?>>Liste</option>
                                            </select>
                                        </div>
                                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                            <input type="text" class="form-input" name="pf_field_forme_label" value="<?= htmlspecialchars($fForme['label'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Libellé">
                                            <input type="text" class="form-input" name="pf_field_forme_placeholder" value="<?= htmlspecialchars($fForme['placeholder'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Placeholder">
                                        </div>
                                    </div>

                                    <div style="font-weight:600;">Présentation</div>
                                    <div style="display:grid; grid-template-columns: 240px 1fr; gap: 10px;">
                                        <div style="display:flex; gap:14px; align-items:center;">
                                            <label class="checkbox-item" style="display:flex; gap:10px; align-items:center;">
                                                <input type="checkbox" name="pf_field_presentation_enabled" <?= $fPresentation['enabled'] ? 'checked' : '' ?>>
                                                <span>Afficher</span>
                                            </label>
                                            <label class="checkbox-item" style="display:flex; gap:10px; align-items:center;">
                                                <input type="checkbox" name="pf_field_presentation_required" <?= $fPresentation['required'] ? 'checked' : '' ?> <?= $fPresentation['enabled'] ? '' : 'disabled' ?>>
                                                <span>Obligatoire</span>
                                            </label>
                                        </div>
                                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                            <input type="text" class="form-input" name="pf_field_presentation_label" value="<?= htmlspecialchars($fPresentation['label'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Libellé">
                                            <input type="text" class="form-input" name="pf_field_presentation_placeholder" value="<?= htmlspecialchars($fPresentation['placeholder'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Placeholder">
                                        </div>
                                    </div>

                                    <div style="font-weight:600;">Unité *</div>
                                    <div style="display:grid; grid-template-columns: 1fr; gap: 10px;">
                                        <input type="text" class="form-input" name="pf_field_base_unit_label" value="<?= htmlspecialchars($fBaseUnit['label'] !== '' ? $fBaseUnit['label'] : 'Unité de base', ENT_QUOTES, 'UTF-8') ?>" placeholder="Libellé (ex: Unité, Mesure...)">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 18px;">
                            <button class="btn btn-primary" type="submit">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="tab-team" class="settings-tab-content <?= $activeTab === 'team' ? 'active' : '' ?>">
                <?php if ($isAdmin): ?>
                    <div class="card" style="margin-bottom: 20px;">
                        <h3 style="margin-bottom: 20px;">Ajouter un utilisateur</h3>
                        <form method="POST" action="/admin/users" class="team-form-grid" data-async="true" data-async-success="Utilisateur cree.">
                            <input type="hidden" name="csrf_token" value="<?= App\Core\Security::generateCSRF() ?>">

                            <div class="form-group">
                                <label class="form-label">Prénom</label>
                                <input type="text" name="first_name" class="form-input" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Nom</label>
                                <input type="text" name="last_name" class="form-input" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Matricule</label>
                                <input type="text" name="matricule" class="form-input" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Mot de passe</label>
                                <input type="password" name="password" class="form-input" minlength="8" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Confirmer le mot de passe</label>
                                <input type="password" name="confirm_password" class="form-input" minlength="8" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Rôle</label>
                                <select class="form-input" name="role" required>
                                    <?php foreach ($roleOptions as $roleKey => $roleText): ?>
                                    <?php if ($roleKey === \App\Core\RolePermissions::ROLE_ADMIN): ?>
                                    <?php continue; ?>
                                    <?php endif; ?>
                                    <option value="<?= htmlspecialchars((string) $roleKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $roleText, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="grid-column: span 2;">
                                <button class="btn btn-add" type="submit">Créer l'utilisateur</button>
                            </div>
                        </form>
                    </div>

                    <div class="card">
                        <h3 style="margin-bottom: 20px;">Membres de l'équipe</h3>

                        <?php if ($companyUsers === []): ?>
                            <p style="color: var(--text-secondary);">Aucun utilisateur trouvé.</p>
                        <?php else: ?>
                            <div class="team-list">
                                <?php foreach ($companyUsers as $member): ?>
                                    <?php
                                    $memberId = (int) ($member['id'] ?? 0);
                                    $memberFullName = trim((string) (($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')));
                                    $memberRoleKey = \App\Core\RolePermissions::normalizeRole((string) ($member['role'] ?? ''));
                                    $memberRole = \App\Core\RolePermissions::label($memberRoleKey);
                                    $memberIsActive = ((int) ($member['is_active'] ?? 0) === 1);
                                    $isSelf = $memberId === (int) ($user['id'] ?? 0);
                                    $isAdminMember = $memberRoleKey === \App\Core\RolePermissions::ROLE_ADMIN;
                                    ?>
                                    <div class="team-member" style="gap:16px;">
                                        <div>
                                            <strong><?= htmlspecialchars($memberFullName !== '' ? $memberFullName : 'Utilisateur', ENT_QUOTES, 'UTF-8') ?></strong>
                                            <div style="font-size: 12px; color: var(--text-secondary);"><?= htmlspecialchars((string) ($member['matricule'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                        </div>
                                        <div style="text-align: right;">
                                            <div class="member-role"><?= htmlspecialchars($memberRole, ENT_QUOTES, 'UTF-8') ?></div>
                                            <div style="font-size: 12px; color: var(--text-secondary);">
                                                <?= $memberIsActive ? 'Actif' : 'Inactif' ?>
                                            </div>
                                        </div>
                                        <div style="margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                            <form method="POST" action="/admin/users/<?= $memberId ?>/status" data-async="true" data-async-success="Statut utilisateur mis a jour." style="margin:0;">
                                                <input type="hidden" name="csrf_token" value="<?= App\Core\Security::generateCSRF() ?>">
                                                <input type="hidden" name="is_active" value="<?= $memberIsActive ? '0' : '1' ?>">
                                                <input type="hidden" name="return_tab" value="team">
                                                <button type="submit" class="btn btn-soft btn-xs" <?= ($isSelf && $memberIsActive) ? 'disabled' : '' ?>>
                                                    <?= $memberIsActive ? 'Suspendre' : 'Activer' ?>
                                                </button>
                                            </form>
                                            <form method="POST" action="/admin/users/<?= $memberId ?>/delete" data-async="true" data-async-success="Utilisateur supprime." style="margin:0;" onsubmit="return confirm('Supprimer cet utilisateur ? Cette action est definitive.');">
                                                <input type="hidden" name="csrf_token" value="<?= App\Core\Security::generateCSRF() ?>">
                                                <input type="hidden" name="return_tab" value="team">
                                                <button type="submit" class="btn btn-soft btn-xs btn-icon-danger" <?= ($isSelf || $isAdminMember) ? 'disabled' : '' ?>>Supprimer</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <h3 style="margin-bottom: 20px;">Gestion de l'équipe</h3>
                        <p style="color: var(--text-secondary);">Seul un administrateur peut ajouter des utilisateurs.</p>
                        <p style="margin-top: 10px;">
                            Rôle actuel: <strong><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($isAdmin): ?>
            <div id="tab-users" class="settings-tab-content <?= $activeTab === 'users' ? 'active' : '' ?>">
                <div class="card" style="margin-bottom: 20px;">
                    <h3 style="margin-bottom: 20px;">Gestion complete des utilisateurs</h3>
                    <?php if ($companyUsers === []): ?>
                        <p style="color: var(--text-secondary);">Aucun utilisateur trouve.</p>
                    <?php else: ?>
                        <div class="users-grid">
                            <?php foreach ($companyUsers as $member): ?>
                                <?php
                                $memberId = (int) ($member['id'] ?? 0);
                                $memberFullName = trim((string) (($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')));
                                $memberIsActive = ((int) ($member['is_active'] ?? 0) === 1);
                                $memberRole = \App\Core\RolePermissions::normalizeRole((string) ($member['role'] ?? ''));
                                $canEditSelfRole = ($memberId === (int) ($user['id'] ?? 0));
                                ?>
                                <div class="user-card">
                                    <div class="user-card-head">
                                        <div>
                                            <strong><?= htmlspecialchars($memberFullName !== '' ? $memberFullName : 'Utilisateur', ENT_QUOTES, 'UTF-8') ?></strong>
                                            <div class="user-meta"><?= htmlspecialchars((string) ($member['matricule'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                        </div>
                                        <span class="member-role"><?= htmlspecialchars(\App\Core\RolePermissions::shortLabel($memberRole), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>

                                    <div class="user-meta-row">
                                        <span>Derniere connexion:</span>
                                        <strong><?= !empty($member['last_login']) ? htmlspecialchars((string) date('d/m/Y H:i', strtotime((string) $member['last_login'])), ENT_QUOTES, 'UTF-8') : 'Jamais' ?></strong>
                                    </div>

                                    <div class="user-actions-grid">
                                        <form method="POST" action="/admin/users/<?= $memberId ?>/status" data-async="true" data-async-success="Statut utilisateur mis a jour.">
                                            <input type="hidden" name="csrf_token" value="<?= App\Core\Security::generateCSRF() ?>">
                                            <input type="hidden" name="is_active" value="<?= $memberIsActive ? '0' : '1' ?>">
                                            <button type="submit" class="btn <?= $memberIsActive ? 'btn-soft' : 'btn-add' ?>" <?= ($canEditSelfRole && $memberIsActive) ? 'disabled' : '' ?>>
                                                <?= $memberIsActive ? 'Desactiver' : 'Activer' ?>
                                            </button>
                                        </form>

                                        <form method="POST" action="/admin/users/<?= $memberId ?>/role" data-async="true" data-async-success="Role utilisateur mis a jour.">
                                            <input type="hidden" name="csrf_token" value="<?= App\Core\Security::generateCSRF() ?>">
                                            <select class="form-input" name="role" style="min-width: 140px;">
                                                <?php foreach ($roleOptions as $roleKey => $roleText): ?>
                                                <option value="<?= htmlspecialchars((string) $roleKey, ENT_QUOTES, 'UTF-8') ?>" <?= $memberRole === $roleKey ? 'selected' : '' ?>><?= htmlspecialchars((string) $roleText, ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-soft" <?= ($canEditSelfRole && $memberRole === \App\Core\RolePermissions::ROLE_ADMIN) ? 'disabled' : '' ?>>
                                                Appliquer role
                                            </button>
                                        </form>

                                        <form method="POST" action="/admin/users/<?= $memberId ?>/delete" data-async="true" data-async-success="Utilisateur supprime." onsubmit="return confirm('Supprimer cet utilisateur ? Cette action est definitive.');">
                                            <input type="hidden" name="csrf_token" value="<?= App\Core\Security::generateCSRF() ?>">
                                            <input type="hidden" name="return_tab" value="users">
                                            <button type="submit" class="btn btn-soft btn-icon-danger" <?= ($canEditSelfRole || $memberRole === \App\Core\RolePermissions::ROLE_ADMIN) ? 'disabled' : '' ?>>
                                                Supprimer
                                            </button>
                                        </form>
                                    </div>

                                    <form method="POST" action="/admin/users/<?= $memberId ?>/password" data-async="true" data-async-success="Mot de passe reinitialise.">
                                        <input type="hidden" name="csrf_token" value="<?= App\Core\Security::generateCSRF() ?>">
                                        <div class="user-password-grid">
                                            <input class="form-input" type="password" name="new_password" minlength="8" placeholder="Nouveau mot de passe" required>
                                            <input class="form-input" type="password" name="confirm_password" minlength="8" placeholder="Confirmer" required>
                                            <button type="submit" class="btn btn-primary">Reinitialiser</button>
                                        </div>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card" id="user-logs">
                    <h3 style="margin-bottom: 16px;">Logs d'activites</h3>
                    <form method="GET" action="/settings" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr)) auto;gap:12px;align-items:end;margin-bottom:14px;">
                        <input type="hidden" name="tab" value="users">
                        <label class="form-group" style="margin:0;">
                            <span class="form-label">Periode comptable</span>
                            <select class="form-input" name="log_period_id">
                                <option value="">Toutes</option>
                                <?php foreach ($fiscalPeriods as $period): ?>
                                <?php $periodId = (int) ($period['id'] ?? 0); ?>
                                <option value="<?= $periodId ?>" <?= ((int) ($logFilters['period_id'] ?? 0) === $periodId) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) ($period['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="form-group" style="margin:0;">
                            <span class="form-label">Du</span>
                            <input class="form-input" type="date" name="log_date_from" value="<?= htmlspecialchars((string) ($logFilters['date_from'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </label>
                        <label class="form-group" style="margin:0;">
                            <span class="form-label">Au</span>
                            <input class="form-input" type="date" name="log_date_to" value="<?= htmlspecialchars((string) ($logFilters['date_to'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </label>
                        <label class="form-group" style="margin:0;">
                            <span class="form-label">Auteur</span>
                            <select class="form-input" name="log_user_id">
                                <option value="">Tous</option>
                                <?php foreach ($companyUsers as $member): ?>
                                <?php $memberId = (int) ($member['id'] ?? 0); ?>
                                <?php $memberFullName = trim((string) (($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''))); ?>
                                <?php $memberLabel = $memberFullName !== '' ? $memberFullName : (string) ($member['matricule'] ?? ($member['email'] ?? 'Utilisateur')); ?>
                                <option value="<?= $memberId ?>" <?= ((int) ($logFilters['user_id'] ?? 0) === $memberId) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($memberLabel, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <button class="btn btn-soft" type="submit">Filtrer</button>
                            <a class="btn" href="/settings?tab=users">Reinitialiser</a>
                            <a class="btn btn-soft" href="<?= htmlspecialchars($logExportUrl, ENT_QUOTES, 'UTF-8') ?>" data-no-async="true">Exporter Excel</a>
                        </div>
                    </form>

                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Utilisateur</th>
                                    <th>Action</th>
                                    <th>Table</th>
                                    <th>ID</th>
                                    <th>IP</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (($auditLogs ?? []) === []): ?>
                                <tr><td colspan="7" style="text-align:center;color:var(--text-secondary);padding:20px;">Aucun log pour ces filtres.</td></tr>
                                <?php endif; ?>
                                <?php foreach (($auditLogs ?? []) as $log): ?>
                                <?php
                                    $logFullName = trim((string) (($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')));
                                    if ($logFullName === '') {
                                        $logFullName = (string) ($log['matricule'] ?? ($log['email'] ?? 'Utilisateur supprime'));
                                    }
                                    $actionKey = (string) ($log['action'] ?? '');
                                    $actionLabel = $actionLabels[$actionKey] ?? $actionKey;
                                    $oldDetails = $formatAuditPayload((string) ($log['old_data'] ?? ''));
                                    $newDetails = $formatAuditPayload((string) ($log['new_data'] ?? ''));
                                    $hasDetails = $oldDetails !== [] || $newDetails !== [] || trim((string) ($log['user_agent'] ?? '')) !== '';
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($log['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($logFullName, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                                        <div class="text-secondary" style="font-size:12px;"><?= htmlspecialchars($actionKey, ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td><?= htmlspecialchars((string) ($log['table_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($log['record_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($log['ip_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php if (!$hasDetails): ?>
                                        <span class="text-secondary">Aucun detail</span>
                                        <?php else: ?>
                                        <details class="audit-log-details">
                                            <summary>Voir details</summary>
                                            <?php if ($newDetails !== []): ?>
                                            <div class="audit-log-block">
                                                <div class="audit-log-block-title">Apres</div>
                                                <?php foreach ($newDetails as $item): ?>
                                                <div class="audit-log-line">
                                                    <span><?= htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                                    <strong><?= htmlspecialchars((string) ($item['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($oldDetails !== []): ?>
                                            <div class="audit-log-block">
                                                <div class="audit-log-block-title">Avant</div>
                                                <?php foreach ($oldDetails as $item): ?>
                                                <div class="audit-log-line">
                                                    <span><?= htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                                    <strong><?= htmlspecialchars((string) ($item['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                        </details>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-secondary" style="font-size:12px;margin-top:8px;">Lecture seule. Derniers 200 logs affiches.</p>
                </div>
            </div>
            <?php endif; ?>

            <div id="tab-fiscal" class="settings-tab-content <?= $activeTab === 'fiscal' ? 'active' : '' ?>">
                <div class="card">
                    <h3 style="margin-bottom: 20px;">Paramètres fiscaux</h3>
                    <form method="POST" action="/settings/fiscal" data-async="true" data-async-success="Parametres fiscaux mis a jour.">
                        <input type="hidden" name="csrf_token" value="<?= App\Core\Security::generateCSRF() ?>">

                    <div class="form-group">
                        <label class="form-label">Date debut exercice comptable</label>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="date" class="form-input" name="fiscal_year_start" value="<?= htmlspecialchars((string) ($company['fiscal_year_start'] ?? date('Y-01-01')), ENT_QUOTES, 'UTF-8') ?>" style="max-width: 240px;" required>
                            <span class="text-secondary">Creation automatique des periodes suivantes</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Duree de l'exercice (mois)</label>
                        <input type="number" class="form-input" name="fiscal_period_duration_months" min="1" max="24" value="<?= htmlspecialchars((string) ($company['fiscal_period_duration_months'] ?? 12), ENT_QUOTES, 'UTF-8') ?>" style="max-width: 140px;" required>
                    </div>
                    
                    <div class="form-group">
                        <p class="text-secondary">Toutes les factures et transactions seront rattachees automatiquement a une periode d'exercice.</p>
                    </div>

                    <div style="margin-top: 20px;">
                        <button class="btn btn-primary" type="submit" <?= $isAdmin ? '' : 'disabled' ?>>Enregistrer l'exercice</button>
                    </div>
                    </form>
                </div>
            </div>

            <div id="tab-ai" class="settings-tab-content <?= $activeTab === 'ai' ? 'active' : '' ?>">
                <div class="card">
                    <h3 style="margin-bottom: 20px;">Configuration de Symphony IA</h3>

                    <?php if ($isAdmin): ?>
                    <form method="POST" action="/settings/ai" data-async="true" data-async-success="Configuration IA sauvegardee.">
                        <input type="hidden" name="csrf_token" value="<?= App\Core\Security::generateCSRF() ?>">

                        <h4 style="margin-bottom: 10px;">Prompts systeme (centralises en base)</h4>
                        <div class="users-grid" style="margin-bottom: 18px;">
                            <?php foreach ($aiPrompts as $prompt): ?>
                            <?php $promptKey = (string) ($prompt['resource_key'] ?? ''); ?>
                            <div class="user-card">
                                <div class="user-card-head">
                                    <strong><?= htmlspecialchars($promptKey, ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span class="member-role">prompt</span>
                                </div>
                                <textarea class="form-input" name="ai_prompt[<?= htmlspecialchars($promptKey, ENT_QUOTES, 'UTF-8') ?>]" rows="6"><?= htmlspecialchars((string) ($prompt['content'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <h4 style="margin-bottom: 10px;">Connaissances (centralisees en base)</h4>
                        <div class="users-grid">
                            <?php foreach ($aiKnowledge as $knowledge): ?>
                            <?php $knowledgeKey = (string) ($knowledge['resource_key'] ?? ''); ?>
                            <div class="user-card">
                                <div class="user-card-head">
                                    <strong><?= htmlspecialchars($knowledgeKey, ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span class="member-role">knowledge</span>
                                </div>
                                <textarea class="form-input" name="ai_knowledge[<?= htmlspecialchars($knowledgeKey, ENT_QUOTES, 'UTF-8') ?>]" rows="8"><?= htmlspecialchars((string) ($knowledge['content'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                            <?php endforeach; ?>
                            <?php if ($aiKnowledge === []): ?>
                            <div class="user-card">
                                <div class="user-card-head">
                                    <strong>Aucune connaissance</strong>
                                    <span class="member-role">knowledge</span>
                                </div>
                                <p class="text-secondary">Ajoutez des fichiers texte dans `app/ai/knowledge` puis rechargez cette page.</p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div style="margin-top: 18px;">
                            <button type="submit" class="btn btn-primary">Enregistrer configuration IA</button>
                        </div>
                    </form>
                    <?php else: ?>
                    <p style="color: var(--text-secondary);">Seul un administrateur peut consulter et modifier les prompts et connaissances IA.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div id="tab-security" class="settings-tab-content <?= $activeTab === 'security' ? 'active' : '' ?>">
                <div class="card">
                    <h3 style="margin-bottom: 20px;">Sécurité</h3>
                    
                    <div class="form-group">
                        <label class="form-label">Mot de passe actuel</label>
                        <div class="password-field">
                            <input type="password" class="form-input" value="********" style="max-width: 300px;" data-password-input>
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Afficher le mot de passe">👁</button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nouveau mot de passe</label>
                        <div class="password-field">
                            <input type="password" class="form-input" style="max-width: 300px;" data-password-input>
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Afficher le mot de passe">👁</button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirmer le mot de passe</label>
                        <div class="password-field">
                            <input type="password" class="form-input" style="max-width: 300px;" data-password-input>
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Afficher le mot de passe">👁</button>
                        </div>
                    </div>
                    
                    <hr style="margin: 30px 0; border-color: var(--border-light);">
                    
                    <div class="form-group">
                        <label class="checkbox-item">
                            <input type="checkbox" checked>
                            <div>
                                <strong>Authentification à deux facteurs</strong>
                                <p>Renforcez la sécurité de votre compte</p>
                            </div>
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-item">
                            <input type="checkbox" checked>
                            <div>
                                <strong>Journalisation des connexions</strong>
                                <p>Enregistrer toutes les tentatives de connexion</p>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.settings-page .page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 18px;
    flex-wrap: wrap;
}

.settings-header-copy {
    max-width: 720px;
}

.settings-page .card {
    border: 1px solid var(--border-light);
}

.audit-log-details {
    min-width: 260px;
}

.audit-log-details summary {
    cursor: pointer;
    color: var(--primary);
    font-weight: 600;
}

.audit-log-block {
    margin-top: 10px;
    padding: 10px 12px;
    border: 1px solid var(--border-light);
    border-radius: 12px;
    background: var(--surface-alt, rgba(15, 23, 42, 0.03));
}

.audit-log-block-title {
    margin-bottom: 6px;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.audit-log-line {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 4px 0;
    font-size: 12px;
}

.audit-log-line strong {
    text-align: right;
    overflow-wrap: anywhere;
}

.settings-sidebar {
    padding: 16px;
    height: fit-content;
    position: sticky;
    top: 20px;
}

.settings-tab {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 12px 16px;
    border: none;
    background: none;
    border-radius: var(--radius-md);
    color: var(--text-secondary);
    font-size: 14px;
    text-align: left;
    cursor: pointer;
    transition: all 0.2s;
    margin-bottom: 4px;
}

.settings-tab:hover {
    background: var(--accent-soft);
    color: var(--accent);
}

.settings-tab.active {
    background: var(--accent-soft);
    color: var(--accent);
    font-weight: 500;
    box-shadow: inset 0 0 0 1px rgba(15, 157, 88, 0.28);
}

.tab-icon {
    font-size: 18px;
}

.settings-tab-content {
    display: none;
}

.settings-tab-content.active {
    display: block;
    animation: fadeIn 0.3s;
}

.avatar-large img {
    width: 80px;
    height: 80px;
    border-radius: 16px;
    object-fit: cover;
    border: 2px solid var(--border-light);
}

.checkbox-item {
    display: flex;
    gap: 15px;
    padding: 15px;
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all 0.2s;
}

.checkbox-item:hover {
    background: var(--accent-soft);
}

.checkbox-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin-top: 3px;
}

.checkbox-item strong {
    display: block;
    margin-bottom: 4px;
    font-size: 14px;
}

.checkbox-item p {
    font-size: 12px;
    color: var(--text-secondary);
}

.full-width {
    grid-column: span 2;
}

.settings-feedback {
    margin-bottom: 16px;
    padding: 10px 12px;
    border-radius: var(--radius-md);
    font-size: 14px;
}

.settings-feedback-error {
    background: rgba(239, 68, 68, 0.12);
    color: #b91c1c;
}

.settings-feedback-success {
    background: rgba(34, 197, 94, 0.12);
    color: #15803d;
}

.team-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.team-list {
    display: grid;
    gap: 12px;
}

.team-member {
    padding: 14px;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.member-role {
    font-size: 13px;
    font-weight: 600;
}

.users-grid {
    display: grid;
    gap: 14px;
}

.user-card {
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    padding: 14px;
    display: grid;
    gap: 10px;
}

.user-card-head {
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.user-meta {
    font-size: 12px;
    color: var(--text-secondary);
}

.user-meta-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    color: var(--text-secondary);
}

.user-actions-grid {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.user-password-grid {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 8px;
}

.hidden-file-input {
    display: none;
}

.logo-upload-row {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.company-logo-preview {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    object-fit: contain;
    background: #fff;
    border: 1px solid var(--border-light);
    padding: 4px;
}

.company-logo-preview.is-hidden {
    display: none;
}

.settings-content .form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.settings-content .form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.settings-content .form-label {
    font-size: 12px;
    color: var(--text-secondary);
    font-weight: 500;
}

.settings-content .form-input {
    width: 100%;
    min-height: 42px;
    padding: 10px 12px;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    background: var(--bg-surface);
    color: var(--text-primary);
    transition: border-color 0.2s, box-shadow 0.2s;
}

.settings-content .form-input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(15, 157, 88, 0.18);
    outline: none;
}

.password-field {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
}

.password-field .form-input {
    flex: 1;
    min-width: 0;
}

.password-toggle {
    width: 36px;
    height: 36px;
    border: 1px solid var(--border-light);
    border-radius: 10px;
    background: var(--bg-surface);
    color: var(--text-secondary);
    cursor: pointer;
    flex-shrink: 0;
}

.password-toggle:hover {
    color: var(--accent);
    border-color: var(--accent);
}

@media (max-width: 980px) {
    .settings-sidebar {
        position: static;
    }
    .settings-content .form-grid {
        grid-template-columns: 1fr;
    }
    .team-form-grid {
        grid-template-columns: 1fr;
    }
    .user-password-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function switchTab(tabId) {
    const tabButton = document.querySelector(`.settings-tab[data-tab="${tabId}"]`);
    const tabContent = document.getElementById(`tab-${tabId}`);
    if (!tabButton || !tabContent) return;

    // Mettre à jour les onglets
    document.querySelectorAll('.settings-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    tabButton.classList.add('active');
    
    // Mettre à jour le contenu
    document.querySelectorAll('.settings-tab-content').forEach(content => {
        content.classList.remove('active');
    });
    tabContent.classList.add('active');
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tabId);
    history.replaceState({ url: url.toString() }, '', url.toString());
}

function saveAllSettings() {
    const activeTab = document.querySelector('.settings-tab-content.active');
    if (!activeTab) return;
    const form = activeTab.querySelector('form');
    if (!form) {
        Symphony.showNotification('Aucun formulaire à enregistrer pour cet onglet.', 'info');
        return;
    }
    form.requestSubmit();
}

function bindSettingsFilePreview() {
    const avatarInput = document.getElementById('avatar-file-input');
    const avatarPreview = document.getElementById('avatar-live-preview');
    if (avatarInput && avatarPreview && !avatarInput.hasAttribute('data-preview-target')) {
        avatarInput.setAttribute('data-preview-target', '#avatar-live-preview');
    }

    document.querySelectorAll('input[type="file"][data-preview-target]').forEach((input) => {
        if (input.dataset.previewBound === '1') return;
        input.dataset.previewBound = '1';
        input.addEventListener('change', (event) => {
            const file = event.target.files && event.target.files[0];
            if (!file) return;
            const targetSelector = input.getAttribute('data-preview-target');
            const preview = targetSelector ? document.querySelector(targetSelector) : null;
            if (!preview) return;
            const objectUrl = URL.createObjectURL(file);
            preview.src = objectUrl;
            preview.classList.remove('is-hidden');
        });
    });
}

function setPasswordToggleIcon(button, visible) {
    button.innerHTML = visible
        ? '<i class="fa-regular fa-eye-slash"></i>'
        : '<i class="fa-regular fa-eye"></i>';
}

function enhancePasswordFields() {
    document.querySelectorAll('input[type="password"]').forEach((input) => {
        if (!input.hasAttribute('data-password-input')) {
            input.setAttribute('data-password-input', '');
        }

        let field = input.closest('.password-field');
        if (!field) {
            const wrapper = document.createElement('div');
            wrapper.className = 'password-field';
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);
            field = wrapper;
        }

        let toggle = field.querySelector('[data-password-toggle]');
        if (!toggle) {
            toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'password-toggle';
            toggle.setAttribute('data-password-toggle', '');
            toggle.setAttribute('aria-label', 'Afficher le mot de passe');
            field.appendChild(toggle);
        }

        setPasswordToggleIcon(toggle, input.type === 'text');
    });
}

function bindPasswordToggles() {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        if (button.dataset.bound === '1') return;
        button.dataset.bound = '1';
        button.addEventListener('click', () => {
            const field = button.closest('.password-field');
            const input = field ? field.querySelector('[data-password-input]') : null;
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            setPasswordToggleIcon(button, show);
            button.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        });
    });
}

bindSettingsFilePreview();
enhancePasswordFields();
bindPasswordToggles();
</script>
