<?php if (!empty($error)): ?>
    <div style="margin-bottom: 14px; padding: 10px; background: rgba(239,68,68,0.12); color: #b91c1c; border-radius: 8px;">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<form method="POST" action="/provider/login">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <div class="form-group">
        <label class="form-label">Email fournisseur</label>
        <input class="form-input" type="email" name="email" placeholder="provider@nestcorporation.com" required>
    </div>

    <div class="form-group">
        <label class="form-label">Mot de passe</label>
        <input class="form-input" type="password" name="password" required>
    </div>

    <button class="btn btn-primary btn-block" type="submit">Se connecter</button>
</form>
