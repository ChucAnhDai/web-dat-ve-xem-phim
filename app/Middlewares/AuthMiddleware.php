<?php

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use Exception;

class AuthMiddleware
{
    private Auth $auth;
    private Logger $logger;

    public function __construct()
    {
        $this->auth = new Auth();
        $this->logger = new Logger();
    }

    public function handle(Request $request, Response $response): bool
    {
        $token = $request->bearerToken();
        if (!$token) {
            $response->error('Unauthorized', ['token' => ['Missing bearer token.']], 401);
            return false;
        }

        try {
            $payload = $this->auth->verifyToken($token);
            $request->setAttribute('auth', $payload);
            return true;
        } catch (Exception $exception) {
            $this->logger->error('JWT verification failed', ['error' => $exception->getMessage()]);
            $response->error('Unauthorized', ['token' => [$exception->getMessage()]], 401);
            return false;
        }
    }

    public function requireRole(array $roles, Request $request, Response $response): bool
    {
        $auth = $request->getAttribute('auth');
        $role = $auth['role'] ?? null;
        if (!$role || !in_array($role, $roles, true)) {
            $response->error('Forbidden', ['role' => ['Insufficient permissions.']], 403);
            return false;
        }

        return true;
    }
}
