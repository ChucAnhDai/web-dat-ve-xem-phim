<?php

namespace Tests\Feature;

use App\Controllers\Admin\ShowtimeManagementController;
use App\Core\Request;
use App\Core\Response;
use App\Services\ShowtimeManagementService;
use PHPUnit\Framework\TestCase;

class AdminShowtimeManagementControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
    }

    public function testListShowtimesReturnsDataPayload(): void
    {
        $service = new FeatureFakeAdminShowtimeManagementService();
        $service->result = [
            'status' => 200,
            'data' => [
                'items' => [['id' => 1, 'movie_title' => 'Showtime A']],
                'meta' => ['total' => 1, 'page' => 1, 'per_page' => 20, 'total_pages' => 1],
            ],
        ];

        $controller = new ShowtimeManagementController($service);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['scope' => 'archived'];
        $response = new FeatureCapturingAdminShowtimeResponse();

        $controller->listShowtimes(new Request(), $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('Showtime A', $response->payload['data']['items'][0]['movie_title']);
        $this->assertSame('archived', $service->lastFilters['scope']);
    }

    public function testCreateShowtimeReturnsCreatedMessage(): void
    {
        $service = new FeatureFakeAdminShowtimeManagementService();
        $service->result = [
            'status' => 201,
            'data' => ['id' => 9, 'movie_title' => 'Showtime B'],
        ];

        $controller = new ShowtimeManagementController($service);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['movie_id' => '4'];
        $request = new Request();
        $request->setAttribute('auth', ['user_id' => 3, 'role' => 'admin']);
        $response = new FeatureCapturingAdminShowtimeResponse();

        $controller->createShowtime($request, $response);

        $this->assertSame(201, $response->statusCode);
        $this->assertSame('Showtime created successfully', $response->payload['message']);
        $this->assertSame(9, $response->payload['data']['id']);
    }

    public function testArchiveShowtimeReturnsErrors(): void
    {
        $service = new FeatureFakeAdminShowtimeManagementService();
        $service->result = [
            'status' => 404,
            'errors' => ['showtime' => ['Showtime not found.']],
        ];

        $controller = new ShowtimeManagementController($service);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['_method' => 'DELETE'];
        $request = new Request();
        $request->setRouteParams(['id' => 99]);
        $request->setAttribute('auth', ['user_id' => 3, 'role' => 'admin']);
        $response = new FeatureCapturingAdminShowtimeResponse();

        $controller->archiveShowtime($request, $response);

        $this->assertSame(404, $response->statusCode);
        $this->assertSame(['Showtime not found.'], $response->payload['errors']['showtime']);
    }
}

class FeatureFakeAdminShowtimeManagementService extends ShowtimeManagementService
{
    public array $result = [];
    public array $lastFilters = [];

    public function __construct()
    {
    }

    public function listShowtimes(array $filters): array
    {
        $this->lastFilters = $filters;
        return $this->result;
    }

    public function createShowtime(array $payload, ?int $actorId = null): array
    {
        return $this->result;
    }

    public function archiveShowtime(int $id, ?int $actorId = null): array
    {
        return $this->result;
    }
}

class FeatureCapturingAdminShowtimeResponse extends Response
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
