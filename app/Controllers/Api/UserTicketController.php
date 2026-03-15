<?php

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\UserTicketService;

class UserTicketController
{
    private UserTicketService $service;

    public function __construct(?UserTicketService $service = null)
    {
        $this->service = $service ?? new UserTicketService();
    }

    public function listMyTickets(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->listMyTickets($this->actorId($request), $request->getBody())
        );
    }

    public function listMyOrders(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->listMyOrders($this->actorId($request), $request->getBody())
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
        $userId = $auth['user_id'] ?? null;

        return $userId !== null ? (int) $userId : 0;
    }
}
