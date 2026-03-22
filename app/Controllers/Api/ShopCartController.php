<?php

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\ShopCartService;
use App\Services\UnifiedCartService;
use App\Support\TicketSessionManager;
use Exception;

class ShopCartController
{
    private ShopCartService $service;
    private Auth $auth;

    public function __construct(?ShopCartService $service = null, ?Auth $auth = null)
    {
        $this->service = $service ?? new UnifiedCartService();
        $this->auth = $auth ?? new Auth();
    }

    public function getCart(Request $request, Response $response)
    {
        return $this->respond($request, $response, $this->getCartResult($request));
    }

    public function addItem(Request $request, Response $response)
    {
        return $this->respond($request, $response, $this->addItemResult($request));
    }

    public function updateItem(Request $request, Response $response)
    {
        return $this->respond($request, $response, $this->updateItemResult($request));
    }

    public function removeItem(Request $request, Response $response)
    {
        return $this->respond($request, $response, $this->removeItemResult($request));
    }

    public function removeTicketSelection(Request $request, Response $response)
    {
        $result = $this->service instanceof UnifiedCartService
            ? $this->service->removeTicketSelection(
                $this->resolveUserId($request),
                $request->cookie($this->service->cartCookieName()),
                $request->cookie(TicketSessionManager::COOKIE_NAME)
            )
            : $this->service->getCart(
                $this->resolveUserId($request),
                $request->cookie($this->service->cartCookieName())
            );

        return $this->respond($request, $response, $result);
    }

    public function clearCart(Request $request, Response $response)
    {
        return $this->respond($request, $response, $this->clearCartResult($request));
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

    private function getCartResult(Request $request): array
    {
        if ($this->service instanceof UnifiedCartService) {
            return $this->service->getCart(
                $this->resolveUserId($request),
                $request->cookie($this->service->cartCookieName()),
                $request->cookie(TicketSessionManager::COOKIE_NAME)
            );
        }

        return $this->service->getCart(
            $this->resolveUserId($request),
            $request->cookie($this->service->cartCookieName())
        );
    }

    private function addItemResult(Request $request): array
    {
        if ($this->service instanceof UnifiedCartService) {
            return $this->service->addItem(
                $request->getBody(),
                $this->resolveUserId($request),
                $request->cookie($this->service->cartCookieName()),
                $request->cookie(TicketSessionManager::COOKIE_NAME)
            );
        }

        return $this->service->addItem(
            $request->getBody(),
            $this->resolveUserId($request),
            $request->cookie($this->service->cartCookieName())
        );
    }

    private function updateItemResult(Request $request): array
    {
        if ($this->service instanceof UnifiedCartService) {
            return $this->service->updateItemQuantity(
                (int) $request->getRouteParam('productId'),
                $request->getBody(),
                $this->resolveUserId($request),
                $request->cookie($this->service->cartCookieName()),
                $request->cookie(TicketSessionManager::COOKIE_NAME)
            );
        }

        return $this->service->updateItemQuantity(
            (int) $request->getRouteParam('productId'),
            $request->getBody(),
            $this->resolveUserId($request),
            $request->cookie($this->service->cartCookieName())
        );
    }

    private function removeItemResult(Request $request): array
    {
        if ($this->service instanceof UnifiedCartService) {
            return $this->service->removeItem(
                (int) $request->getRouteParam('productId'),
                $this->resolveUserId($request),
                $request->cookie($this->service->cartCookieName()),
                $request->cookie(TicketSessionManager::COOKIE_NAME)
            );
        }

        return $this->service->removeItem(
            (int) $request->getRouteParam('productId'),
            $this->resolveUserId($request),
            $request->cookie($this->service->cartCookieName())
        );
    }

    private function clearCartResult(Request $request): array
    {
        if ($this->service instanceof UnifiedCartService) {
            return $this->service->clearCart(
                $this->resolveUserId($request),
                $request->cookie($this->service->cartCookieName()),
                $request->cookie(TicketSessionManager::COOKIE_NAME)
            );
        }

        return $this->service->clearCart(
            $this->resolveUserId($request),
            $request->cookie($this->service->cartCookieName())
        );
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
