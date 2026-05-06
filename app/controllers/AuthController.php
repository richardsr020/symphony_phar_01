<?php

namespace App\Controllers;

use App\Core\RolePermissions;
use App\Core\Session;
use App\Models\Company;
use App\Models\User;

class AuthController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    public function showLogin(): void
    {
        if ($this->hasValidUserSession()) {
            $this->redirect('/dashboard');
        }

        $this->clearInvalidUserSession();

        $registrationOpen = !$this->userModel->hasUsers();

        if ($registrationOpen) {
            $this->redirect('/register');
        }

        $this->renderAuth('login', [
            'title' => 'Connexion',
            'subtitle' => 'Accédez à votre tableau de bord',
            'registrationOpen' => $registrationOpen,
            'authError' => $this->resolveAuthError($_GET['error'] ?? ''),
            'authSuccess' => $this->resolveAuthSuccess($_GET['success'] ?? ''),
        ]);
    }

    public function showRegister(): void
    {
        if ($this->hasValidUserSession()) {
            $this->redirect('/dashboard');
        }

        $this->clearInvalidUserSession();

        if ($this->userModel->hasUsers()) {
            $this->redirect('/login?error=registration_closed');
        }

        $this->renderAuth('register', [
            'title' => 'Créer un compte',
            'subtitle' => 'Commencez avec  phar',
            'authError' => $this->resolveAuthError($_GET['error'] ?? ''),
        ]);
    }

    public function login(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $this->redirect('/login?error=missing_credentials');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->redirect('/login?error=invalid_email');
        }

        $user = $this->userModel->findByEmail($email);
        if ($user === null || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            $this->redirect('/login?error=invalid_credentials');
        }

        if ((int) ($user['is_active'] ?? 0) !== 1) {
            $this->redirect('/login?error=account_disabled');
        }

        $companyModel = new Company();
        if ($companyModel->isLockedForCompanyId((int) ($user['company_id'] ?? 0))) {
            $this->redirect('/login?error=company_locked');
        }

        $this->userModel->updateLastLogin((int) $user['id']);
        $this->authenticateUser($user);

        $this->redirect('/dashboard');
    }

    public function register(): void
    {
        if ($this->userModel->hasUsers()) {
            $this->redirect('/login?error=registration_closed');
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $termsAccepted = isset($_POST['terms']);

        if ($email === '') {
            $this->redirect('/register?error=missing_fields');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->redirect('/register?error=invalid_email');
        }

        if (strlen($password) < 8) {
            $this->redirect('/register?error=weak_password');
        }

        if ($password !== $confirmPassword) {
            $this->redirect('/register?error=password_mismatch');
        }

        if ($termsAccepted === false) {
            $this->redirect('/register?error=terms_required');
        }

        if ($this->userModel->findByEmail($email) !== null) {
            $this->redirect('/register?error=email_taken');
        }

        try {
            $createdUserId = $this->userModel->createInitialAdmin([
                'email' => $email,
                'password' => $password,
            ]);
        } catch (\Throwable $exception) {
            $this->redirect('/register?error=register_failed');
        }

        $createdUser = $this->userModel->findById($createdUserId);
        if ($createdUser === null) {
            $this->redirect('/register?error=register_failed');
        }

        $this->authenticateUser($createdUser);

        $this->redirect('/dashboard?welcome=1');
    }

    public function logout(): void
    {
        Session::destroy();
        $this->redirect('/login');
    }

    private function hasValidUserSession(): bool
    {
        $user = Session::get('user');

        return Session::get('is_authenticated') === true
            && is_array($user)
            && (int) ($user['id'] ?? 0) > 0
            && (int) ($user['company_id'] ?? 0) > 0;
    }

    private function clearInvalidUserSession(): void
    {
        if (Session::get('is_authenticated') !== true) {
            return;
        }

        if ($this->hasValidUserSession()) {
            return;
        }

        Session::remove('is_authenticated');
        Session::remove('user');
    }

    private function authenticateUser(array $user): void
    {
        Session::regenerate();
        Session::set('is_authenticated', true);
        Session::set('user', [
            'id' => (int) $user['id'],
            'company_id' => (int) $user['company_id'],
            'company_name' => (string) ($user['company_name'] ?? ''),
            'first_name' => (string) ($user['first_name'] ?? ''),
            'last_name' => (string) ($user['last_name'] ?? ''),
            'name' => trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))),
            'matricule' => (string) ($user['matricule'] ?? $user['email'] ?? ''),
            'role' => RolePermissions::normalizeRole((string) ($user['role'] ?? '')),
        ]);
    }

    private function resolveAuthError(string $code): string
    {
        $messages = [
            'missing_credentials' => 'Email et mot de passe requis.',
            'missing_fields' => 'Veuillez remplir tous les champs obligatoires.',
            'invalid_email' => 'Email invalide.',
            'invalid_credentials' => 'Email ou mot de passe incorrect.',
            'account_disabled' => 'Ce compte est désactivé.',
            'session_expired' => 'Votre session a expiré ou votre compte n existe plus. Reconnectez-vous.',
            'company_locked' => 'Accès suspendu pour cette entreprise. Contactez NestCorporation.',
            'weak_password' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password_mismatch' => 'Les mots de passe ne correspondent pas.',
            'terms_required' => 'Vous devez accepter les conditions d\'utilisation.',
            'email_taken' => 'Cet email est déjà utilisé.',
            'register_failed' => 'Impossible de créer le compte pour le moment.',
            'registration_closed' => 'Les inscriptions sont fermées. Contactez l\'administrateur.',
        ];

        return $messages[$code] ?? '';
    }

    private function resolveAuthSuccess(string $code): string
    {
        $messages = [
            'user_created' => 'Utilisateur créé avec succès. Il peut maintenant se connecter.',
        ];

        return $messages[$code] ?? '';
    }
}
