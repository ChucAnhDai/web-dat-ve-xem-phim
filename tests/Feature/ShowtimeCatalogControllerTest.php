<?php

namespace Tests\Feature;

use App\Controllers\Api\ShowtimeCatalogController;
use App\Core\Request;
use App\Core\Response;
use App\Services\ShowtimeCatalogService;
use PHPUnit\Framework\TestCase;

class ShowtimeCatalogControllerTest extends TestCase
{
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

        $controller = new ShowtimeCatalogController($service);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $request = new Request();
        $request->setRouteParams(['id' => 501]);
        $response = new FeatureCapturingShowtimeResponse();

        $controller->getSeatMap($request, $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('Detail Movie', $response->payload['data']['showtime']['movie_title']);
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

    public function __construct()
    {
    }

    public function getSeatMap(int $showtimeId): array
    {
        return $this->result;
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
