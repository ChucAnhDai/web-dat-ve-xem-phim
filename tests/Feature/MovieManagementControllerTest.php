<?php

namespace Tests\Feature;

use App\Controllers\Admin\MovieManagementController;
use App\Core\Request;
use App\Core\Response;
use App\Services\MovieManagementService;
use PHPUnit\Framework\TestCase;

class MovieManagementControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
    }

    public function testListMoviesReturnsDataPayload(): void
    {
        $service = new FeatureFakeMovieManagementService();
        $service->result = [
            'status' => 200,
            'data' => [
                'items' => [['id' => 1, 'title' => 'Demo Movie']],
                'meta' => ['total' => 1, 'page' => 1, 'per_page' => 20, 'total_pages' => 1],
            ],
        ];
        $controller = new MovieManagementController($service);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $response = new FeatureCapturingResponse();

        $controller->listMovies(new Request(), $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame([['id' => 1, 'title' => 'Demo Movie']], $response->payload['data']['items']);
    }

    public function testCreateMovieReturnsCreatedResponse(): void
    {
        $service = new FeatureFakeMovieManagementService();
        $service->result = [
            'status' => 201,
            'data' => ['id' => 7, 'title' => 'Demo Movie'],
        ];
        $controller = new MovieManagementController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['title' => 'Demo Movie'];
        $request = new Request();
        $request->setAttribute('auth', ['user_id' => 10, 'role' => 'admin']);
        $response = new FeatureCapturingResponse();

        $controller->createMovie($request, $response);

        $this->assertSame(201, $response->statusCode);
        $this->assertSame('Movie created successfully', $response->payload['message']);
        $this->assertSame(7, $response->payload['data']['id']);
    }

    public function testModerateReviewReturnsErrors(): void
    {
        $service = new FeatureFakeMovieManagementService();
        $service->result = [
            'status' => 422,
            'errors' => ['status' => ['Review status is invalid.']],
        ];
        $controller = new MovieManagementController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['_method' => 'PUT', 'status' => 'invalid', 'is_visible' => '1'];
        $request = new Request();
        $request->setRouteParams(['id' => 99]);
        $request->setAttribute('auth', ['user_id' => 10, 'role' => 'admin']);
        $response = new FeatureCapturingResponse();

        $controller->moderateReview($request, $response);

        $this->assertSame(422, $response->statusCode);
        $this->assertSame(['Review status is invalid.'], $response->payload['errors']['status']);
    }

    public function testImportMovieFromOphimReturnsCreatedMessage(): void
    {
        $service = new FeatureFakeMovieManagementService();
        $service->result = [
            'status' => 201,
            'data' => [
                'movie' => ['id' => 8, 'title' => 'Squid Game'],
                'sync' => [
                    'movie_id' => 8,
                    'created' => 1,
                    'source_slug' => 'tro-choi-con-muc',
                ],
            ],
        ];
        $controller = new MovieManagementController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['slug' => 'tro-choi-con-muc', 'sync_images' => '1'];
        $request = new Request();
        $request->setAttribute('auth', ['user_id' => 10, 'role' => 'admin']);
        $response = new FeatureCapturingResponse();

        $controller->importMovieFromOphim($request, $response);

        $this->assertSame(201, $response->statusCode);
        $this->assertSame('Movie imported from OPhim successfully', $response->payload['message']);
        $this->assertSame(8, $response->payload['data']['movie']['id']);
    }

    public function testImportMovieListFromOphimReturnsBatchMessage(): void
    {
        $service = new FeatureFakeMovieManagementService();
        $service->result = [
            'status' => 200,
            'data' => [
                'list_slug' => 'phim-chieu-rap',
                'processed_count' => 12,
                'created_count' => 9,
                'updated_count' => 3,
                'failed_count' => 0,
            ],
        ];
        $controller = new MovieManagementController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['list_slug' => 'phim-chieu-rap', 'page' => '1', 'limit' => '12'];
        $request = new Request();
        $request->setAttribute('auth', ['user_id' => 10, 'role' => 'admin']);
        $response = new FeatureCapturingResponse();

        $controller->importMovieListFromOphim($request, $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('Movie list synced from OPhim successfully', $response->payload['message']);
        $this->assertSame(12, $response->payload['data']['processed_count']);
    }
}

class FeatureFakeMovieManagementService extends MovieManagementService
{
    public array $result = [];

    public function __construct()
    {
    }

    public function listMovies(array $filters): array
    {
        return $this->result;
    }

    public function createMovie(array $payload, ?int $actorId = null): array
    {
        return $this->result;
    }

    public function moderateReview(int $id, array $payload, ?int $actorId = null): array
    {
        return $this->result;
    }

    public function importMovieFromOphim(array $payload, ?int $actorId = null): array
    {
        return $this->result;
    }

    public function importMovieListFromOphim(array $payload, ?int $actorId = null): array
    {
        return $this->result;
    }
}

class FeatureCapturingResponse extends Response
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
