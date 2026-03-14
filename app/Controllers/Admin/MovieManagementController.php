<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Services\MovieManagementService;

class MovieManagementController
{
    private MovieManagementService $service;

    public function __construct(?MovieManagementService $service = null)
    {
        $this->service = $service ?? new MovieManagementService();
    }

    public function listMovies(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->listMovies($request->getBody()));
    }

    public function getMovie(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->getMovie((int) $request->getRouteParam('id')));
    }

    public function createMovie(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->createMovie($request->getBody(), $this->actorId($request)),
            'Movie created successfully'
        );
    }

    public function updateMovie(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->updateMovie((int) $request->getRouteParam('id'), $request->getBody(), $this->actorId($request)),
            'Movie updated successfully'
        );
    }

    public function archiveMovie(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->archiveMovie((int) $request->getRouteParam('id'), $this->actorId($request)),
            'Movie archived successfully'
        );
    }

    public function listCategories(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->listCategories($request->getBody()));
    }

    public function getCategory(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->getCategory((int) $request->getRouteParam('id')));
    }

    public function createCategory(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->createCategory($request->getBody(), $this->actorId($request)),
            'Movie category created successfully'
        );
    }

    public function updateCategory(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->updateCategory((int) $request->getRouteParam('id'), $request->getBody(), $this->actorId($request)),
            'Movie category updated successfully'
        );
    }

    public function deactivateCategory(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->deactivateCategory((int) $request->getRouteParam('id'), $this->actorId($request)),
            'Movie category deactivated successfully'
        );
    }

    public function listAssets(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->listAssets($request->getBody()));
    }

    public function getAsset(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->getAsset((int) $request->getRouteParam('id')));
    }

    public function createAsset(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->createAsset($request->getBody(), $this->actorId($request)),
            'Movie asset created successfully'
        );
    }

    public function updateAsset(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->updateAsset((int) $request->getRouteParam('id'), $request->getBody(), $this->actorId($request)),
            'Movie asset updated successfully'
        );
    }

    public function archiveAsset(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->archiveAsset((int) $request->getRouteParam('id'), $this->actorId($request)),
            'Movie asset archived successfully'
        );
    }

    public function listReviews(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->listReviews($request->getBody()));
    }

    public function getReview(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->getReview((int) $request->getRouteParam('id')));
    }

    public function moderateReview(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->moderateReview((int) $request->getRouteParam('id'), $request->getBody(), $this->actorId($request)),
            'Movie review moderated successfully'
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
