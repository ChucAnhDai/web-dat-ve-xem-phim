<?php

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\ShowtimeCatalogService;

class ShowtimeCatalogController
{
    private ShowtimeCatalogService $service;

    public function __construct(?ShowtimeCatalogService $service = null)
    {
        $this->service = $service ?? new ShowtimeCatalogService();
    }

    public function getSeatMap(Request $request, Response $response)
    {
        $showtimeId = (int) $request->getRouteParam('id');
        $result = $this->service->getSeatMap($showtimeId);
        $status = (int) ($result['status'] ?? 200);

        if (isset($result['errors'])) {
            return $response->json(['errors' => $result['errors']], $status);
        }

        return $response->json(['data' => $result['data'] ?? []], $status);
    }
}
