<?php

namespace Tests\Feature;

use App\Controllers\Auth\CustomerAuthPageController;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use PHPUnit\Framework\TestCase;

class CustomerAuthPageControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
    }

    public function testShowLoginRendersViewWithSanitizedRedirect(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SCRIPT_NAME'] = '/web-dat-ve-xem-phim/public/index.php';
        $_GET['redirect'] = '/shop/checkout';

        $controller = new CustomerAuthPageController(new CustomerAuthPageFakeService([]));
        $response = new CustomerAuthPageCapturingResponse();

        $controller->showLogin(new Request(), $response);

        $this->assertSame('auth/login', $response->viewPath);
        $this->assertSame('/shop/checkout', $response->viewParams['redirect']);
    }

    public function testLoginRendersErrorsWhenCredentialsAreInvalid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['SCRIPT_NAME'] = '/web-dat-ve-xem-phim/public/index.php';
        $_POST = [
            'identifier' => 'user@example.com',
            'password' => 'wrong',
            'redirect' => '/cart',
        ];

        $controller = new CustomerAuthPageController(new CustomerAuthPageFakeService([
            'errors' => ['credentials' => ['Invalid credentials.']],
        ]));
        $response = new CustomerAuthPageCapturingResponse();

        $controller->login(new Request(), $response);

        $this->assertSame(401, $response->statusCode);
        $this->assertSame('auth/login', $response->viewPath);
        $this->assertSame(['Invalid credentials.'], $response->viewParams['errors']['credentials']);
        $this->assertSame('/cart', $response->viewParams['redirect']);
        $this->assertSame('user@example.com', $response->viewParams['old']['identifier']);
    }

    public function testLoginRendersBridgeViewWhenAuthenticationSucceeds(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['SCRIPT_NAME'] = '/web-dat-ve-xem-phim/public/index.php';
        $_POST = [
            'identifier' => 'user@example.com',
            'password' => 'secret',
            'remember' => '1',
            'redirect' => '/shop/checkout',
        ];

        $controller = new CustomerAuthPageController(new CustomerAuthPageFakeService([
            'data' => [
                'token' => 'valid-token',
                'user' => ['id' => 2, 'email' => 'user@example.com', 'role' => 'user'],
            ],
        ]));
        $response = new CustomerAuthPageCapturingResponse();

        $controller->login(new Request(), $response);

        $this->assertSame('auth/login-complete', $response->viewPath);
        $this->assertSame('valid-token', $response->viewParams['authToken']);
        $this->assertSame('/shop/checkout', $response->viewParams['redirectPath']);
        $this->assertTrue($response->viewParams['persistAuth']);
    }

    public function testLoginRejectsUnsafeRedirectPath(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['SCRIPT_NAME'] = '/web-dat-ve-xem-phim/public/index.php';
        $_POST = [
            'identifier' => 'user@example.com',
            'password' => 'secret',
            'redirect' => '//evil.example/phish',
        ];

        $controller = new CustomerAuthPageController(new CustomerAuthPageFakeService([
            'data' => [
                'token' => 'valid-token',
                'user' => ['id' => 2, 'email' => 'user@example.com', 'role' => 'user'],
            ],
        ]));
        $response = new CustomerAuthPageCapturingResponse();

        $controller->login(new Request(), $response);

        $this->assertSame('auth/login-complete', $response->viewPath);
        $this->assertSame('/', $response->viewParams['redirectPath']);
    }
}

class CustomerAuthPageFakeService extends AuthService
{
    private array $result;

    public function __construct(array $result)
    {
        $this->result = $result;
    }

    public function login(array $data): array
    {
        return $this->result;
    }
}

class CustomerAuthPageCapturingResponse extends Response
{
    public int $statusCode = 200;
    public ?string $viewPath = null;
    public array $viewParams = [];

    public function setStatusCode(int $code): void
    {
        $this->statusCode = $code;
    }

    public function view(string $viewPath, array $params = []): void
    {
        $this->viewPath = $viewPath;
        $this->viewParams = $params;
    }
}
