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
            $status = isset($result['errors']['identifier']) || isset($result['errors']['password']) ? 422 : 401;
            return $response->json(['errors' => $result['errors']], $status);
        }

        return $response->json(['message' => 'Login successful', 'data' => $result['data']]);
    }

    public function adminLogin(Request $request, Response $response)
    {
        $result = $this->service->loginAdmin($request->getBody());
        if (isset($result['errors'])) {
            $status = isset($result['errors']['identifier']) || isset($result['errors']['password']) ? 422 : 401;
            return $response->json(['errors' => $result['errors']], $status);
        }

        return $response->json(['message' => 'Admin login successful', 'data' => $result['data']]);
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

    public function logout(Request $request, Response $response)
    {
        $token = $request->bearerToken();
        $result = $this->service->logout($token ?? '');

        $response->clearCookie('cinemax_token', [
            'path' => $request->appBasePath() !== '' ? $request->appBasePath() : '/',
        ]);

        if (isset($result['errors'])) {
            return $response->json(['errors' => $result['errors']], 401);
        }

        return $response->json(['message' => 'Logout successful'], 200);
    }

    public function adminLogout(Request $request, Response $response)
    {
        $token = $request->bearerToken();
        $result = $this->service->logout($token ?? '');

        $response->clearCookie('cinemax_admin_token', [
            'path' => $request->appBasePath() !== '' ? $request->appBasePath() : '/',
        ]);

        if (isset($result['errors'])) {
            return $response->json(['errors' => $result['errors']], 401);
        }

        return $response->json(['message' => 'Admin logout successful'], 200);
    }

    public function updatePassword(Request $request, Response $response)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return $response->json(['errors' => ['token' => ['Missing bearer token.']]], 401);
        }

        try {
            $auth = new \App\Core\Auth();
            $payload = $auth->verifyToken($token);
            $userId = (int) ($payload['user_id'] ?? 0);
        } catch (\Exception $e) {
            return $response->json(['errors' => ['token' => [$e->getMessage()]]], 401);
        }

        $result = $this->service->updatePassword($userId, $request->getBody());
        if (isset($result['errors'])) {
            return $response->json(['errors' => $result['errors']], 422);
        }

        return $response->json(['message' => 'Password updated successfully'], 200);
    }
}
