<?php $footer = '<a href="/login">Retour à la connexion</a>'; ?>

<h2>Mot de passe oublié</h2>
<p class="auth-card-subtitle">En mode démo, utilisez simplement un compte existant pour accéder au tableau de bord.</p>

<form method="GET" action="/login" id="forgot-form" class="auth-form">
    <div class="auth-field">
        <label class="auth-label" for="email">Email du compte</label>
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

    <button type="submit" class="auth-button">Retour à la connexion</button>
</form>
