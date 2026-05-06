<?php $title = 'Créer un compte'; ?>
<?php $subtitle = 'Commencez en quelques secondes'; ?>
<?php $footer = 'Déjà un compte ? <a href="/login">Se connecter</a>'; ?>

<h2>Créer un compte</h2>
<p class="auth-card-subtitle">Inscription rapide : email + mot de passe.</p>

<?php if (!empty($authError)): ?>
    <div class="auth-alert auth-alert-error"><?= htmlspecialchars($authError, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<form method="POST" action="/register" id="register-form" class="auth-form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <div class="auth-field">
        <label class="auth-label" for="email">Email</label>
        <div class="auth-input-wrap">
            <i class="fa-regular fa-envelope auth-input-icon" aria-hidden="true"></i>
            <input
                class="auth-input"
                type="email"
                id="email"
                name="email"
                placeholder="exemple@domaine.com"
                autocomplete="email"
                required
            >
        </div>
    </div>

    <div class="auth-field">
        <label class="auth-label" for="password">Mot de passe</label>
        <div class="auth-input-wrap">
            <i class="fa-solid fa-lock auth-input-icon" aria-hidden="true"></i>
            <input
                class="auth-input"
                type="password"
                id="password"
                name="password"
                placeholder="Min 8 caractères"
                autocomplete="new-password"
                required
            >
            <button
                type="button"
                class="auth-input-action"
                aria-label="Afficher/masquer le mot de passe"
                aria-pressed="false"
                data-toggle-password="password"
            >
                <i class="fa-regular fa-eye" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="auth-field">
        <label class="auth-label" for="confirm_password">Confirmer le mot de passe</label>
        <div class="auth-input-wrap">
            <i class="fa-solid fa-lock auth-input-icon" aria-hidden="true"></i>
            <input
                class="auth-input"
                type="password"
                id="confirm_password"
                name="confirm_password"
                placeholder="Répétez le mot de passe"
                autocomplete="new-password"
                required
            >
            <button
                type="button"
                class="auth-input-action"
                aria-label="Afficher/masquer le mot de passe"
                aria-pressed="false"
                data-toggle-password="confirm_password"
            >
                <i class="fa-regular fa-eye" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <label class="auth-terms">
        <input type="checkbox" name="terms" required>
        <span>J'accepte les <a class="auth-link" href="/terms">conditions d'utilisation</a></span>
    </label>

    <button type="submit" class="auth-button">Créer mon compte</button>
</form>
