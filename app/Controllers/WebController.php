<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

class WebController
{
    public function showLoginForm(Request $request, Response $response)
    {
        return $response->view('auth/login');
    }

    public function showRegisterForm(Request $request, Response $response)
    {
        return $response->view('auth/register');
    }
}
