<?php $title = 'Connexion'; ?>
<?php $subtitle = 'Accédez à votre tableau de bord'; ?>
<?php
$footer = !empty($registrationOpen)
    ? 'Pas encore de compte ? <a href="/register">Créer un compte</a>'
    : 'Inscriptions fermées. Contactez votre administrateur.';
?>

<h2>Connexion</h2>
<p class="auth-card-subtitle">Connectez-vous avec votre email et votre mot de passe.</p>

<?php if (!empty($authError)): ?>
    <div class="auth-alert auth-alert-error"><?= htmlspecialchars($authError, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if (!empty($authSuccess)): ?>
    <div class="auth-alert auth-alert-success"><?= htmlspecialchars($authSuccess, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<form method="POST" action="/login" id="login-form" class="auth-form">
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
                placeholder="Votre mot de passe"
                autocomplete="current-password"
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

    <div class="auth-row">
        <a class="auth-link" href="/forgot-password">Mot de passe oublié ?</a>
    </div>

    <button type="submit" class="auth-button">Se connecter</button>
</form>
