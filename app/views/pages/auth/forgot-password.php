<?php $footer = '<a href="/login">Retour à la connexion</a>'; ?>

<div class="auth-form">
    <p style="color: var(--text-secondary); margin-bottom: 20px;">
        En mode démo, utilisez simplement un compte de démonstration pour accéder au dashboard.
    </p>

    <form method="GET" action="/login" id="forgot-form">
        <div class="form-group">
            <label for="matricule" class="form-label">Matricule du compte</label>
            <input type="text" id="matricule" name="matricule" class="form-input" placeholder="MAT-0001" required>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Retour à la connexion</button>
    </form>
</div>
