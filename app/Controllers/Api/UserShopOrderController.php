<?php

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\UserShopOrderService;

class UserShopOrderController
{
    private UserShopOrderService $service;

    public function __construct(?UserShopOrderService $service = null)
    {
        $this->service = $service ?? new UserShopOrderService();
    }

    public function listMyOrders(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->listMyOrders($this->actorId($request), $request->getBody())
        );
    }

    public function getMyOrder(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->getMyOrder($this->actorId($request), (int) $request->getRouteParam('id'))
        );
    }

    public function cancelMyOrder(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->cancelMyOrder($this->actorId($request), (int) $request->getRouteParam('id'))
        );
    }

    public function listSessionOrders(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->listSessionOrders($request->cookie($this->service->cartCookieName()), $request->getBody())
        );
    }

    public function getSessionOrder(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->getSessionOrder(
                $request->cookie($this->service->cartCookieName()),
                (int) $request->getRouteParam('id')
            )
        );
    }

    public function cancelSessionOrder(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->cancelSessionOrder(
                $request->cookie($this->service->cartCookieName()),
                (int) $request->getRouteParam('id')
            )
        );
    }

    public function lookupGuestOrder(Request $request, Response $response)
    {
        $userId = $this->actorId($request);
        if ($userId <= 0) {
            $token = $request->bearerToken();
            if ($token) {
                try {
                    $payload = (new \App\Core\Auth())->verifyToken($token);
                    $userId = (int) ($payload['user_id'] ?? 0);
                } catch (\Exception $e) {
                    // Ignore invalid token for guest lookup
                }
            }
        }

        return $this->respond(
            $response,
            $this->service->lookupGuestOrder($request->getBody(), $userId)
        );
    }

    public function cancelGuestOrder(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->cancelGuestOrder($request->getBody())
        );
    }

    private function respond(Response $response, array $result)
    {
        $status = (int) ($result['status'] ?? 200);
        if (isset($result['errors'])) {
            return $response->json(['errors' => $result['errors']], $status);
        }

        return $response->json(['data' => $result['data'] ?? []], $status);
    }

    private function actorId(Request $request): int
    {
        $auth = $request->getAttribute('auth', []);
        $userId = is_array($auth) ? ($auth['user_id'] ?? null) : null;

        return is_numeric($userId) ? (int) $userId : 0;
    }
}
