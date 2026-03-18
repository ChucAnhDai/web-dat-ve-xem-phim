<?php

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\ShopCheckoutService;
use Exception;

class ShopCheckoutController
{
    private ShopCheckoutService $service;
    private Auth $auth;

    public function __construct(?ShopCheckoutService $service = null, ?Auth $auth = null)
    {
        $this->service = $service ?? new ShopCheckoutService();
        $this->auth = $auth ?? new Auth();
    }

    public function getCheckout(Request $request, Response $response)
    {
        return $this->respond($request, $response, $this->service->getCheckout(
            $this->resolveUserId($request),
            $request->cookie($this->service->cartCookieName())
        ));
    }

    public function createCheckout(Request $request, Response $response)
    {
        $payload = $request->getBody();

        return $this->respond($request, $response, $this->service->createCheckout(
            $payload,
            $this->resolveIdempotencyKey($payload),
            $this->resolveUserId($request),
            $request->cookie($this->service->cartCookieName()),
            [
                'base_url' => $this->baseUrl($request),
                'client_ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ]
        ));
    }

    private function respond(Request $request, Response $response, array $result)
    {
        $this->applyCartCookie($request, $response, $result);
        $status = (int) ($result['status'] ?? 200);

        if (isset($result['errors'])) {
            return $response->json(['errors' => $result['errors']], $status);
        }

        return $response->json(['data' => $result['data'] ?? []], $status);
    }

    private function resolveUserId(Request $request): ?int
    {
        $token = $request->bearerToken();
        if (!is_string($token) || trim($token) === '') {
            error_log('[ShopCheckout] bearerToken() returned empty — no Authorization header or auth cookie found');
            return null;
        }

        try {
            $payload = $this->auth->verifyToken($token);
        } catch (Exception $exception) {
            error_log('[ShopCheckout] verifyToken failed: ' . $exception->getMessage());
            return null;
        }

        $userId = $payload['user_id'] ?? null;
        if ($userId === null) {
            error_log('[ShopCheckout] verifyToken OK but user_id is null in payload');
        }

        return is_numeric($userId) ? (int) $userId : null;
    }

    private function resolveIdempotencyKey(array $payload): ?string
    {
        $headerKey = trim((string) ($_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? ''));
        if ($headerKey !== '') {
            return $headerKey;
        }

        $bodyKey = trim((string) ($payload['idempotency_key'] ?? ''));

        return $bodyKey !== '' ? $bodyKey : null;
    }

    private function baseUrl(Request $request): string
    {
        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return rtrim($scheme . '://' . $host . $request->appBasePath(), '/');
    }

    private function applyCartCookie(Request $request, Response $response, array $result): void
    {
        $sessionToken = trim((string) ($result['session_token'] ?? ''));
        if ($sessionToken === '') {
            return;
        }

        $expiresAt = (int) ($result['session_cookie_expires_at'] ?? 0);
        $response->setCookie($this->service->cartCookieName(), $sessionToken, [
            'expires' => $expiresAt > 0 ? $expiresAt : 0,
            'path' => $request->appBasePath() !== '' ? $request->appBasePath() : '/',
        ]);
    }
}
