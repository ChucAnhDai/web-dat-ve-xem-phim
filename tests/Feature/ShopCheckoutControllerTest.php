<?php

namespace Tests\Feature;

use App\Controllers\Api\ShopCheckoutController;
use App\Core\Request;
use App\Core\Response;
use App\Services\ShopCheckoutService;
use PHPUnit\Framework\TestCase;

class ShopCheckoutControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_SERVER = [];
    }

    public function testGetCheckoutReturnsDataAndSetsCartCookie(): void
    {
        $service = new FeatureFakeShopCheckoutService();
        $service->result = [
            'status' => 200,
            'data' => [
                'cart' => ['id' => 11, 'item_count' => 2, 'items' => []],
                'checkout_ready' => true,
                'payment_methods' => [],
                'fulfillment_methods' => [],
                'active_order' => null,
            ],
            'session_token' => str_repeat('a', 64),
            'session_cookie_expires_at' => time() + 3600,
        ];
        $controller = new ShopCheckoutController($service);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $response = new ShopCheckoutCapturingResponse();

        $controller->getCheckout(new Request(), $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertTrue($response->payload['data']['checkout_ready']);
        $this->assertSame('cinemax_cart', $response->cookies[0]['name'] ?? null);
    }

    public function testCreateCheckoutPassesIdempotencyKeyToService(): void
    {
        $service = new FeatureFakeShopCheckoutService();
        $service->result = [
            'status' => 201,
            'data' => [
                'order' => ['order_code' => 'SHP-001'],
                'payment' => ['payment_method' => 'vnpay'],
                'redirect_url' => 'https://sandbox.vnpayment.vn',
            ],
            'session_token' => str_repeat('b', 64),
            'session_cookie_expires_at' => time() + 3600,
        ];
        $controller = new ShopCheckoutController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] = 'shop-idem-001';
        $_POST = [
            'contact_name' => 'Checkout User',
            'contact_email' => 'checkout@example.com',
            'contact_phone' => '0901234567',
            'fulfillment_method' => 'pickup',
            'payment_method' => 'vnpay',
        ];
        $_COOKIE = [
            'cinemax_cart' => str_repeat('c', 64),
        ];
        $response = new ShopCheckoutCapturingResponse();

        $controller->createCheckout(new Request(), $response);

        $this->assertSame(201, $response->statusCode);
        $this->assertSame('shop-idem-001', $service->lastIdempotencyKey);
        $this->assertSame(str_repeat('c', 64), $service->lastSessionToken);
    }
}

class FeatureFakeShopCheckoutService extends ShopCheckoutService
{
    public array $result = [];
    public ?int $lastUserId = null;
    public ?string $lastSessionToken = null;
    public ?string $lastIdempotencyKey = null;
    public array $lastPayload = [];

    public function __construct()
    {
    }

    public function cartCookieName(): string
    {
        return 'cinemax_cart';
    }

    public function getCheckout(?int $userId = null, ?string $sessionToken = null): array
    {
        $this->lastUserId = $userId;
        $this->lastSessionToken = $sessionToken;

        return $this->result;
    }

    public function createCheckout(array $payload, ?string $idempotencyKey, ?int $userId = null, ?string $sessionToken = null, array $requestContext = []): array
    {
        $this->lastPayload = $payload;
        $this->lastIdempotencyKey = $idempotencyKey;
        $this->lastUserId = $userId;
        $this->lastSessionToken = $sessionToken;

        return $this->result;
    }
}

class ShopCheckoutCapturingResponse extends Response
{
    public int $statusCode = 200;
    public array $payload = [];
    public array $cookies = [];

    public function setStatusCode(int $code): void
    {
        $this->statusCode = $code;
    }

    public function json($data, int $statusCode = 200): void
    {
        $this->setStatusCode($statusCode);
        $this->payload = $data;
    }

    public function setCookie(string $name, string $value, array $options = []): void
    {
        $this->cookies[] = [
            'name' => $name,
            'value' => $value,
            'options' => $options,
        ];
        $_COOKIE[$name] = $value;
    }
}
