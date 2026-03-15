<?php

namespace Tests\Feature;

use App\Controllers\Api\ShowtimeCatalogController;
use App\Core\Request;
use App\Core\Response;
use App\Services\ShowtimeCatalogService;
use App\Support\TicketSessionManager;
use PHPUnit\Framework\TestCase;

class ShowtimeCatalogControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_SERVER = [];
    }

    public function testListShowtimesReturnsCatalogPayload(): void
    {
        $service = new FeatureFakeShowtimeCatalogService();
        $service->result = [
            'status' => 200,
            'data' => [
                'items' => [['id' => 1, 'movie_title' => 'Catalog Movie']],
                'meta' => ['total' => 1, 'page' => 1, 'per_page' => 20, 'total_pages' => 1],
                'options' => ['movies' => [], 'cinemas' => [], 'cities' => []],
                'summary' => ['total_items' => 1, 'available' => 1, 'limited' => 0, 'sold_out' => 0],
            ],
        ];

        $controller = new ShowtimeCatalogController($service);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['movie_id' => '5'];
        $response = new FeatureCapturingShowtimeResponse();

        $controller->listShowtimes(new Request(), $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('Catalog Movie', $response->payload['data']['items'][0]['movie_title']);
    }

    public function testGetSeatMapReturnsDataPayload(): void
    {
        $service = new FeatureFakeShowtimeCatalogService();
        $service->result = [
            'status' => 200,
            'data' => [
                'showtime' => ['id' => 501, 'movie_title' => 'Detail Movie'],
                'seats' => [['id' => 1, 'label' => 'A1']],
                'summary' => ['available_seats' => 1],
            ],
        ];

        $sessions = new FeatureFakeTicketSessionManager();
        $sessions->resolvedToken = str_repeat('a', 48);

        $controller = new ShowtimeCatalogController($service, $sessions);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_COOKIE[FeatureFakeTicketSessionManager::COOKIE_NAME] = str_repeat('a', 48);
        $request = new Request();
        $request->setRouteParams(['id' => 501]);
        $response = new FeatureCapturingShowtimeResponse();

        $controller->getSeatMap($request, $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('Detail Movie', $response->payload['data']['showtime']['movie_title']);
        $this->assertSame(str_repeat('a', 48), $service->receivedSessionToken);
    }

    public function testGetSeatMapReturnsErrorPayload(): void
    {
        $service = new FeatureFakeShowtimeCatalogService();
        $service->result = [
            'status' => 404,
            'errors' => ['showtime' => ['Showtime not found.']],
        ];

        $controller = new ShowtimeCatalogController($service);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $request = new Request();
        $request->setRouteParams(['id' => 999]);
        $response = new FeatureCapturingShowtimeResponse();

        $controller->getSeatMap($request, $response);

        $this->assertSame(404, $response->statusCode);
        $this->assertSame(['Showtime not found.'], $response->payload['errors']['showtime']);
    }
}

class FeatureFakeShowtimeCatalogService extends ShowtimeCatalogService
{
    public array $result = [];
    public ?string $receivedSessionToken = null;

    public function __construct()
    {
    }

    public function getSeatMapForSession(int $showtimeId, ?string $sessionToken = null): array
    {
        $this->receivedSessionToken = $sessionToken;

        return $this->result;
    }

    public function listShowtimes(array $filters): array
    {
        return $this->result;
    }
}

class FeatureFakeTicketSessionManager extends TicketSessionManager
{
    public ?string $resolvedToken = null;

    public function resolve(Request $request): ?string
    {
        return $this->resolvedToken;
    }
}

class FeatureCapturingShowtimeResponse extends Response
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
