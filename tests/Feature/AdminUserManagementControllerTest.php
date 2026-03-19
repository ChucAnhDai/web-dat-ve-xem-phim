<?php

namespace Tests\Feature;

use App\Controllers\Api\Admin\AdminUserController;
use App\Core\Request;
use App\Core\Response;
use App\Services\Admin\AdminUserService;
use PHPUnit\Framework\TestCase;

class AdminUserManagementControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
    }

    public function testListUsersReturnsDataPayload(): void
    {
        $service = $this->createMock(AdminUserService::class);
        $service->method('listUsers')->willReturn([
            'data' => [
                'users' => [['id' => 1, 'name' => 'Test User', 'email' => 'test@example.com', 'role' => 'user', 'status' => 'Active']],
                'pagination' => ['current_page' => 1, 'total_pages' => 1]
            ]
        ]);
        
        $controller = new AdminUserController($service);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $response = new FeatureCapturingResponse();

        $controller->listUsers(new Request(), $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('Test User', $response->payload['data']['users'][0]['name']);
    }

    public function testCreateUserReturns201(): void
    {
        $service = $this->createMock(AdminUserService::class);
        $service->method('createUser')->willReturn([
            'data' => ['id' => 123, 'message' => 'User created successfully.']
        ]);
        
        $controller = new AdminUserController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request = new Request();
        $response = new FeatureCapturingResponse();

        $controller->createUser($request, $response);

        $this->assertSame(201, $response->statusCode);
        $this->assertSame(123, $response->payload['data']['id']);
    }
}

if (!class_exists('Tests\Feature\FeatureCapturingResponse')) {
    class FeatureCapturingResponse extends Response
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
}
