<?php

namespace App\Controllers\Auth;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

class AuthController
{
    private AuthService $service;

    public function __construct(?AuthService $service = null)
    {
        $this->service = $service ?? new AuthService();
    }

    public function register(Request $request, Response $response)
    {
        $result = $this->service->register($request->getBody());
        if (isset($result['errors'])) {
            return $response->json(['errors' => $result['errors']], 422);
        }

        return $response->json(['message' => 'Registered successfully', 'data' => $result['data']], 201);
    }

    public function login(Request $request, Response $response)
    {
        $result = $this->service->login($request->getBody());
        if (isset($result['errors'])) {
            return $response->json(['errors' => $result['errors']], 401);
        }

        return $response->json(['message' => 'Login successful', 'data' => $result['data']]);
    }

    public function profile(Request $request, Response $response)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return $response->json(['errors' => ['token' => ['Missing bearer token.']]], 401);
        }

        $result = $this->service->profile($token);
        if (isset($result['errors'])) {
            return $response->json(['errors' => $result['errors']], 401);
        }

        return $response->json(['data' => $result['data']]);
    }
}
