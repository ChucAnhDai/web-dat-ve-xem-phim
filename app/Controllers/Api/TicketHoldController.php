<?php

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\TicketHoldService;
use App\Support\TicketSessionManager;

class TicketHoldController
{
    private TicketHoldService $service;
    private TicketSessionManager $sessions;
    private Auth $auth;

    public function __construct(?TicketHoldService $service = null, ?TicketSessionManager $sessions = null, ?Auth $auth = null)
    {
        $this->service = $service ?? new TicketHoldService();
        $this->sessions = $sessions ?? new TicketSessionManager();
        $this->auth = $auth ?? new Auth();
    }

    public function createHold(Request $request, Response $response)
    {
        $sessionToken = $this->sessions->ensure($request, $response);
        $result = $this->service->createHold($request->getBody(), $sessionToken, $this->optionalUserId($request));

        return $this->respond($response, $result);
    }

    public function releaseHold(Request $request, Response $response)
    {
        $sessionToken = $this->sessions->resolve($request);
        if ($sessionToken === null) {
            return $response->json([
                'data' => [
                    'showtime_id' => (int) $request->getRouteParam('showtimeId'),
                    'released_count' => 0,
                ],
            ], 200);
        }

        $result = $this->service->releaseHold((int) $request->getRouteParam('showtimeId'), $sessionToken);

        return $this->respond($response, $result);
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

    private function respond(Response $response, array $result)
    {
        $status = (int) ($result['status'] ?? 200);

        if (isset($result['errors'])) {
            return $response->json(['errors' => $result['errors']], $status);
        }

        return $response->json(['data' => $result['data'] ?? []], $status);
    }
}
