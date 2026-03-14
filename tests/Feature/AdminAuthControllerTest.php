<?php

namespace Tests\Feature;

use App\Controllers\Admin\AdminAuthController;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use PHPUnit\Framework\TestCase;

class AdminAuthControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_SERVER = [];
    }

    public function testShowLoginRedirectsAuthenticatedAdmin(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SCRIPT_NAME'] = '/web-dat-ve-xem-phim/public/index.php';
        $_COOKIE['cinemax_admin_token'] = 'valid-token';

        $controller = new AdminAuthController(new AdminAuthFakeService([]), new AdminAuthFakeAuth([
            'user_id' => 5,
            'role' => 'admin',
        ]));
        $response = new AdminAuthCapturingResponse();

        $controller->showLogin(new Request(), $response);

        $this->assertSame('/web-dat-ve-xem-phim/admin', $response->redirectPath);
    }

    public function testLoginRendersErrorsWhenCredentialsAreInvalid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['SCRIPT_NAME'] = '/web-dat-ve-xem-phim/public/index.php';
        $_POST = [
            'identifier' => 'admin',
            'password' => 'wrong',
        ];

        $controller = new AdminAuthController(new AdminAuthFakeService([
            'errors' => ['credentials' => ['Invalid admin credentials.']],
        ]), new AdminAuthFakeAuth([]));
        $response = new AdminAuthCapturingResponse();

        $controller->login(new Request(), $response);

        $this->assertSame(401, $response->statusCode);
        $this->assertSame('admin/auth/login', $response->viewPath);
        $this->assertSame(['Invalid admin credentials.'], $response->viewParams['errors']['credentials']);
    }

    public function testLoginSetsCookieAndRedirectsToDashboard(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['SCRIPT_NAME'] = '/web-dat-ve-xem-phim/public/index.php';
        $_POST = [
            'identifier' => 'admin',
            'password' => 'admin',
            'remember' => '1',
        ];

        $controller = new AdminAuthController(new AdminAuthFakeService([
            'data' => [
                'token' => 'valid-token',
                'user' => ['id' => 1, 'email' => 'admin', 'role' => 'admin'],
            ],
        ]), new AdminAuthFakeAuth([]));
        $response = new AdminAuthCapturingResponse();

        $controller->login(new Request(), $response);

        $this->assertSame('/web-dat-ve-xem-phim/admin', $response->redirectPath);
        $this->assertSame('valid-token', $response->cookies['cinemax_admin_token']['value']);
        $this->assertTrue($response->cookies['cinemax_admin_token']['options']['httponly']);
    }

    public function testLogoutClearsCookieAndRedirectsToLogin(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SCRIPT_NAME'] = '/web-dat-ve-xem-phim/public/index.php';
        $_COOKIE['cinemax_admin_token'] = 'valid-token';

        $controller = new AdminAuthController(new AdminAuthFakeService([
            'data' => ['message' => 'Logged out'],
        ]), new AdminAuthFakeAuth([]));
        $response = new AdminAuthCapturingResponse();

        $controller->logout(new Request(), $response);

        $this->assertSame('/web-dat-ve-xem-phim/admin/login', $response->redirectPath);
        $this->assertArrayHasKey('cinemax_admin_token', $response->clearedCookies);
    }
}

class AdminAuthFakeService extends AuthService
{
    private array $result;

    public function __construct(array $result)
    {
        $this->result = $result;
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

class AdminAuthFakeAuth extends Auth
{
    private array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function verifyToken(string $token): array
    {
        return $this->payload;
    }
}

class AdminAuthCapturingResponse extends Response
{
    public int $statusCode = 200;
    public ?string $redirectPath = null;
    public ?string $viewPath = null;
    public array $viewParams = [];
    public array $cookies = [];
    public array $clearedCookies = [];

    public function setStatusCode(int $code): void
    {
        $this->statusCode = $code;
    }

    public function redirect(string $path, int $statusCode = 302): void
    {
        $this->setStatusCode($statusCode);
        $this->redirectPath = $path;
    }

    public function view(string $viewPath, array $params = []): void
    {
        $this->viewPath = $viewPath;
        $this->viewParams = $params;
    }

    public function setCookie(string $name, string $value, array $options = []): void
    {
        $this->cookies[$name] = [
            'value' => $value,
            'options' => $options,
        ];
    }

    public function clearCookie(string $name, array $options = []): void
    {
        $this->clearedCookies[$name] = $options;
    }
}
