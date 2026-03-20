<?php $title = 'Créer un compte'; ?>
<?php $subtitle = 'Commencez avec Symphony' ?>
<?php $footer = 'Déjà inscrit ? <a href="/login">Se connecter</a>' ?>

<div class="auth-form">
    <?php if (!empty($authError)): ?>
        <div class="auth-alert auth-alert-error"><?= htmlspecialchars($authError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" action="/register" id="register-form">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        
        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div class="form-group">
                <label class="form-label">Prénom</label>
                <input type="text" name="first_name" class="form-input" placeholder="Jean" required data-validate="required">
            </div>
            
            <div class="form-group">
                <label class="form-label">Nom</label>
                <input type="text" name="last_name" class="form-input" placeholder="Dupont" required data-validate="required">
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Nom de l'entreprise</label>
            <input type="text" name="company_name" class="form-input" placeholder="Ma Super Entreprise" required data-validate="required">
        </div>
        
        <div class="form-group">
            <label class="form-label">Matricule</label>
            <input type="text" name="matricule" class="form-input" placeholder="MAT-0001" required data-validate="required">
        </div>
        
        <div class="form-group">
            <label class="form-label">Téléphone</label>
            <input type="tel" name="phone" class="form-input" placeholder="+243 XXX XXX XXX">
        </div>
        
        <div class="form-group">
            <label class="form-label">Mot de passe</label>
            <input type="password" name="password" class="form-input" placeholder="Minimum 8 caractères" required data-validate="required min:8">
        </div>
        
        <div class="form-group">
            <label class="form-label">Confirmer le mot de passe</label>
            <input type="password" name="confirm_password" class="form-input" placeholder="Confirmer" required data-validate="required">
        </div>
        
        <div class="form-group">
            <label class="checkbox">
                <input type="checkbox" name="terms" required>
                <span>J'accepte les <a href="/terms" style="color: var(--accent);">conditions d'utilisation</a></span>
            </label>
        </div>
        
        <button type="submit" class="btn btn-primary btn-block">
            Créer mon compte
        </button>
    </form>
    
    <div class="business-types" style="margin-top: 30px;">
        <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 10px;">Adapté à tout type d'entreprise :</p>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <span style="background: var(--accent-soft); padding: 4px 12px; border-radius: 20px; font-size: 12px;">SARL</span>
            <span style="background: var(--accent-soft); padding: 4px 12px; border-radius: 20px; font-size: 12px;">SASU</span>
            <span style="background: var(--accent-soft); padding: 4px 12px; border-radius: 20px; font-size: 12px;">EURL</span>
            <span style="background: var(--accent-soft); padding: 4px 12px; border-radius: 20px; font-size: 12px;">Auto-entrepreneur</span>
            <span style="background: var(--accent-soft); padding: 4px 12px; border-radius: 20px; font-size: 12px;">Association</span>
            <span style="background: var(--accent-soft); padding: 4px 12px; border-radius: 20px; font-size: 12px;">ONG</span>
        </div>
    </div>
</div>

<style>
.auth-alert {
    margin-bottom: 16px;
    border-radius: var(--radius-md);
    padding: 10px 12px;
    font-size: 13px;
}

.auth-alert-error {
    background: rgba(239, 68, 68, 0.12);
    color: #b91c1c;
}
</style>

<script>
document.getElementById('register-form')?.addEventListener('submit', function(e) {
    // Validation simple
    const password = document.querySelector('input[name="password"]').value;
    const confirm = document.querySelector('input[name="confirm_password"]').value;
    
    if (password !== confirm) {
        alert('Les mots de passe ne correspondent pas');
        return;
    }
    
    if (window.Symphony && typeof window.Symphony.showLoader === 'function') {
        window.Symphony.showLoader();
    }
});
</script>
