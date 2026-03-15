<?php

namespace Tests\Feature;

use App\Controllers\Api\TicketHoldController;
use App\Core\Request;
use App\Core\Response;
use App\Services\TicketHoldService;
use App\Support\TicketSessionManager;
use PHPUnit\Framework\TestCase;

class TicketHoldControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_SERVER = [];
    }

    public function testCreateHoldUsesSessionTokenAndReturnsPayload(): void
    {
        $service = new FeatureFakeTicketHoldService();
        $service->result = [
            'status' => 200,
            'data' => [
                'showtime_id' => 77,
                'seat_ids' => [1, 2],
                'seat_count' => 2,
            ],
        ];
        $sessions = new FeatureFakeHoldSessionManager();
        $sessions->ensuredToken = str_repeat('b', 48);

        $controller = new TicketHoldController($service, $sessions);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'showtime_id' => '77',
            'seat_ids' => ['1', '2'],
        ];
        $response = new FeatureCapturingTicketHoldResponse();

        $controller->createHold(new Request(), $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('77', $service->createPayload['showtime_id']);
        $this->assertSame(['1', '2'], $service->createPayload['seat_ids']);
        $this->assertSame(str_repeat('b', 48), $service->createSessionToken);
        $this->assertSame(2, $response->payload['data']['seat_count']);
    }

    public function testReleaseHoldWithoutSessionReturnsNoopPayload(): void
    {
        $controller = new TicketHoldController(new FeatureFakeTicketHoldService(), new FeatureFakeHoldSessionManager());
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $request = new Request();
        $request->setRouteParams(['showtimeId' => 91]);
        $response = new FeatureCapturingTicketHoldResponse();

        $controller->releaseHold($request, $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(91, $response->payload['data']['showtime_id']);
        $this->assertSame(0, $response->payload['data']['released_count']);
    }

    public function testReleaseHoldUsesResolvedSessionToken(): void
    {
        $service = new FeatureFakeTicketHoldService();
        $service->result = [
            'status' => 200,
            'data' => [
                'showtime_id' => 12,
                'released_count' => 3,
            ],
        ];
        $sessions = new FeatureFakeHoldSessionManager();
        $sessions->resolvedToken = str_repeat('c', 48);

        $controller = new TicketHoldController($service, $sessions);
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $request = new Request();
        $request->setRouteParams(['showtimeId' => 12]);
        $response = new FeatureCapturingTicketHoldResponse();

        $controller->releaseHold($request, $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(12, $service->releaseShowtimeId);
        $this->assertSame(str_repeat('c', 48), $service->releaseSessionToken);
        $this->assertSame(3, $response->payload['data']['released_count']);
    }
}

class FeatureFakeTicketHoldService extends TicketHoldService
{
    public array $result = [];
    public array $createPayload = [];
    public ?string $createSessionToken = null;
    public ?int $releaseShowtimeId = null;
    public ?string $releaseSessionToken = null;

    public function __construct()
    {
    }

    public function createHold(array $payload, string $sessionToken, ?int $userId = null): array
    {
        $this->createPayload = $payload;
        $this->createSessionToken = $sessionToken;

        return $this->result;
    }

    public function releaseHold(int $showtimeId, string $sessionToken, ?int $userId = null): array
    {
        $this->releaseShowtimeId = $showtimeId;
        $this->releaseSessionToken = $sessionToken;

        return $this->result;
    }
}

class FeatureFakeHoldSessionManager extends TicketSessionManager
{
    public ?string $resolvedToken = null;
    public ?string $ensuredToken = null;

    public function resolve(Request $request): ?string
    {
        return $this->resolvedToken;
    }

    public function ensure(Request $request, Response $response): string
    {
        return $this->ensuredToken ?? str_repeat('d', 48);
    }
}

class FeatureCapturingTicketHoldResponse extends Response
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
