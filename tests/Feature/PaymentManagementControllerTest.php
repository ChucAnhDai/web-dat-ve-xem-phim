<?php

namespace Tests\Feature;

use App\Controllers\Admin\PaymentManagementController;
use App\Core\Request;
use App\Core\Response;
use App\Services\AdminPaymentManagementService;
use PHPUnit\Framework\TestCase;

class PaymentManagementControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
    }

    public function testListPaymentsReturnsPayload(): void
    {
        $service = new FeatureFakeAdminPaymentManagementService();
        $service->result = [
            'status' => 200,
            'data' => [
                'items' => [['transaction_code' => 'PAY-001']],
            ],
        ];

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $controller = new PaymentManagementController($service);
        $response = new PaymentManagementCapturingResponse();

        $controller->listPayments(new Request(), $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('PAY-001', $response->payload['data']['items'][0]['transaction_code']);
    }

    public function testCreatePaymentMethodPassesActorAndPayload(): void
    {
        $service = new FeatureFakeAdminPaymentManagementService();
        $service->result = [
            'status' => 201,
            'data' => [
                'id' => 5,
                'code' => 'stripe',
            ],
        ];

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'code' => 'stripe',
            'name' => 'Stripe Gateway',
            'provider' => 'stripe',
        ];

        $request = new Request();
        $request->setAttribute('auth', [
            'user_id' => 12,
            'role' => 'admin',
        ]);

        $response = new PaymentManagementCapturingResponse();
        $controller = new PaymentManagementController($service);

        $controller->createPaymentMethod($request, $response);

        $this->assertSame(201, $response->statusCode);
        $this->assertSame(12, $service->receivedActorId);
        $this->assertSame('stripe', $service->receivedPayload['code']);
        $this->assertSame('Stripe Gateway', $service->receivedPayload['name']);
    }
}

class FeatureFakeAdminPaymentManagementService extends AdminPaymentManagementService
{
    public array $result = [];
    public ?int $receivedId = null;
    public ?int $receivedActorId = null;
    public array $receivedPayload = [];

    public function __construct()
    {
    }

    public function listPayments(array $filters): array
    {
        $this->receivedPayload = $filters;

        return $this->result;
    }

    public function getPayment(int $paymentId): array
    {
        $this->receivedId = $paymentId;

        return $this->result;
    }

    public function listPaymentMethods(array $filters): array
    {
        $this->receivedPayload = $filters;

        return $this->result;
    }

    public function getPaymentMethod(int $methodId): array
    {
        $this->receivedId = $methodId;

        return $this->result;
    }

    public function createPaymentMethod(array $payload, ?int $actorId = null): array
    {
        $this->receivedPayload = $payload;
        $this->receivedActorId = $actorId;

        return $this->result;
    }

    public function updatePaymentMethod(int $methodId, array $payload, ?int $actorId = null): array
    {
        $this->receivedId = $methodId;
        $this->receivedPayload = $payload;
        $this->receivedActorId = $actorId;

        return $this->result;
    }

    public function archivePaymentMethod(int $methodId, ?int $actorId = null): array
    {
        $this->receivedId = $methodId;
        $this->receivedActorId = $actorId;

        return $this->result;
    }
}

class PaymentManagementCapturingResponse extends Response
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
