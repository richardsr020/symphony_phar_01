<?php $title = 'Créer un compte'; ?>
<?php $subtitle = 'Commencez avec Symphony' ?>
<?php $wrapperClass = 'wrapper--tall'; ?>

<div class="form-wrapper">
    <br><br><br>
    <form method="POST" action="/register" id="register-form">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <div class="input-row input-row--three">
            <div class="input-box">
                <span class="icon"><i class="fa fa-user"></i></span>
                <input type="text" name="first_name" placeholder="Prénom" required data-validate="required">
            </div>
            <div class="input-box">
                <span class="icon"><i class="fa fa-user"></i></span>
                <input type="text" name="last_name" placeholder="Nom" required data-validate="required">
            </div>
            <div class="input-box">
                <span class="icon"><i class="fa fa-id-card"></i></span>
                <input type="text" name="matricule" placeholder="Matricule" required data-validate="required">
            </div>
        </div>

        <div class="input-row">
            <div class="input-box">
                <span class="icon"><i class="fa fa-building"></i></span>
                <input type="text" name="company_name" placeholder="Nom de l'entreprise" required data-validate="required">
            </div>
            <div class="input-box">
                <span class="icon"><i class="fa fa-phone"></i></span>
                <input type="tel" name="phone" placeholder="Téléphone">
            </div>
        </div>

        <div class="input-row">
            <div class="input-box">
                <span class="icon"><i class="fa fa-lock"></i></span>
                <input type="password" name="password" placeholder="Mot de passe (min 8 caractères)" required data-validate="required min:8">
            </div>
            <div class="input-box">
                <span class="icon"><i class="fa fa-lock"></i></span>
                <input type="password" name="confirm_password" placeholder="Confirmer le mot de passe" required data-validate="required">
            </div>
        </div>

        <label class="terms-check">
            <input type="checkbox" name="terms" required>
            <span>J'accepte les <a href="/terms">conditions d'utilisation</a></span>
        </label>

        <button type="submit">Créer mon compte</button>
    </form>

    <div class="business-types">
        <p>Adapté à tout type d'entreprise :</p>
        <div class="type-chips">
            <span>SARL</span>
            <span>SASU</span>
            <span>EURL</span>
            <span>Auto-entrepreneur</span>
            <span>Association</span>
            <span>ONG</span>
        </div>
    </div>

    <div class="sign-link">
        <p>Déjà inscrit ? <a href="/login">Se connecter</a></p>
    </div>
</div>

<style>
.wrapper.wrapper--tall {
    height: auto;
    padding-top: 120px;
    padding-bottom: 50px;
    justify-content: center;
}

.form-wrapper {
    max-width: 900px;
    margin: 0 auto;
    padding: 0 20px;
}

form {
    max-width: 100%;
    margin: 0 auto;
}

.input-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.input-row--three {
    grid-template-columns: 1fr 1fr 1fr;
}

.input-box {
    display: flex;
    align-items: center;
    border: 2px solid white;
    border-radius: 50px;
    padding: 4px 20px;
    transition: all 0.3s ease;
    width: 100%;
    background: transparent;
    position: relative;
}

.input-box .icon {
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    margin-right: 12px;
    font-size: 16px;
    min-width: 20px;
    flex-shrink: 0;
    position: relative;
    z-index: 1;
}

.input-box input {
    flex: 1;
    border: none;
    padding: 12px 0;
    font-size: 15px;
    outline: none;
    background: transparent;
    width: 100%;
    color: white;
    position: relative;
    z-index: 1;
}

.input-box input::placeholder {
    color: rgba(255, 255, 255, 0.7);
}

.input-box input:-webkit-autofill,
.input-box input:-webkit-autofill:hover,
.input-box input:-webkit-autofill:focus,
.input-box input:-webkit-autofill:active {
    -webkit-background-clip: text;
    -webkit-text-fill-color: white;
    transition: background-color 5000s ease-in-out 0s;
    box-shadow: inset 0 0 0px 1000px transparent;
    background-color: transparent;
}

.terms-check {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    font-size: 0.9em;
    color: white;
    margin: 20px 0 24px;
}

.terms-check input {
    width: 16px;
    height: 16px;
    accent-color: #54880e;
    margin: 0;
}

.terms-check a {
    color: white;
    font-weight: 600;
    text-decoration: none;
}

.terms-check a:hover {
    text-decoration: underline;
}

button[type="submit"] {
    width: 100%;
    padding: 14px;
    background: #54880e;
    color: white;
    border: none;
    border-radius: 50px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 8px;
}

button[type="submit"]:hover {
    background: #436c0b;
    transform: translateY(-1px);
}

.business-types {
    margin-top: 32px;
    text-align: center;
}

.business-types p {
    color: white;
    font-size: 13px;
    margin-bottom: 12px;
}

.type-chips {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: center;
}

.type-chips span {
    background: transparent;
    padding: 6px 14px;
    border-radius: 50px;
    font-size: 12px;
    color: white;
    border: 1px solid white;
    transition: all 0.3s ease;
}

.type-chips span:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: #54880e;
}

.sign-link {
    text-align: center;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
}

.sign-link p {
    color: white;
}

.sign-link a {
    color: white;
    text-decoration: none;
    font-weight: 600;
}

.sign-link a:hover {
    text-decoration: underline;
}

.auth-alert {
    margin-bottom: 20px;
    border-radius: 50px;
    padding: 12px 16px;
    font-size: 13px;
    text-align: center;
}

.auth-alert-error {
    background: rgba(239, 68, 68, 0.2);
    color: #ff6b6b;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

@media (max-width: 900px) {
    .input-row--three {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 700px) {
    .form-wrapper {
        padding: 0 15px;
    }
    
    .input-row {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .input-box input {
        padding: 10px 0;
    }
    
    .terms-check {
        justify-content: flex-start;
    }
}
</style>

<script>
document.getElementById('register-form')?.addEventListener('submit', function(e) {
    const password = document.querySelector('input[name="password"]').value;
    const confirm = document.querySelector('input[name="confirm_password"]').value;

    if (password !== confirm) {
        e.preventDefault();
        alert('Les mots de passe ne correspondent pas');
        return false;
    }

    if (window.Symphony && typeof window.Symphony.showLoader === 'function') {
        window.Symphony.showLoader();
    }
});
</script>