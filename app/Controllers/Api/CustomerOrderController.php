<?php

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\CustomerOrderService;

class CustomerOrderController
{
    private CustomerOrderService $service;

    public function __construct(?CustomerOrderService $service = null)
    {
        $this->service = $service ?? new CustomerOrderService();
    }

    public function listMyOrders(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->listMyOrders($this->actorId($request), $request->getBody())
        );
    }

    public function lookupOrder(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->lookupOrder($request->getBody(), $this->actorId($request))
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
