<?php

namespace Tests\Unit;

use App\Core\Auth;
use App\Core\Logger;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use Exception;
use PHPUnit\Framework\TestCase;

class AuthServiceTest extends TestCase
{
    public function testRegisterReturnsErrorsForMissingFields(): void
    {
        $service = new AuthService(new FakeUserRepository(), new FakeAuth(), new FakeLogger());

        $result = $service->register([]);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('name', $result['errors']);
        $this->assertArrayHasKey('email', $result['errors']);
        $this->assertArrayHasKey('password', $result['errors']);
    }

    public function testRegisterRejectsInvalidEmail(): void
    {
        $service = new AuthService(new FakeUserRepository(), new FakeAuth(), new FakeLogger());

        $result = $service->register([
            'name' => 'Test',
            'email' => 'invalid-email',
            'password' => '123456',
        ]);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('email', $result['errors']);
    }

    public function testRegisterRejectsShortPassword(): void
    {
        $service = new AuthService(new FakeUserRepository(), new FakeAuth(), new FakeLogger());

        $result = $service->register([
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => '123',
        ]);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('password', $result['errors']);
    }

    public function testRegisterRejectsExistingEmail(): void
    {
        $repo = new FakeUserRepository();
        $repo->existingEmail = 'exists@example.com';
        $service = new AuthService($repo, new FakeAuth(), new FakeLogger());

        $result = $service->register([
            'name' => 'Test',
            'email' => 'exists@example.com',
            'password' => '123456',
        ]);

        $this->assertArrayHasKey('errors', $result);
        $this->assertSame(['Email already exists.'], $result['errors']['email']);
    }

    public function testRegisterReturnsTokenOnSuccess(): void
    {
        $repo = new FakeUserRepository();
        $auth = new FakeAuth();
        $service = new AuthService($repo, $auth, new FakeLogger());

        $result = $service->register([
            'name' => '  Test User ',
            'email' => 'TEST@EXAMPLE.COM',
            'password' => '123456',
        ]);

        $this->assertArrayHasKey('data', $result);
        $this->assertSame(123, $result['data']['id']);
        $this->assertSame('fake-token', $result['data']['token']);
        $this->assertSame('test@example.com', $repo->createdData['email']);
    }

    public function testLoginRejectsMissingFields(): void
    {
        $service = new AuthService(new FakeUserRepository(), new FakeAuth(), new FakeLogger());

        $result = $service->login([]);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('email', $result['errors']);
        $this->assertArrayHasKey('password', $result['errors']);
    }

    public function testLoginRejectsInvalidCredentials(): void
    {
        $repo = new FakeUserRepository();
        $repo->user = [
            'id' => 1,
            'email' => 'user@example.com',
            'password' => password_hash('correct', PASSWORD_BCRYPT),
            'role' => 'user',
        ];

        $service = new AuthService($repo, new FakeAuth(), new FakeLogger());

        $result = $service->login([
            'email' => 'user@example.com',
            'password' => 'wrong',
        ]);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('credentials', $result['errors']);
    }

    public function testLoginReturnsTokenOnSuccess(): void
    {
        $repo = new FakeUserRepository();
        $repo->user = [
            'id' => 1,
            'email' => 'user@example.com',
            'password' => password_hash('correct', PASSWORD_BCRYPT),
            'role' => 'admin',
        ];
        $auth = new FakeAuth();

        $service = new AuthService($repo, $auth, new FakeLogger());

        $result = $service->login([
            'email' => 'user@example.com',
            'password' => 'correct',
        ]);

        $this->assertArrayHasKey('data', $result);
        $this->assertSame('fake-token', $result['data']['token']);
        $this->assertSame(['user_id' => 1, 'role' => 'admin'], $auth->payload);
    }

    public function testProfileReturnsUserDataWithoutPassword(): void
    {
        $repo = new FakeUserRepository();
        $repo->userById = [
            'id' => 1,
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'secret',
        ];
        $auth = new FakeAuth();
        $auth->payload = ['user_id' => 1];

        $service = new AuthService($repo, $auth, new FakeLogger());

        $result = $service->profile('token');

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayNotHasKey('password', $result['data']);
    }

    public function testProfileReturnsErrorWhenUserNotFound(): void
    {
        $repo = new FakeUserRepository();
        $repo->userById = null;
        $auth = new FakeAuth();
        $auth->payload = ['user_id' => 999];

        $service = new AuthService($repo, $auth, new FakeLogger());

        $result = $service->profile('token');

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('user', $result['errors']);
    }

    public function testProfileReturnsErrorForInvalidToken(): void
    {
        $auth = new FakeAuth();
        $auth->shouldThrow = true;

        $service = new AuthService(new FakeUserRepository(), $auth, new FakeLogger());

        $result = $service->profile('token');

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('token', $result['errors']);
    }
}

class FakeUserRepository extends UserRepository
{
    public ?string $existingEmail = null;
    public ?array $user = null;
    public ?array $userById = null;
    public array $createdData = [];

    public function __construct()
    {
    }

    public function findByEmail(string $email): ?array
    {
        if ($this->existingEmail && strtolower($email) === strtolower($this->existingEmail)) {
            return ['id' => 1, 'email' => $email];
        }

        return $this->user;
    }

    public function findById(int $id): ?array
    {
        return $this->userById;
    }

    public function create(array $data): int
    {
        $this->createdData = $data;

        return 123;
    }
}

class FakeAuth extends Auth
{
    public array $payload = [];
    public bool $shouldThrow = false;

    public function __construct()
    {
    }

    public function generateToken(array $payload): string
    {
        $this->payload = $payload;

        return 'fake-token';
    }

    public function verifyToken(string $token): array
    {
        if ($this->shouldThrow) {
            throw new Exception('Invalid token.');
        }

        return $this->payload;
    }
}

class FakeLogger extends Logger
{
    public function __construct()
    {
    }

    public function info(string $message, array $context = []): void
    {
    }
}
