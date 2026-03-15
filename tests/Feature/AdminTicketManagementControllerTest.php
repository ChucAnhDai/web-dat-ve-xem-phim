<?php

namespace Tests\Feature;

use App\Controllers\Admin\TicketManagementController;
use App\Core\Request;
use App\Core\Response;
use App\Services\AdminTicketManagementService;
use PHPUnit\Framework\TestCase;

class AdminTicketManagementControllerTest extends TestCase
{
    public function testListTicketOrdersReturnsPayload(): void
    {
        $service = new FeatureFakeAdminTicketManagementService();
        $service->result = [
            'status' => 200,
            'data' => [
                'items' => [['order_code' => 'TKT-001']],
            ],
        ];

        $controller = new TicketManagementController($service);
        $response = new FeatureCapturingAdminTicketResponse();

        $controller->listTicketOrders(new Request(), $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('TKT-001', $response->payload['data']['items'][0]['order_code']);
    }

    public function testGetTicketDetailReturnsPayload(): void
    {
        $service = new FeatureFakeAdminTicketManagementService();
        $service->result = [
            'status' => 200,
            'data' => [
                'ticket_code' => 'TIC-001',
            ],
        ];

        $controller = new TicketManagementController($service);
        $request = new Request();
        $request->setRouteParams(['id' => 7]);
        $response = new FeatureCapturingAdminTicketResponse();

        $controller->getTicketDetail($request, $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(7, $service->receivedId);
        $this->assertSame('TIC-001', $response->payload['data']['ticket_code']);
    }
}

class FeatureFakeAdminTicketManagementService extends AdminTicketManagementService
{
    public array $result = [];
    public ?int $receivedId = null;

    public function __construct()
    {
    }

    public function listTicketOrders(array $filters): array
    {
        return $this->result;
    }

    public function getTicketOrder(int $orderId): array
    {
        $this->receivedId = $orderId;

        return $this->result;
    }

    public function listTicketDetails(array $filters): array
    {
        return $this->result;
    }

    public function getTicketDetail(int $ticketId): array
    {
        $this->receivedId = $ticketId;

        return $this->result;
    }

    public function listActiveHolds(array $filters): array
    {
        return $this->result;
    }
}

class FeatureCapturingAdminTicketResponse extends Response
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
