<?php

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\ShopCartService;
use Exception;

class ShopCartController
{
    private ShopCartService $service;
    private Auth $auth;

    public function __construct(?ShopCartService $service = null, ?Auth $auth = null)
    {
        $this->service = $service ?? new ShopCartService();
        $this->auth = $auth ?? new Auth();
    }

    public function getCart(Request $request, Response $response)
    {
        return $this->respond($request, $response, $this->service->getCart(
            $this->resolveUserId($request),
            $request->cookie($this->service->cartCookieName())
        ));
    }

    public function addItem(Request $request, Response $response)
    {
        return $this->respond($request, $response, $this->service->addItem(
            $request->getBody(),
            $this->resolveUserId($request),
            $request->cookie($this->service->cartCookieName())
        ));
    }

    public function updateItem(Request $request, Response $response)
    {
        return $this->respond($request, $response, $this->service->updateItemQuantity(
            (int) $request->getRouteParam('productId'),
            $request->getBody(),
            $this->resolveUserId($request),
            $request->cookie($this->service->cartCookieName())
        ));
    }

    public function removeItem(Request $request, Response $response)
    {
        return $this->respond($request, $response, $this->service->removeItem(
            (int) $request->getRouteParam('productId'),
            $this->resolveUserId($request),
            $request->cookie($this->service->cartCookieName())
        ));
    }

    public function clearCart(Request $request, Response $response)
    {
        return $this->respond($request, $response, $this->service->clearCart(
            $this->resolveUserId($request),
            $request->cookie($this->service->cartCookieName())
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
