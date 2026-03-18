<?php

namespace Tests\Feature;

use App\Controllers\Api\UserShopOrderController;
use App\Core\Request;
use App\Core\Response;
use App\Services\UserShopOrderService;
use PHPUnit\Framework\TestCase;

class UserShopOrderControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_SERVER = [];
    }

    public function testListSessionOrdersUsesGuestSessionCookie(): void
    {
        $service = new FeatureFakeUserShopOrderService();
        $service->result = [
            'status' => 200,
            'data' => [
                'source' => 'session',
                'items' => [],
            ],
        ];
        $controller = new UserShopOrderController($service);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_COOKIE = [
            'cinemax_cart' => str_repeat('a', 64),
        ];
        $response = new UserShopOrderCapturingResponse();

        $controller->listSessionOrders(new Request(), $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(str_repeat('a', 64), $service->lastSessionToken);
    }

    public function testCancelMyOrderUsesActorIdFromAuthAttribute(): void
    {
        $service = new FeatureFakeUserShopOrderService();
        $service->result = [
            'status' => 200,
            'data' => [
                'order' => ['order_code' => 'SHP-MEMBER-001', 'status' => 'cancelled'],
            ],
        ];
        $controller = new UserShopOrderController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request = new Request();
        $request->setAttribute('auth', ['user_id' => 42]);
        $request->setRouteParams(['id' => 9]);
        $response = new UserShopOrderCapturingResponse();

        $controller->cancelMyOrder($request, $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(42, $service->lastUserId);
        $this->assertSame(9, $service->lastOrderId);
    }

    public function testLookupGuestOrderPassesRequestPayloadToService(): void
    {
        $service = new FeatureFakeUserShopOrderService();
        $service->result = [
            'status' => 200,
            'data' => [
                'source' => 'lookup',
                'order' => ['order_code' => 'SHP-GUEST-001'],
            ],
        ];
        $controller = new UserShopOrderController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'order_code' => 'SHP-GUEST-001',
            'contact_email' => 'guest@example.com',
        ];
        $response = new UserShopOrderCapturingResponse();

        $controller->lookupGuestOrder(new Request(), $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('SHP-GUEST-001', $service->lastPayload['order_code'] ?? null);
        $this->assertSame('guest@example.com', $service->lastPayload['contact_email'] ?? null);
    }
}

class FeatureFakeUserShopOrderService extends UserShopOrderService
{
    public array $result = [];
    public ?int $lastUserId = null;
    public ?string $lastSessionToken = null;
    public ?int $lastOrderId = null;
    public array $lastPayload = [];

    public function __construct()
    {
    }

    public function cartCookieName(): string
    {
        return 'cinemax_cart';
    }

    public function listSessionOrders(?string $sessionToken, array $filters): array
    {
        $this->lastSessionToken = $sessionToken;
        $this->lastPayload = $filters;

        return $this->result;
    }

    public function cancelMyOrder(int $userId, int $orderId): array
    {
        $this->lastUserId = $userId;
        $this->lastOrderId = $orderId;

        return $this->result;
    }

    public function lookupGuestOrder(array $payload): array
    {
        $this->lastPayload = $payload;

        return $this->result;
    }
}

class UserShopOrderCapturingResponse extends Response
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
