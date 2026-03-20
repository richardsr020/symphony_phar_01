<?php

namespace App\Controllers;

class ChatController extends Controller
{
    public function index(): void
    {
        $this->redirect('/dashboard?error=feature_disabled');
    }
}
