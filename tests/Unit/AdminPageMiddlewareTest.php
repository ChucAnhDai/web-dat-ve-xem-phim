<?php

namespace Tests\Unit;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Middlewares\AdminPageMiddleware;
use App\Repositories\UserRepository;
use Exception;
use PHPUnit\Framework\TestCase;

class AdminPageMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_SERVER = [];
    }

    public function testHandleRedirectsToLoginWhenTokenIsMissing(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SCRIPT_NAME'] = '/web-dat-ve-xem-phim/public/index.php';

        $middleware = new AdminPageMiddleware(new AdminPageFakeAuth(), new AdminPageFakeUserRepository(), new AdminPageFakeLogger());
        $response = new AdminPageCapturingResponse();

        $allowed = $middleware->handle(new Request(), $response);

        $this->assertFalse($allowed);
        $this->assertSame('/web-dat-ve-xem-phim/admin/login', $response->redirectPath);
    }

    public function testHandleRedirectsToLoginWhenRoleIsNotAdmin(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SCRIPT_NAME'] = '/web-dat-ve-xem-phim/public/index.php';
        $_COOKIE['cinemax_admin_token'] = 'valid-token';

        $middleware = new AdminPageMiddleware(
            new AdminPageFakeAuth(['user_id' => 3, 'role' => 'user']),
            new AdminPageFakeUserRepository(['id' => 3, 'role' => 'user']),
            new AdminPageFakeLogger()
        );
        $response = new AdminPageCapturingResponse();

        $allowed = $middleware->handle(new Request(), $response);

        $this->assertFalse($allowed);
        $this->assertSame('/web-dat-ve-xem-phim/admin/login', $response->redirectPath);
    }

    public function testHandleAllowsValidAdminAndSetsRequestAttributes(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SCRIPT_NAME'] = '/web-dat-ve-xem-phim/public/index.php';
        $_COOKIE['cinemax_admin_token'] = 'valid-token';

        $payload = ['user_id' => 7, 'role' => 'admin'];
        $user = ['id' => 7, 'name' => 'System Admin', 'email' => 'admin', 'password' => 'secret', 'role' => 'admin'];
        $middleware = new AdminPageMiddleware(
            new AdminPageFakeAuth($payload),
            new AdminPageFakeUserRepository($user),
            new AdminPageFakeLogger()
        );
        $request = new Request();
        $response = new AdminPageCapturingResponse();

        $allowed = $middleware->handle($request, $response);

        $this->assertTrue($allowed);
        $this->assertSame($payload, $request->getAttribute('auth'));
        $this->assertSame('System Admin', $request->getAttribute('adminUser')['name']);
        $this->assertArrayNotHasKey('password', $request->getAttribute('adminUser'));
    }
}

class AdminPageFakeAuth extends Auth
{
    private array $payload;
    private bool $shouldThrow;

    public function __construct(array $payload = [], bool $shouldThrow = false)
    {
        $this->payload = $payload;
        $this->shouldThrow = $shouldThrow;
    }

    public function verifyToken(string $token): array
    {
        if ($this->shouldThrow) {
            throw new Exception('Invalid token.');
        }

        return $this->payload;
    }
}

class AdminPageFakeUserRepository extends UserRepository
{
    private ?array $user;

    public function __construct(?array $user = null)
    {
        $this->user = $user;
    }

    public function findById(int $id): ?array
    {
        return $this->user;
    }
}

class AdminPageFakeLogger extends Logger
{
    public function __construct()
    {
    }

    public function info(string $message, array $context = []): void
    {
    }

    public function error(string $message, array $context = []): void
    {
    }
}

class AdminPageCapturingResponse extends Response
{
    public ?string $redirectPath = null;

    public function redirect(string $path, int $statusCode = 302): void
    {
        $this->redirectPath = $path;
    }

    public function clearCookie(string $name, array $options = []): void
    {
    }
}
