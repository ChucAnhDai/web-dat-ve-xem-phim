<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Services\CinemaManagementService;

class CinemaManagementController
{
    private CinemaManagementService $service;

    public function __construct(?CinemaManagementService $service = null)
    {
        $this->service = $service ?? new CinemaManagementService();
    }

    public function listCinemas(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->listCinemas($request->getBody()));
    }

    public function getCinema(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->getCinema((int) $request->getRouteParam('id')));
    }

    public function createCinema(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->createCinema($request->getBody(), $this->actorId($request)),
            'Cinema created successfully'
        );
    }

    public function updateCinema(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->updateCinema((int) $request->getRouteParam('id'), $request->getBody(), $this->actorId($request)),
            'Cinema updated successfully'
        );
    }

    public function archiveCinema(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->archiveCinema((int) $request->getRouteParam('id'), $this->actorId($request)),
            'Cinema archived successfully'
        );
    }

    public function listRooms(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->listRooms($request->getBody()));
    }

    public function getRoom(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->getRoom((int) $request->getRouteParam('id')));
    }

    public function createRoom(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->createRoom($request->getBody(), $this->actorId($request)),
            'Room created successfully'
        );
    }

    public function updateRoom(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->updateRoom((int) $request->getRouteParam('id'), $request->getBody(), $this->actorId($request)),
            'Room updated successfully'
        );
    }

    public function archiveRoom(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->archiveRoom((int) $request->getRouteParam('id'), $this->actorId($request)),
            'Room archived successfully'
        );
    }

    public function getRoomSeats(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->getRoomSeats((int) $request->getRouteParam('id')));
    }

    public function replaceRoomSeats(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->replaceRoomSeats((int) $request->getRouteParam('id'), $request->getBody(), $this->actorId($request)),
            'Seat layout updated successfully'
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
