<?php

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\UserRepository;
use Exception;

class AdminPageMiddleware
{
    private Auth $auth;
    private UserRepository $users;
    private Logger $logger;

    public function __construct(?Auth $auth = null, ?UserRepository $users = null, ?Logger $logger = null)
    {
        $this->auth = $auth ?? new Auth();
        $this->users = $users ?? new UserRepository();
        $this->logger = $logger ?? new Logger();
    }

    public function handle(Request $request, Response $response): bool
    {
        $token = $request->bearerToken();
        if (!$token) {
            $this->redirectToLogin($request, $response);
            return false;
        }

        try {
            $payload = $this->auth->verifyToken($token);
        } catch (Exception $exception) {
            $this->logger->error('Admin page token verification failed', ['error' => $exception->getMessage()]);
            $this->clearAdminCookie($request, $response);
            $this->redirectToLogin($request, $response);
            return false;
        }

        if (($payload['role'] ?? null) !== 'admin') {
            $this->logger->error('Admin page access denied due to role mismatch', [
                'user_id' => $payload['user_id'] ?? null,
                'role' => $payload['role'] ?? null,
            ]);
            $this->clearAdminCookie($request, $response);
            $this->redirectToLogin($request, $response);
            return false;
        }

        $userId = (int) ($payload['user_id'] ?? 0);
        $user = $userId > 0 ? $this->users->findById($userId) : null;
        if (!$user || ($user['role'] ?? null) !== 'admin') {
            $this->logger->error('Admin page access denied because user no longer has admin access', [
                'user_id' => $userId,
            ]);
            $this->clearAdminCookie($request, $response);
            $this->redirectToLogin($request, $response);
            return false;
        }

        unset($user['password']);
        $request->setAttribute('auth', $payload);
        $request->setAttribute('adminUser', $user);

        return true;
    }

    private function redirectToLogin(Request $request, Response $response): void
    {
        $response->redirect($request->appBasePath() . '/admin/login');
    }

    private function clearAdminCookie(Request $request, Response $response): void
    {
        $response->clearCookie('cinemax_admin_token', [
            'path' => $this->cookiePath($request),
        ]);
    }

    private function cookiePath(Request $request): string
    {
        return $request->appBasePath() !== '' ? $request->appBasePath() : '/';
    }
}
