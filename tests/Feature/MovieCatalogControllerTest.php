<?php

namespace Tests\Feature;

use App\Controllers\Api\MovieCatalogController;
use App\Core\Request;
use App\Core\Response;
use App\Services\MovieCatalogService;
use PHPUnit\Framework\TestCase;

class MovieCatalogControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
    }

    public function testListMoviesReturnsDataPayload(): void
    {
        $service = new FeatureFakeMovieCatalogService();
        $service->result = [
            'status' => 200,
            'data' => [
                'items' => [['id' => 1, 'title' => 'Catalog Movie']],
                'meta' => ['total' => 1, 'page' => 1, 'per_page' => 12, 'total_pages' => 1],
                'categories' => [],
                'filters' => ['status' => 'now_showing'],
            ],
        ];
        $controller = new MovieCatalogController($service);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $response = new FeatureCapturingCatalogResponse();

        $controller->listMovies(new Request(), $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('Catalog Movie', $response->payload['data']['items'][0]['title']);
    }

    public function testListMoviesReturnsErrorPayload(): void
    {
        $service = new FeatureFakeMovieCatalogService();
        $service->result = [
            'status' => 500,
            'errors' => ['server' => ['Failed to load movie catalog.']],
        ];
        $controller = new MovieCatalogController($service);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $response = new FeatureCapturingCatalogResponse();

        $controller->listMovies(new Request(), $response);

        $this->assertSame(500, $response->statusCode);
        $this->assertSame(['Failed to load movie catalog.'], $response->payload['errors']['server']);
    }

    public function testGetMovieDetailReturnsDataPayload(): void
    {
        $service = new FeatureFakeMovieCatalogService();
        $service->result = [
            'status' => 200,
            'data' => [
                'movie' => ['slug' => 'detail-movie', 'title' => 'Detail Movie'],
                'gallery' => [],
                'showtime_groups' => [],
                'reviews' => [],
                'related_movies' => [],
            ],
        ];
        $controller = new MovieCatalogController($service);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $request = new Request();
        $request->setRouteParams(['slug' => 'detail-movie']);
        $response = new FeatureCapturingCatalogResponse();

        $controller->getMovieDetail($request, $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('Detail Movie', $response->payload['data']['movie']['title']);
    }

    public function testGetMovieDetailReturnsErrorPayload(): void
    {
        $service = new FeatureFakeMovieCatalogService();
        $service->result = [
            'status' => 404,
            'errors' => ['movie' => ['Movie not found.']],
        ];
        $controller = new MovieCatalogController($service);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $request = new Request();
        $request->setRouteParams(['slug' => 'missing-movie']);
        $response = new FeatureCapturingCatalogResponse();

        $controller->getMovieDetail($request, $response);

        $this->assertSame(404, $response->statusCode);
        $this->assertSame(['Movie not found.'], $response->payload['errors']['movie']);
    }
}

class FeatureFakeMovieCatalogService extends MovieCatalogService
{
    public array $result = [];

    public function __construct()
    {
    }

    public function listMovies(array $filters): array
    {
        return $this->result;
    }

    public function getMovieDetail(string $slug): array
    {
        return $this->result;
    }
}

class FeatureCapturingCatalogResponse extends Response
{
    public int $statusCode = 200;
    public array $payload = [];

    public function setStatusCode(int $code): void
    {
        $this->statusCode = $code;
    }

    public function json($data, int $statusCode = 200): void
    {
        $this->setStatusCode($statusCode);
        $this->payload = $data;
    }
}
