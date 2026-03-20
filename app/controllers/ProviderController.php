<?php

namespace App\Controllers;

use App\Core\SubscriptionService;
use App\Core\Session;
use App\Models\Company;
use App\Models\ProviderUser;
use Throwable;

class ProviderController extends Controller
{
    private Company $companyModel;
    private ProviderUser $providerUserModel;
    private SubscriptionService $subscriptionService;

    public function __construct()
    {
        $this->companyModel = new Company();
        $this->providerUserModel = new ProviderUser();
        $this->subscriptionService = new SubscriptionService();
    }

    public function showLogin(): void
    {
        if ($this->hasValidProviderSession()) {
            $this->redirect('/provider/dashboard');
        }

        $this->clearInvalidProviderSession();

        $this->renderProviderAuth('login', [
            'title' => 'Connexion fournisseur',
            'subtitle' => 'Espace opérateur NestCorporation',
            'error' => $this->resolveError((string) ($_GET['error'] ?? '')),
        ]);
    }

    public function login(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $this->redirect('/provider/login?error=missing_credentials');
        }

        $providerUser = $this->providerUserModel->findByEmail($email);
        if (
            $providerUser === null
            || !password_verify($password, (string) ($providerUser['password_hash'] ?? ''))
        ) {
            $this->redirect('/provider/login?error=invalid_credentials');
        }

        if ((int) ($providerUser['is_active'] ?? 0) !== 1) {
            $this->redirect('/provider/login?error=account_disabled');
        }

        Session::regenerate();
        Session::set('provider_authenticated', true);
        Session::set('provider_user', [
            'id' => (int) $providerUser['id'],
            'email' => (string) $providerUser['email'],
            'full_name' => (string) $providerUser['full_name'],
        ]);

        $this->providerUserModel->updateLastLogin((int) $providerUser['id']);
        $this->redirect('/provider/dashboard');
    }

    public function logout(): void
    {
        Session::remove('provider_authenticated');
        Session::remove('provider_user');
        $this->redirect('/provider/login');
    }

    public function dashboard(): void
    {
        $this->renderProvider('dashboard', [
            'title' => 'Pilotage NestCorporation',
            'subtitle' => 'Configuration des échéances et automatisation licences',
            'companies' => $this->companyModel->getAllForProvider(),
            'flashError' => $this->resolveError((string) ($_GET['error'] ?? '')),
            'flashSuccess' => $this->resolveSuccess((string) ($_GET['success'] ?? '')),
            'providerUser' => Session::get('provider_user', []),
        ]);
    }

    public function configureSubscription(string $id): void
    {
        $companyId = (int) $id;
        $dueDate = trim((string) ($_POST['subscription_ends_at'] ?? ''));
        $callbackUrl = trim((string) ($_POST['provider_callback_url'] ?? ''));
        $reminderInterval = (int) ($_POST['reminder_interval_days'] ?? \Config::SUBSCRIPTION_DEFAULT_REMINDER_INTERVAL_DAYS);
        $autoEnabled = isset($_POST['auto_subscription_enabled']);

        if ($companyId <= 0 || $this->companyModel->findById($companyId) === null) {
            $this->redirect('/provider/dashboard?error=company_not_found');
        }

        if ($callbackUrl !== '' && !filter_var($callbackUrl, FILTER_VALIDATE_URL)) {
            $this->redirect('/provider/dashboard?error=invalid_callback_url');
        }

        try {
            $this->subscriptionService->configureCompany(
                $companyId,
                $dueDate,
                $callbackUrl,
                $reminderInterval,
                $autoEnabled
            );
        } catch (Throwable $exception) {
            $this->redirect('/provider/dashboard?error=invalid_schedule');
        }

        $this->redirect('/provider/dashboard?success=schedule_saved');
    }

    public function runAutomationNow(): void
    {
        $companyId = null;
        if (isset($_POST['company_id']) && (int) $_POST['company_id'] > 0) {
            $companyId = (int) $_POST['company_id'];
        }

        $this->subscriptionService->runAutomation($companyId);
        $this->redirect('/provider/dashboard?success=automation_run');
    }

    public function cronRun(): void
    {
        $summary = $this->subscriptionService->runAutomation();
        $this->json([
            'status' => 'ok',
            'message' => 'Automatisation abonnement exécutée.',
            'summary' => $summary,
            'timestamp' => date('c'),
        ]);
    }

    public function apiRunAutomation(): void
    {
        $payload = $this->readPayload();
        $companyId = isset($payload['company_id']) ? (int) $payload['company_id'] : null;
        $companyId = ($companyId !== null && $companyId > 0) ? $companyId : null;

        $summary = $this->subscriptionService->runAutomation($companyId);
        $this->json([
            'status' => 'ok',
            'message' => 'Automatisation exécutée.',
            'summary' => $summary,
        ]);
    }

    public function apiActivateLicense(): void
    {
        $payload = $this->readPayload();
        $companyId = (int) ($payload['company_id'] ?? 0);
        $licenseKey = trim((string) ($payload['license_key'] ?? ''));
        $renewalDays = (int) ($payload['renewal_days'] ?? \Config::SUBSCRIPTION_RENEWAL_DAYS);

        if ($companyId <= 0) {
            $this->json(['error' => 'company_id requis'], 422);
            return;
        }

        $result = $this->subscriptionService->activateWithLicenseKey($companyId, $licenseKey, $renewalDays);
        if (($result['ok'] ?? false) !== true) {
            $this->json([
                'status' => 'error',
                'error' => $result['error'] ?? 'activation_failed',
            ], 422);
            return;
        }

        $this->json([
            'status' => 'ok',
            'message' => 'Réabonnement réactivé.',
            'next_due_date' => $result['next_due_date'] ?? null,
            'renewal_days' => $result['renewal_days'] ?? null,
        ]);
    }

    private function hasValidProviderSession(): bool
    {
        $providerUser = Session::get('provider_user');

        return Session::get('provider_authenticated') === true
            && is_array($providerUser)
            && isset($providerUser['id']);
    }

    private function clearInvalidProviderSession(): void
    {
        if (Session::get('provider_authenticated') !== true) {
            return;
        }

        if ($this->hasValidProviderSession()) {
            return;
        }

        Session::remove('provider_authenticated');
        Session::remove('provider_user');
    }

    private function readPayload(): array
    {
        if (!empty($_POST)) {
            return $_POST;
        }

        $raw = file_get_contents('php://input');
        if (!$raw) {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function resolveError(string $code): string
    {
        $messages = [
            'missing_credentials' => 'Email et mot de passe requis.',
            'invalid_credentials' => 'Identifiants fournisseur invalides.',
            'account_disabled' => 'Compte fournisseur désactivé.',
            'company_not_found' => 'Entreprise introuvable.',
            'invalid_callback_url' => 'URL callback invalide.',
            'invalid_schedule' => 'Configuration d\'échéance invalide.',
        ];

        return $messages[$code] ?? '';
    }

    private function resolveSuccess(string $code): string
    {
        $messages = [
            'schedule_saved' => 'Configuration enregistrée.',
            'automation_run' => 'Automatisation exécutée.',
        ];

        return $messages[$code] ?? '';
    }
}
