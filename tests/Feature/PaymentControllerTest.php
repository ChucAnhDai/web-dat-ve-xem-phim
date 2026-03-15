<?php

namespace Tests\Feature;

use App\Controllers\Api\PaymentController;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\PaymentService;
use App\Support\TicketSessionManager;
use PHPUnit\Framework\TestCase;

class PaymentControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_SERVER = [];
    }

    public function testCreateTicketVnpayIntentReturnsConflictWhenSessionIsMissing(): void
    {
        $controller = new PaymentController(
            new FeatureFakePaymentService(),
            new FeatureFakePaymentSessionManager(),
            new FeatureFakePaymentAuth()
        );

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $response = new FeatureCapturingPaymentResponse();

        $controller->createTicketVnpayIntent(new Request(), $response);

        $this->assertSame(409, $response->statusCode);
        $this->assertSame(['Seat hold is missing or expired.'], $response->payload['errors']['hold']);
    }

    public function testHandleVnpayIpnReturnsGatewayPayload(): void
    {
        $service = new FeatureFakePaymentService();
        $service->result = [
            'status' => 200,
            'data' => [
                'RspCode' => '00',
                'Message' => 'Confirm Success',
            ],
        ];

        $controller = new PaymentController(
            $service,
            new FeatureFakePaymentSessionManager(),
            new FeatureFakePaymentAuth()
        );
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [
            'vnp_TxnRef' => 'TKT-001',
            'vnp_Amount' => '17000000',
        ];
        $response = new FeatureCapturingPaymentResponse();

        $controller->handleVnpayIpn(new Request(), $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('00', $response->payload['RspCode']);
        $this->assertSame('TKT-001', $service->payload['vnp_TxnRef']);
    }
}

class FeatureFakePaymentService extends PaymentService
{
    public array $result = [];
    public array $payload = [];

    public function __construct()
    {
    }

    public function createTicketVnpayIntent(array $payload, string $sessionToken, ?int $userId, array $requestContext): array
    {
        $this->payload = $payload;

        return $this->result;
    }

    public function handleVnpayReturn(array $payload): array
    {
        $this->payload = $payload;

        return $this->result;
    }

    public function handleVnpayIpn(array $payload): array
    {
        $this->payload = $payload;

        return $this->result;
    }
}

class FeatureFakePaymentSessionManager extends TicketSessionManager
{
    public ?string $resolvedToken = null;

    public function resolve(Request $request): ?string
    {
        return $this->resolvedToken;
    }
}

class FeatureFakePaymentAuth extends Auth
{
    public array $payload = [];

    public function verifyToken(string $token): array
    {
        return $this->payload;
    }
}

class FeatureCapturingPaymentResponse extends Response
{
    public int $statusCode = 200;
    public array $payload = [];
    public ?string $redirectPath = null;

    public function setStatusCode(int $code): void
    {
        $this->statusCode = $code;
    }

    public function json($data, int $statusCode = 200): void
    {
        $this->setStatusCode($statusCode);
        $this->payload = $data;
    }

    public function redirect(string $path, int $statusCode = 302): void
    {
        $this->setStatusCode($statusCode);
        $this->redirectPath = $path;
    }
}
