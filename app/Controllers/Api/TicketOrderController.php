<?php

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\TicketCheckoutService;
use App\Support\TicketSessionManager;

class TicketOrderController
{
    private TicketCheckoutService $service;
    private TicketSessionManager $sessions;
    private Auth $auth;

    public function __construct(
        ?TicketCheckoutService $service = null,
        ?TicketSessionManager $sessions = null,
        ?Auth $auth = null
    ) {
        $this->service = $service ?? new TicketCheckoutService();
        $this->sessions = $sessions ?? new TicketSessionManager();
        $this->auth = $auth ?? new Auth();
    }

    public function previewOrder(Request $request, Response $response)
    {
        $sessionToken = $this->sessions->resolve($request);
        if ($sessionToken === null) {
            return $response->json(['errors' => ['hold' => ['Seat hold is missing or expired.']]], 409);
        }

        return $this->respond(
            $response,
            $this->service->previewOrder($request->getBody(), $sessionToken, $this->optionalUserId($request))
        );
    }

    public function createOrder(Request $request, Response $response)
    {
        $sessionToken = $this->sessions->resolve($request);
        if ($sessionToken === null) {
            return $response->json(['errors' => ['hold' => ['Seat hold is missing or expired.']]], 409);
        }

        return $this->respond(
            $response,
            $this->service->createOrder($request->getBody(), $sessionToken, $this->optionalUserId($request)),
            'Ticket order created successfully'
        );
    }

    private function respond(Response $response, array $result, ?string $successMessage = null)
    {
        $status = (int) ($result['status'] ?? 200);
        if (isset($result['errors'])) {
            return $response->json(['errors' => $result['errors']], $status);
        }

        $payload = ['data' => $result['data'] ?? []];
        if ($successMessage !== null) {
            $payload['message'] = $successMessage;
        }

        return $response->json($payload, $status);
    }

    private function optionalUserId(Request $request): ?int
    {
        $token = $request->bearerToken();
        if (!is_string($token) || trim($token) === '') {
            return null;
        }

        try {
            $payload = $this->auth->verifyToken($token);
            $userId = $payload['user_id'] ?? null;

            return $userId !== null ? (int) $userId : null;
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
