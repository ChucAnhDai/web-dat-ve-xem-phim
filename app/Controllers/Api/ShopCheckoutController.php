<?php

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\ShopCheckoutService;
use App\Services\UnifiedCheckoutService;
use App\Support\TicketSessionManager;
use Exception;

class ShopCheckoutController
{
    private ShopCheckoutService $service;
    private Auth $auth;

    public function __construct(?ShopCheckoutService $service = null, ?Auth $auth = null)
    {
        $this->service = $service ?? new UnifiedCheckoutService();
        $this->auth = $auth ?? new Auth();
    }

    public function getCheckout(Request $request, Response $response)
    {
        return $this->respond($request, $response, $this->getCheckoutResult($request));
    }

    public function createCheckout(Request $request, Response $response)
    {
        return $this->respond($request, $response, $this->createCheckoutResult($request));
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
            return null;
        }

        try {
            $payload = $this->auth->verifyToken($token);
        } catch (Exception $exception) {
            return null;
        }

        $userId = $payload['user_id'] ?? null;

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

    private function getCheckoutResult(Request $request): array
    {
        if ($this->service instanceof UnifiedCheckoutService) {
            return $this->service->getCheckoutWithTickets(
                $this->resolveUserId($request),
                $request->cookie($this->service->cartCookieName()),
                $request->cookie(TicketSessionManager::COOKIE_NAME)
            );
        }

        return $this->service->getCheckout(
            $this->resolveUserId($request),
            $request->cookie($this->service->cartCookieName())
        );
    }

    private function createCheckoutResult(Request $request): array
    {
        $payload = $request->getBody();
        $idempotencyKey = $this->resolveIdempotencyKey($payload);
        $requestContext = [
            'base_url' => $this->baseUrl($request),
            'client_ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        ];

        if ($this->service instanceof UnifiedCheckoutService) {
            $requestContext['ticket_session_token'] = $request->cookie(TicketSessionManager::COOKIE_NAME);

            return $this->service->createCheckoutWithTickets(
                $payload,
                $idempotencyKey,
                $this->resolveUserId($request),
                $request->cookie($this->service->cartCookieName()),
                $request->cookie(TicketSessionManager::COOKIE_NAME),
                $requestContext
            );
        }

        return $this->service->createCheckout(
            $payload,
            $idempotencyKey,
            $this->resolveUserId($request),
            $request->cookie($this->service->cartCookieName()),
            $requestContext
        );
    }

    private function baseUrl(Request $request): string
    {
        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return rtrim($scheme . '://' . $host . $request->appBasePath(), '/');
    }

    private function applyCartCookie(Request $request, Response $response, array $result): void
    {
        if (!empty($result['clear_session_cookie'])) {
            $response->clearCookie($this->service->cartCookieName(), [
                'path' => $request->appBasePath() !== '' ? $request->appBasePath() : '/',
            ]);

            return;
        }

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
