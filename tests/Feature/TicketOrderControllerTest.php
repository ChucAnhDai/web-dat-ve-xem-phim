<?php

namespace Tests\Feature;

use App\Controllers\Api\TicketOrderController;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\TicketCheckoutService;
use App\Support\TicketSessionManager;
use PHPUnit\Framework\TestCase;

class TicketOrderControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_SERVER = [];
    }

    public function testPreviewOrderReturnsConflictWhenSessionIsMissing(): void
    {
        $controller = new TicketOrderController(
            new FeatureFakeTicketCheckoutService(),
            new FeatureFakeTicketOrderSessionManager(),
            new FeatureFakeTicketOrderAuth()
        );

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $response = new FeatureCapturingTicketOrderResponse();

        $controller->previewOrder(new Request(), $response);

        $this->assertSame(409, $response->statusCode);
        $this->assertSame(['Seat hold is missing or expired.'], $response->payload['errors']['hold']);
    }

    public function testCreateOrderForwardsPayloadSessionAndOptionalUser(): void
    {
        $service = new FeatureFakeTicketCheckoutService();
        $service->result = [
            'status' => 201,
            'data' => [
                'order' => ['id' => 8, 'order_code' => 'TKT-123'],
            ],
        ];
        $sessions = new FeatureFakeTicketOrderSessionManager();
        $sessions->resolvedToken = str_repeat('a', 48);
        $auth = new FeatureFakeTicketOrderAuth();
        $auth->payload = ['user_id' => 17, 'role' => 'user'];

        $controller = new TicketOrderController($service, $sessions, $auth);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer sample-token';
        $_POST = [
            'showtime_id' => '42',
            'contact_name' => 'Guest Ticket',
        ];
        $response = new FeatureCapturingTicketOrderResponse();

        $controller->createOrder(new Request(), $response);

        $this->assertSame(201, $response->statusCode);
        $this->assertSame(str_repeat('a', 48), $service->sessionToken);
        $this->assertSame(17, $service->userId);
        $this->assertSame('42', $service->payload['showtime_id']);
        $this->assertSame('TKT-123', $response->payload['data']['order']['order_code']);
    }

    public function testActiveCheckoutReturnsServicePayload(): void
    {
        $service = new FeatureFakeTicketCheckoutService();
        $service->result = [
            'status' => 200,
            'data' => [
                'resume_available' => true,
                'order' => ['order_code' => 'TKT-RESUME'],
            ],
        ];
        $sessions = new FeatureFakeTicketOrderSessionManager();
        $sessions->resolvedToken = str_repeat('f', 48);

        $controller = new TicketOrderController($service, $sessions, new FeatureFakeTicketOrderAuth());
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $response = new FeatureCapturingTicketOrderResponse();

        $controller->activeCheckout(new Request(), $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(str_repeat('f', 48), $service->sessionToken);
        $this->assertTrue($response->payload['data']['resume_available']);
        $this->assertSame('TKT-RESUME', $response->payload['data']['order']['order_code']);
    }
}

class FeatureFakeTicketCheckoutService extends TicketCheckoutService
{
    public array $result = [];
    public array $payload = [];
    public ?string $sessionToken = null;
    public ?int $userId = null;

    public function __construct()
    {
    }

    public function previewOrder(array $payload, string $sessionToken, ?int $userId = null): array
    {
        $this->payload = $payload;
        $this->sessionToken = $sessionToken;
        $this->userId = $userId;

        return $this->result;
    }

    public function createOrder(array $payload, string $sessionToken, ?int $userId = null): array
    {
        $this->payload = $payload;
        $this->sessionToken = $sessionToken;
        $this->userId = $userId;

        return $this->result;
    }

    public function activeCheckout(?string $sessionToken, ?int $userId = null): array
    {
        $this->sessionToken = $sessionToken;
        $this->userId = $userId;

        return $this->result;
    }
}

class FeatureFakeTicketOrderSessionManager extends TicketSessionManager
{
    public ?string $resolvedToken = null;

    public function resolve(Request $request): ?string
    {
        return $this->resolvedToken;
    }
}

class FeatureFakeTicketOrderAuth extends Auth
{
    public array $payload = [];

    public function verifyToken(string $token): array
    {
        return $this->payload;
    }
}

class FeatureCapturingTicketOrderResponse extends Response
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
