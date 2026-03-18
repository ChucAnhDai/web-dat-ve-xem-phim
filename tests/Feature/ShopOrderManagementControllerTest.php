<?php

namespace Tests\Feature;

use App\Controllers\Admin\ShopOrderManagementController;
use App\Core\Request;
use App\Core\Response;
use App\Services\AdminShopOrderManagementService;
use PHPUnit\Framework\TestCase;

class ShopOrderManagementControllerTest extends TestCase
{
    public function testListShopOrdersReturnsPayload(): void
    {
        $service = new FeatureFakeAdminShopOrderManagementService();
        $service->result = [
            'status' => 200,
            'data' => [
                'items' => [['order_code' => 'SHP-001']],
            ],
        ];

        $controller = new ShopOrderManagementController($service);
        $response = new CapturingShopOrderManagementResponse();

        $controller->listShopOrders(new Request(), $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('SHP-001', $response->payload['data']['items'][0]['order_code']);
    }

    public function testUpdateShopOrderStatusPassesActorAndPayload(): void
    {
        $service = new FeatureFakeAdminShopOrderManagementService();
        $service->result = [
            'status' => 200,
            'data' => [
                'order_code' => 'SHP-002',
                'status' => 'confirmed',
            ],
        ];

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request = new Request();
        $request->setRouteParams(['id' => 9]);
        $request->setAttribute('auth', [
            'user_id' => 11,
            'role' => 'admin',
        ]);

        $response = new CapturingShopOrderManagementResponse();
        $controller = new ShopOrderManagementController($service);

        $_POST = ['status' => 'confirmed'];
        $controller->updateShopOrderStatus($request, $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(9, $service->receivedId);
        $this->assertSame(11, $service->receivedActorId);
        $this->assertSame('confirmed', $service->receivedPayload['status']);
    }
}

class FeatureFakeAdminShopOrderManagementService extends AdminShopOrderManagementService
{
    public array $result = [];
    public ?int $receivedId = null;
    public ?int $receivedActorId = null;
    public array $receivedPayload = [];

    public function __construct()
    {
    }

    public function listShopOrders(array $filters): array
    {
        return $this->result;
    }

    public function getShopOrder(int $orderId): array
    {
        $this->receivedId = $orderId;

        return $this->result;
    }

    public function listOrderDetails(array $filters): array
    {
        return $this->result;
    }

    public function updateShopOrderStatus(int $orderId, array $payload, ?int $actorId = null): array
    {
        $this->receivedId = $orderId;
        $this->receivedActorId = $actorId;
        $this->receivedPayload = $payload;

        return $this->result;
    }
}

class CapturingShopOrderManagementResponse extends Response
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
