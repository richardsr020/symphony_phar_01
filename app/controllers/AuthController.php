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
            'subtitle' => 'Commencez avec Kombiphar',
            'authError' => $this->resolveAuthError($_GET['error'] ?? ''),
        ]);
    }

    public function login(): void
    {
        $matricule = trim((string) ($_POST['matricule'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($matricule === '' || $password === '') {
            $this->redirect('/login?error=missing_credentials');
        }

        $user = $this->userModel->findByMatricule($matricule);
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

        $matricule = trim((string) ($_POST['matricule'] ?? ''));
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $companyName = trim((string) ($_POST['company_name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $termsAccepted = isset($_POST['terms']);

        if ($matricule === '' || $firstName === '' || $lastName === '' || $companyName === '') {
            $this->redirect('/register?error=missing_fields');
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

        if ($this->userModel->findByMatricule($matricule) !== null) {
            $this->redirect('/register?error=matricule_taken');
        }

        try {
            $createdUserId = $this->userModel->createInitialAdmin([
                'matricule' => $matricule,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'company_name' => $companyName,
                'phone' => $phone,
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
            'missing_credentials' => 'Matricule et mot de passe requis.',
            'missing_fields' => 'Veuillez remplir tous les champs obligatoires.',
            'invalid_matricule' => 'Matricule invalide.',
            'invalid_credentials' => 'Matricule ou mot de passe incorrect.',
            'account_disabled' => 'Ce compte est désactivé.',
            'session_expired' => 'Votre session a expiré ou votre compte n existe plus. Reconnectez-vous.',
            'company_locked' => 'Accès suspendu pour cette entreprise. Contactez NestCorporation.',
            'weak_password' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password_mismatch' => 'Les mots de passe ne correspondent pas.',
            'terms_required' => 'Vous devez accepter les conditions d\'utilisation.',
            'matricule_taken' => 'Ce matricule est déjà utilisé.',
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
