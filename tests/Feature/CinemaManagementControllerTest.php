<?php

namespace Tests\Feature;

use App\Controllers\Admin\CinemaManagementController;
use App\Core\Request;
use App\Core\Response;
use App\Services\CinemaManagementService;
use PHPUnit\Framework\TestCase;

class CinemaManagementControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
    }

    public function testListCinemasReturnsDataPayload(): void
    {
        $service = new FeatureFakeCinemaManagementService();
        $service->result = [
            'status' => 200,
            'data' => [
                'items' => [['id' => 1, 'name' => 'Cinema A']],
                'meta' => ['total' => 1, 'page' => 1, 'per_page' => 20, 'total_pages' => 1],
            ],
        ];

        $controller = new CinemaManagementController($service);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['scope' => 'archived'];
        $response = new FeatureCapturingCinemaManagementResponse();

        $controller->listCinemas(new Request(), $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('Cinema A', $response->payload['data']['items'][0]['name']);
        $this->assertSame('archived', $service->lastFilters['scope']);
    }

    public function testCreateCinemaReturnsCreatedMessage(): void
    {
        $service = new FeatureFakeCinemaManagementService();
        $service->result = [
            'status' => 201,
            'data' => ['id' => 7, 'name' => 'New Cinema'],
        ];

        $controller = new CinemaManagementController($service);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['name' => 'New Cinema'];
        $request = new Request();
        $request->setAttribute('auth', ['user_id' => 5, 'role' => 'admin']);
        $response = new FeatureCapturingCinemaManagementResponse();

        $controller->createCinema($request, $response);

        $this->assertSame(201, $response->statusCode);
        $this->assertSame('Cinema created successfully', $response->payload['message']);
        $this->assertSame(7, $response->payload['data']['id']);
    }

    public function testArchiveRoomReturnsErrors(): void
    {
        $service = new FeatureFakeCinemaManagementService();
        $service->result = [
            'status' => 409,
            'errors' => ['room' => ['Cannot archive room while published future showtimes exist.']],
        ];

        $controller = new CinemaManagementController($service);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['_method' => 'DELETE'];
        $request = new Request();
        $request->setRouteParams(['id' => 22]);
        $request->setAttribute('auth', ['user_id' => 5, 'role' => 'admin']);
        $response = new FeatureCapturingCinemaManagementResponse();

        $controller->archiveRoom($request, $response);

        $this->assertSame(409, $response->statusCode);
        $this->assertSame(['Cannot archive room while published future showtimes exist.'], $response->payload['errors']['room']);
    }
}

class FeatureFakeCinemaManagementService extends CinemaManagementService
{
    public array $result = [];
    public array $lastFilters = [];

    public function __construct()
    {
    }

    public function listCinemas(array $filters): array
    {
        $this->lastFilters = $filters;
        return $this->result;
    }

    public function createCinema(array $payload, ?int $actorId = null): array
    {
        return $this->result;
    }

    public function archiveRoom(int $id, ?int $actorId = null): array
    {
        return $this->result;
    }
}

class FeatureCapturingCinemaManagementResponse extends Response
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
