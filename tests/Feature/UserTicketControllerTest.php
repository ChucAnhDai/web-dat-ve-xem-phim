<?php

namespace Tests\Feature;

use App\Controllers\Api\UserTicketController;
use App\Core\Request;
use App\Core\Response;
use App\Services\UserTicketService;
use PHPUnit\Framework\TestCase;

class UserTicketControllerTest extends TestCase
{
    public function testListMyTicketsReturnsPayloadForAuthenticatedUser(): void
    {
        $service = new FeatureFakeUserTicketService();
        $service->result = [
            'status' => 200,
            'data' => [
                'items' => [['ticket_code' => 'TIC-001']],
            ],
        ];

        $controller = new UserTicketController($service);
        $request = new Request();
        $request->setAttribute('auth', ['user_id' => 11]);
        $response = new FeatureCapturingUserTicketResponse();

        $controller->listMyTickets($request, $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(11, $service->userId);
        $this->assertSame('TIC-001', $response->payload['data']['items'][0]['ticket_code']);
    }

    public function testListMyOrdersReturnsPayloadForAuthenticatedUser(): void
    {
        $service = new FeatureFakeUserTicketService();
        $service->result = [
            'status' => 200,
            'data' => [
                'items' => [['order_code' => 'TKT-001']],
            ],
        ];

        $controller = new UserTicketController($service);
        $request = new Request();
        $request->setAttribute('auth', ['user_id' => 22]);
        $response = new FeatureCapturingUserTicketResponse();

        $controller->listMyOrders($request, $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(22, $service->userId);
        $this->assertSame('TKT-001', $response->payload['data']['items'][0]['order_code']);
    }
}

class FeatureFakeUserTicketService extends UserTicketService
{
    public array $result = [];
    public ?int $userId = null;

    public function __construct()
    {
    }

    public function listMyTickets(int $userId, array $filters): array
    {
        $this->userId = $userId;

        return $this->result;
    }

    public function listMyOrders(int $userId, array $filters): array
    {
        $this->userId = $userId;

        return $this->result;
    }
}

class FeatureCapturingUserTicketResponse extends Response
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
