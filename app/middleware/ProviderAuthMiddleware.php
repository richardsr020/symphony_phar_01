<?php

namespace App\Middleware;

use App\Core\Session;

class ProviderAuthMiddleware
{
    public static function handle(): bool
    {
        Session::start();

        $providerUser = Session::get('provider_user');
        $authenticated = Session::get('provider_authenticated') === true;

        if ($authenticated && is_array($providerUser) && isset($providerUser['id'])) {
            return true;
        }

        header('Location: /provider/login');
        return false;
    }
}
