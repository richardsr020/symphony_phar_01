<?php

namespace App\Middleware;

use App\Core\Security;

class CsrfMiddleware
{
    public static function handle(): bool
    {
        Security::validateCSRF();
        return true;
    }
}
