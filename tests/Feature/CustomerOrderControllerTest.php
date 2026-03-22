<?php

namespace Tests\Feature;

use App\Controllers\Api\CustomerOrderController;
use App\Core\Request;
use App\Core\Response;
use App\Services\CustomerOrderService;
use PHPUnit\Framework\TestCase;

class CustomerOrderControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_SERVER = [];
    }

    public function testListMyOrdersUsesActorIdAndQueryFilters(): void
    {
        $service = new FeatureFakeCustomerOrderService();
        $service->result = [
            'status' => 200,
            'data' => [
                'source' => 'member',
                'items' => [],
                'summary' => ['total_orders' => 0],
            ],
        ];
        $controller = new CustomerOrderController($service);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['per_page' => '100', 'status' => 'pending'];
        $request = new Request();
        $request->setAttribute('auth', ['user_id' => 42]);
        $response = new CustomerOrderCapturingResponse();

        $controller->listMyOrders($request, $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(42, $service->lastUserId);
        $this->assertSame('pending', $service->lastPayload['status'] ?? null);
        $this->assertSame('100', $service->lastPayload['per_page'] ?? null);
    }

    public function testLookupOrderPassesBodyAndActorIdToService(): void
    {
        $service = new FeatureFakeCustomerOrderService();
        $service->result = [
            'status' => 200,
            'data' => [
                'source' => 'lookup',
                'order' => ['order_code' => 'ORD-001'],
            ],
        ];
        $controller = new CustomerOrderController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'order_code' => 'ORD-001',
            'contact_email' => 'guest@example.com',
            'contact_phone' => '0901234567',
        ];
        $request = new Request();
        $request->setAttribute('auth', ['user_id' => 7]);
        $response = new CustomerOrderCapturingResponse();

        $controller->lookupOrder($request, $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(7, $service->lastUserId);
        $this->assertSame('ORD-001', $service->lastPayload['order_code'] ?? null);
        $this->assertSame('guest@example.com', $service->lastPayload['contact_email'] ?? null);
        $this->assertSame('0901234567', $service->lastPayload['contact_phone'] ?? null);
    }
}

class FeatureFakeCustomerOrderService extends CustomerOrderService
{
    public array $result = [];
    public int $lastUserId = 0;
    public array $lastPayload = [];

    public function __construct()
    {
    }

    public function listMyOrders(int $userId, array $filters): array
    {
        $this->lastUserId = $userId;
        $this->lastPayload = $filters;

        return $this->result;
    }

    public function lookupOrder(array $payload, int $userId = 0): array
    {
        $this->lastUserId = $userId;
        $this->lastPayload = $payload;

        return $this->result;
    }
}

class CustomerOrderCapturingResponse extends Response
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
