<?php

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\MovieCatalogService;

class MovieCatalogController
{
    private MovieCatalogService $service;

    public function __construct(?MovieCatalogService $service = null)
    {
        $this->service = $service ?? new MovieCatalogService();
    }

    public function listMovies(Request $request, Response $response)
    {
        $result = $this->service->listMovies($request->getBody());
        $status = (int) ($result['status'] ?? 200);

        if (isset($result['errors'])) {
            return $response->json(['errors' => $result['errors']], $status);
        }

        return $response->json(['data' => $result['data'] ?? []], $status);
    }

    public function getMovieDetail(Request $request, Response $response)
    {
        $result = $this->service->getMovieDetail((string) $request->getRouteParam('slug'));
        $status = (int) ($result['status'] ?? 200);

        if (isset($result['errors'])) {
            return $response->json(['errors' => $result['errors']], $status);
        }

        return $response->json(['data' => $result['data'] ?? []], $status);
    }
}
