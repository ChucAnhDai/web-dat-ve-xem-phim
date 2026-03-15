<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Services\ShowtimeManagementService;

class ShowtimeManagementController
{
    private ShowtimeManagementService $service;

    public function __construct(?ShowtimeManagementService $service = null)
    {
        $this->service = $service ?? new ShowtimeManagementService();
    }

    public function listShowtimes(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->listShowtimes($request->getBody()));
    }

    public function getShowtime(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->getShowtime((int) $request->getRouteParam('id')));
    }

    public function createShowtime(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->createShowtime($request->getBody(), $this->actorId($request)),
            'Showtime created successfully'
        );
    }

    public function updateShowtime(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->updateShowtime((int) $request->getRouteParam('id'), $request->getBody(), $this->actorId($request)),
            'Showtime updated successfully'
        );
    }

    public function archiveShowtime(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->archiveShowtime((int) $request->getRouteParam('id'), $this->actorId($request)),
            'Showtime archived successfully'
        );
    }

    private function respond(Response $response, array $result, ?string $successMessage = null)
    {
        $status = (int) ($result['status'] ?? 200);

        if (isset($result['errors'])) {
            return $response->json(['errors' => $result['errors']], $status);
        }

        $payload = ['data' => $result['data'] ?? null];
        if ($successMessage !== null) {
            $payload['message'] = $successMessage;
        }

        return $response->json($payload, $status);
    }

    private function actorId(Request $request): ?int
    {
        $auth = $request->getAttribute('auth', []);
        $userId = $auth['user_id'] ?? null;

        return $userId !== null ? (int) $userId : null;
    }
}
