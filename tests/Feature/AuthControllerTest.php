<?php

namespace Tests\Feature;

use App\Controllers\Auth\AuthController;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use PHPUnit\Framework\TestCase;

class AuthControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
        $this->clearOutputBuffer();
    }

    public function testRegisterReturnsValidationErrors(): void
    {
        $service = new FakeAuthService(['errors' => ['email' => ['Invalid email format.']]]);
        $controller = new TestableAuthController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [];

        $response = $controller->register(new Request(), new CapturingResponse());

        $this->assertSame(422, $response->statusCode);
        $this->assertSame(['errors' => ['email' => ['Invalid email format.']]], $response->payload);
    }

    public function testRegisterReturnsSuccess(): void
    {
        $service = new FakeAuthService(['data' => ['id' => 1, 'token' => 'token']]);
        $controller = new TestableAuthController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['name' => 'Test', 'email' => 'test@example.com', 'password' => '123456'];

        $response = $controller->register(new Request(), new CapturingResponse());

        $this->assertSame(201, $response->statusCode);
        $this->assertSame([
            'message' => 'Registered successfully',
            'data' => ['id' => 1, 'token' => 'token'],
        ], $response->payload);
    }

    public function testLoginReturnsUnauthorizedOnFailure(): void
    {
        $service = new FakeAuthService(['errors' => ['credentials' => ['Invalid credentials.']]]);
        $controller = new TestableAuthController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['email' => 'test@example.com', 'password' => 'wrong'];

        $response = $controller->login(new Request(), new CapturingResponse());

        $this->assertSame(401, $response->statusCode);
        $this->assertSame(['errors' => ['credentials' => ['Invalid credentials.']]], $response->payload);
    }

    public function testLoginReturnsTokenOnSuccess(): void
    {
        $service = new FakeAuthService(['data' => ['token' => 'token']]);
        $controller = new TestableAuthController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['email' => 'test@example.com', 'password' => 'correct'];

        $response = $controller->login(new Request(), new CapturingResponse());

        $this->assertSame(200, $response->statusCode);
        $this->assertSame([
            'message' => 'Login successful',
            'data' => ['token' => 'token'],
        ], $response->payload);
    }

    public function testProfileRejectsMissingToken(): void
    {
        $service = new FakeAuthService(['data' => ['id' => 1]]);
        $controller = new TestableAuthController($service);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_AUTHORIZATION'] = '';

        $response = $controller->profile(new Request(), new CapturingResponse());

        $this->assertSame(401, $response->statusCode);
        $this->assertSame(['errors' => ['token' => ['Missing bearer token.']]], $response->payload);
    }

    public function testProfileReturnsUnauthorizedOnServiceError(): void
    {
        $service = new FakeAuthService(['errors' => ['token' => ['Invalid token.']]]);
        $controller = new TestableAuthController($service);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalid';

        $response = $controller->profile(new Request(), new CapturingResponse());

        $this->assertSame(401, $response->statusCode);
        $this->assertSame(['errors' => ['token' => ['Invalid token.']]], $response->payload);
    }

    public function testProfileReturnsUserDataOnSuccess(): void
    {
        $service = new FakeAuthService(['data' => ['id' => 1, 'email' => 'test@example.com']]);
        $controller = new TestableAuthController($service);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer valid-token';

        $response = $controller->profile(new Request(), new CapturingResponse());

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(['data' => ['id' => 1, 'email' => 'test@example.com']], $response->payload);
    }

    private function clearOutputBuffer(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
}

class TestableAuthController extends AuthController
{
    public function __construct(AuthService $service)
    {
        parent::__construct($service);
    }

    public function register(Request $request, Response $response)
    {
        return parent::register($request, $response);
    }

    public function login(Request $request, Response $response)
    {
        return parent::login($request, $response);
    }

    public function profile(Request $request, Response $response)
    {
        return parent::profile($request, $response);
    }
}

class FakeAuthService extends AuthService
{
    private array $result;

    public function __construct(array $result)
    {
        $this->result = $result;
    }

    public function register(array $data): array
    {
        return $this->result;
    }

    public function login(array $data): array
    {
        return $this->result;
    }

    public function profile(string $token): array
    {
        return $this->result;
    }
}

class CapturingResponse extends Response
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
