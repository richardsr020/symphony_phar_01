<?php

namespace App\Controllers;

class PageController extends Controller
{
    public function forgotPassword(): void
    {
        $this->renderAuth('forgot-password', [
            'title' => 'Mot de passe oublié',
            'subtitle' => 'Réinitialisez votre accès',
        ]);
    }

    public function terms(): void
    {
        $this->renderAuth('terms', [
            'title' => 'Conditions d\'utilisation',
            'subtitle' => 'Version de démonstration investisseur',
        ]);
    }
}
