<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use Exception;

class AdminAuthController
{
    private AuthService $service;
    private Auth $auth;

    public function __construct(?AuthService $service = null, ?Auth $auth = null)
    {
        $this->service = $service ?? new AuthService();
        $this->auth = $auth ?? new Auth();
    }

    public function showLogin(Request $request, Response $response): void
    {
        if ($this->isAuthenticatedAdmin($request)) {
            $response->redirect($request->appBasePath() . '/admin');
        }

        $this->renderLogin($response, $request, [], []);
    }

    public function login(Request $request, Response $response): void
    {
        $result = $this->service->loginAdmin($request->getBody());
        if (isset($result['errors'])) {
            $response->setStatusCode(isset($result['errors']['identifier']) || isset($result['errors']['password']) ? 422 : 401);
            $this->renderLogin($response, $request, $result['errors'], [
                'identifier' => trim((string) ($request->getBody()['identifier'] ?? '')),
                'remember' => (string) ($request->getBody()['remember'] ?? '') === '1',
            ]);
            return;
        }

        $token = (string) ($result['data']['token'] ?? '');
        if ($token === '') {
            $response->setStatusCode(500);
            $this->renderLogin($response, $request, ['server' => ['Admin login did not return a token.']], []);
            return;
        }

        $response->setCookie('cinemax_admin_token', $token, [
            'path' => $this->cookiePath($request),
            'expires' => $this->cookieExpiry($request),
            'httponly' => true,
        ]);

        $response->redirect($request->appBasePath() . '/admin');
    }

    public function logout(Request $request, Response $response): void
    {
        $token = $request->bearerToken();
        $this->service->logout($token ?? '');

        $response->clearCookie('cinemax_admin_token', [
            'path' => $this->cookiePath($request),
            'httponly' => true,
        ]);

        $response->redirect($request->appBasePath() . '/admin/login');
    }

    private function renderLogin(Response $response, Request $request, array $errors, array $old): void
    {
        $response->view('admin/auth/login', [
            'title' => 'Admin Login - CineShop Admin',
            'layout' => 'admin/layouts/auth',
            'activePage' => 'admin-login',
            'errors' => $errors,
            'old' => $old,
        ]);
    }

    private function isAuthenticatedAdmin(Request $request): bool
    {
        $token = $request->bearerToken();
        if (!$token) {
            return false;
        }

        try {
            $payload = $this->auth->verifyToken($token);
        } catch (Exception $exception) {
            return false;
        }

        return ($payload['role'] ?? null) === 'admin';
    }

    private function cookiePath(Request $request): string
    {
        return $request->appBasePath() !== '' ? $request->appBasePath() : '/';
    }

    private function cookieExpiry(Request $request): int
    {
        $remember = (string) ($request->getBody()['remember'] ?? '') === '1';

        return $remember ? (time() + (60 * 60 * 24)) : 0;
    }
}
