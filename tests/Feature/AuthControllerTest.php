<?php

namespace Tests\Feature;

use App\Controllers\Auth\AuthController;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use PHPUnit\Framework\TestCase;

class AuthControllerTest extends TestCase
{
    private int $initialOutputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialOutputBufferLevel = ob_get_level();
    }

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

    public function testLoginReturnsValidationErrorsForIdentifier(): void
    {
        $service = new FakeAuthService(['errors' => ['identifier' => ['Field is required.']]]);
        $controller = new TestableAuthController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['password' => 'secret'];

        $response = $controller->login(new Request(), new CapturingResponse());

        $this->assertSame(422, $response->statusCode);
        $this->assertSame(['errors' => ['identifier' => ['Field is required.']]], $response->payload);
    }

    public function testLoginReturnsTokenOnSuccess(): void
    {
        $service = new FakeAuthService(['data' => ['token' => 'token']]);
        $controller = new TestableAuthController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['identifier' => '0900000001', 'password' => 'correct'];

        $response = $controller->login(new Request(), new CapturingResponse());

        $this->assertSame(200, $response->statusCode);
        $this->assertSame([
            'message' => 'Login successful',
            'data' => ['token' => 'token'],
        ], $response->payload);
    }

    public function testAdminLoginReturnsValidationErrors(): void
    {
        $service = new FakeAuthService(['errors' => ['identifier' => ['Field is required.']]]);
        $controller = new TestableAuthController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['password' => 'admin'];

        $response = $controller->adminLogin(new Request(), new CapturingResponse());

        $this->assertSame(422, $response->statusCode);
        $this->assertSame(['errors' => ['identifier' => ['Field is required.']]], $response->payload);
    }

    public function testAdminLoginReturnsTokenOnSuccess(): void
    {
        $service = new FakeAuthService(['data' => ['token' => 'token', 'user' => ['id' => 1, 'role' => 'admin']]]);
        $controller = new TestableAuthController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['identifier' => 'admin', 'password' => 'admin'];

        $response = $controller->adminLogin(new Request(), new CapturingResponse());

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('Admin login successful', $response->payload['message']);
        $this->assertSame('token', $response->payload['data']['token']);
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

    public function testLogoutReturnsUnauthorizedOnFailure(): void
    {
        $service = new FakeAuthService(['errors' => ['token' => ['Invalid token.']]]);
        $controller = new TestableAuthController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalid';

        $response = $controller->logout(new Request(), new CapturingResponse());

        $this->assertSame(401, $response->statusCode);
        $this->assertSame(['errors' => ['token' => ['Invalid token.']]], $response->payload);
        $this->assertArrayHasKey('cinemax_token', $response->clearedCookies);
    }

    public function testLogoutReturnsSuccess(): void
    {
        $service = new FakeAuthService(['data' => ['message' => 'Logged out']]);
        $controller = new TestableAuthController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer valid-token';

        $response = $controller->logout(new Request(), new CapturingResponse());

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(['message' => 'Logout successful'], $response->payload);
        $this->assertArrayHasKey('cinemax_token', $response->clearedCookies);
    }

    public function testAdminLogoutReturnsSuccess(): void
    {
        $service = new FakeAuthService(['data' => ['message' => 'Logged out']]);
        $controller = new TestableAuthController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['SCRIPT_NAME'] = '/web-dat-ve-xem-phim/public/index.php';
        $_COOKIE['cinemax_admin_token'] = 'valid-token';

        $response = new CapturingResponse();
        $controller->adminLogout(new Request(), $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(['message' => 'Admin logout successful'], $response->payload);
    }

    private function clearOutputBuffer(): void
    {
        while (ob_get_level() > $this->initialOutputBufferLevel) {
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
        parent::register($request, $response);

        return $response;
    }

    public function login(Request $request, Response $response)
    {
        parent::login($request, $response);

        return $response;
    }

    public function adminLogin(Request $request, Response $response)
    {
        parent::adminLogin($request, $response);

        return $response;
    }

    public function profile(Request $request, Response $response)
    {
        parent::profile($request, $response);

        return $response;
    }

    public function logout(Request $request, Response $response)
    {
        parent::logout($request, $response);

        return $response;
    }

    public function adminLogout(Request $request, Response $response)
    {
        parent::adminLogout($request, $response);

        return $response;
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

    public function loginAdmin(array $data): array
    {
        return $this->result;
    }

    public function logout(string $token): array
    {
        return $this->result;
    }
}

class CapturingResponse extends Response
{
    public int $statusCode = 200;
    public array $payload = [];
    public array $clearedCookies = [];

    public function setStatusCode(int $code): void
    {
        $this->statusCode = $code;
    }

    public function json($data, int $statusCode = 200): void
    {
        $this->setStatusCode($statusCode);
        $this->payload = $data;
    }

    public function clearCookie(string $name, array $options = []): void
    {
        $this->clearedCookies[$name] = $options;
    }
}
