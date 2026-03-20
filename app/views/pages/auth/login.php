<?php $title = 'Connexion'; ?>
<?php $subtitle = 'Accédez à votre tableau de bord' ?>
<?php
$footer = !empty($registrationOpen)
    ? 'Pas encore de compte ? <a href="/register">Créer un compte</a>'
    : '';
?>

<div class="form-wrapper">
    <h2>Connexion</h2>

    <?php if (!empty($authError)): ?>
        <div class="auth-alert auth-alert-error"><?= htmlspecialchars($authError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (!empty($authSuccess)): ?>
        <div class="auth-alert auth-alert-success"><?= htmlspecialchars($authSuccess, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" action="/login" id="login-form">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <div class="input-box">
            <span class="icon"><i class="fa fa-envelope"></i></span>
            <input
                type="text"
                id="matricule"
                name="matricule"
                placeholder="Matricule"
                required
            >
        </div>

        <div class="input-box">
            <span class="icon"><i class="fa fa-lock"></i></span>
            <input
                type="password"
                id="password"
                name="password"
                placeholder="Mot de passe"
                required
            >
        </div>

        <div class="forgot-pass">
            <a href="#">Mot de passe oublié ?</a>
        </div>

        <button type="submit">Se connecter</button>
    </form>

    <div class="sign-link">
        <p>
            <?= $registrationOpen
                ? 'Pas encore de compte ? <a href="/register">Créer un compte</a>'
                : 'Inscriptions fermées. Contactez votre administrateur.' ?>
        </p>
    </div>
</div>
