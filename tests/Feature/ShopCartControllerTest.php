<?php

namespace Tests\Feature;

use App\Controllers\Api\ShopCartController;
use App\Core\Request;
use App\Core\Response;
use App\Services\ShopCartService;
use PHPUnit\Framework\TestCase;

class ShopCartControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_SERVER = [];
    }

    public function testGetCartReturnsDataAndSetsSessionCookie(): void
    {
        $service = new FeatureFakeShopCartService();
        $service->result = [
            'status' => 200,
            'data' => [
                'cart' => [
                    'id' => null,
                    'item_count' => 0,
                    'items' => [],
                ],
            ],
            'session_token' => str_repeat('a', 64),
            'session_cookie_expires_at' => time() + 3600,
        ];
        $controller = new ShopCartController($service);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $response = new ShopCartCapturingResponse();

        $controller->getCart(new Request(), $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(0, $response->payload['data']['cart']['item_count']);
        $this->assertSame('cinemax_cart', $response->cookies[0]['name'] ?? null);
    }

    public function testAddItemPassesCookieSessionTokenToService(): void
    {
        $service = new FeatureFakeShopCartService();
        $service->result = [
            'status' => 201,
            'data' => [
                'cart' => [
                    'id' => 1,
                    'item_count' => 1,
                    'items' => [['product_id' => 11, 'quantity' => 1]],
                ],
            ],
            'session_token' => str_repeat('b', 64),
            'session_cookie_expires_at' => time() + 3600,
        ];
        $controller = new ShopCartController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'product_id' => '11',
            'quantity' => '1',
        ];
        $_COOKIE = [
            'cinemax_cart' => str_repeat('c', 64),
        ];
        $response = new ShopCartCapturingResponse();

        $controller->addItem(new Request(), $response);

        $this->assertSame(201, $response->statusCode);
        $this->assertSame('11', $service->lastPayload['product_id'] ?? null);
        $this->assertSame(str_repeat('c', 64), $service->lastSessionToken);
    }
}

class FeatureFakeShopCartService extends ShopCartService
{
    public array $result = [];
    public array $lastPayload = [];
    public ?int $lastUserId = null;
    public ?string $lastSessionToken = null;

    public function __construct()
    {
    }

    public function cartCookieName(): string
    {
        return 'cinemax_cart';
    }

    public function getCart(?int $userId = null, ?string $sessionToken = null): array
    {
        $this->lastUserId = $userId;
        $this->lastSessionToken = $sessionToken;

        return $this->result;
    }

    public function addItem(array $payload, ?int $userId = null, ?string $sessionToken = null): array
    {
        $this->lastPayload = $payload;
        $this->lastUserId = $userId;
        $this->lastSessionToken = $sessionToken;

        return $this->result;
    }
}

class ShopCartCapturingResponse extends Response
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
