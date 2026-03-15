<?php

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\ShowtimeCatalogService;
use App\Support\TicketSessionManager;

class ShowtimeCatalogController
{
    private ShowtimeCatalogService $service;
    private TicketSessionManager $sessions;

    public function __construct(?ShowtimeCatalogService $service = null, ?TicketSessionManager $sessions = null)
    {
        $this->service = $service ?? new ShowtimeCatalogService();
        $this->sessions = $sessions ?? new TicketSessionManager();
    }

    public function listShowtimes(Request $request, Response $response)
    {
        $result = $this->service->listShowtimes($request->getBody());
        $status = (int) ($result['status'] ?? 200);

        if (isset($result['errors'])) {
            return $response->json(['errors' => $result['errors']], $status);
        }

        return $response->json(['data' => $result['data'] ?? []], $status);
    }

    public function getSeatMap(Request $request, Response $response)
    {
        $showtimeId = (int) $request->getRouteParam('id');
        $result = $this->service->getSeatMapForSession($showtimeId, $this->sessions->resolve($request));
        $status = (int) ($result['status'] ?? 200);

        if (isset($result['errors'])) {
            return $response->json(['errors' => $result['errors']], $status);
        }

        return $response->json(['data' => $result['data'] ?? []], $status);
    }
}
