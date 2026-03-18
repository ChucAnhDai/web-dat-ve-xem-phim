<?php

namespace App\Controllers\Auth;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

class CustomerAuthPageController
{
    private AuthService $service;

    public function __construct(?AuthService $service = null)
    {
        $this->service = $service ?? new AuthService();
    }

    public function showLogin(Request $request, Response $response): void
    {
        $this->renderLogin($response, $request, [], []);
    }

    public function login(Request $request, Response $response): void
    {
        $body = $request->getBody();
        $result = $this->service->login($body);

        if (isset($result['errors'])) {
            $response->setStatusCode(isset($result['errors']['identifier']) || isset($result['errors']['password']) ? 422 : 401);
            $this->renderLogin($response, $request, $result['errors'], [
                'identifier' => trim((string) ($body['identifier'] ?? $body['email'] ?? '')),
                'remember' => (string) ($body['remember'] ?? '') === '1',
            ]);
            return;
        }

        $token = (string) ($result['data']['token'] ?? '');
        if ($token === '') {
            $response->setStatusCode(500);
            $this->renderLogin($response, $request, ['server' => ['Login did not return a token.']], [
                'identifier' => trim((string) ($body['identifier'] ?? $body['email'] ?? '')),
                'remember' => (string) ($body['remember'] ?? '') === '1',
            ]);
            return;
        }

        $response->view('auth/login-complete', [
            'title' => 'Signing In - CinemaX',
            'activePage' => 'login',
            'authToken' => $token,
            'redirectPath' => $this->resolveRedirectPath($request),
            'persistAuth' => (string) ($body['remember'] ?? '') === '1',
        ]);
    }

    private function renderLogin(Response $response, Request $request, array $errors, array $old): void
    {
        $response->view('auth/login', [
            'title' => 'Dang nhap - CinemaX',
            'activePage' => 'login',
            'errors' => $errors,
            'old' => $old,
            'redirect' => $this->resolveRedirectPath($request),
        ]);
    }

    private function resolveRedirectPath(Request $request): string
    {
        $body = $request->getBody();
        $redirect = $body['redirect'] ?? ($_GET['redirect'] ?? '/');

        return $this->sanitizeRedirectPath($request, is_string($redirect) ? $redirect : '/');
    }

    private function sanitizeRedirectPath(Request $request, string $redirectPath): string
    {
        $candidate = trim($redirectPath);
        if ($candidate === '' || $candidate[0] !== '/' || str_starts_with($candidate, '//') || str_contains($candidate, '\\')) {
            return '/';
        }

        $parts = parse_url($candidate);
        if ($parts === false) {
            return '/';
        }

        $path = '/' . ltrim((string) ($parts['path'] ?? '/'), '/');
        $appBase = $request->appBasePath();
        if ($appBase !== '' && ($path === $appBase || str_starts_with($path, $appBase . '/'))) {
            $path = '/' . ltrim(substr($path, strlen($appBase)), '/');
        }

        if (preg_match('#(^|/)\.\.?(/|$)#', $path)) {
            return '/';
        }

        if (in_array($path, ['/login', '/register'], true)) {
            return '/';
        }

        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#' . $parts['fragment'] : '';

        return $path . $query . $fragment;
    }
}
