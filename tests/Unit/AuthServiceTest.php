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
        $this->assertArrayHasKey('phone', $result['errors']);
        $this->assertArrayHasKey('email', $result['errors']);
        $this->assertArrayHasKey('password', $result['errors']);
    }

    public function testRegisterRejectsInvalidEmail(): void
    {
        $service = new AuthService(new FakeUserRepository(), new FakeAuth(), new FakeLogger());

        $result = $service->register([
            'name' => 'Test',
            'phone' => '0987654321',
            'email' => 'invalid-email',
            'password' => '12345678',
        ]);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('email', $result['errors']);
    }

    public function testRegisterRejectsShortPassword(): void
    {
        $service = new AuthService(new FakeUserRepository(), new FakeAuth(), new FakeLogger());

        $result = $service->register([
            'name' => 'Test',
            'phone' => '0987654321',
            'email' => 'test@example.com',
            'password' => '1234567',
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
            'phone' => '0987654321',
            'email' => 'exists@example.com',
            'password' => '12345678',
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
            'phone' => '0901234567',
            'email' => 'TEST@EXAMPLE.COM',
            'password' => '12345678',
            'role' => 'admin',
        ]);

        $this->assertArrayHasKey('data', $result);
        $this->assertSame(123, $result['data']['id']);
        $this->assertSame('fake-token', $result['data']['token']);
        $this->assertSame('test@example.com', $repo->createdData['email']);
        $this->assertSame('0901234567', $repo->createdData['phone']);
        $this->assertSame('user', $repo->createdData['role']);
    }

    public function testRegisterRejectsInvalidPhone(): void
    {
        $service = new AuthService(new FakeUserRepository(), new FakeAuth(), new FakeLogger());

        $result = $service->register([
            'name' => 'Test',
            'phone' => 'abc',
            'email' => 'test@example.com',
            'password' => '12345678',
        ]);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('phone', $result['errors']);
    }

    public function testLoginRejectsMissingFields(): void
    {
        $service = new AuthService(new FakeUserRepository(), new FakeAuth(), new FakeLogger());

        $result = $service->login([]);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('identifier', $result['errors']);
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
            'email' => 'USER@EXAMPLE.COM',
            'password' => 'correct',
        ]);

        $this->assertArrayHasKey('data', $result);
        $this->assertSame('fake-token', $result['data']['token']);
        $this->assertSame(['user_id' => 1, 'role' => 'admin'], $auth->payload);
    }

    public function testLoginAcceptsPhoneIdentifier(): void
    {
        $repo = new FakeUserRepository();
        $repo->user = [
            'id' => 8,
            'email' => 'member@example.com',
            'phone' => '0900000001',
            'password' => password_hash('member123', PASSWORD_BCRYPT),
            'role' => 'user',
        ];
        $auth = new FakeAuth();

        $service = new AuthService($repo, $auth, new FakeLogger());

        $result = $service->login([
            'identifier' => '0900000001',
            'password' => 'member123',
        ]);

        $this->assertArrayHasKey('data', $result);
        $this->assertSame('0900000001', $repo->lastLoginIdentifier);
        $this->assertSame(['user_id' => 8, 'role' => 'user'], $auth->payload);
    }

    public function testAdminLoginRejectsNonAdminUser(): void
    {
        $repo = new FakeUserRepository();
        $repo->user = [
            'id' => 4,
            'email' => 'admin',
            'name' => 'Normal User',
            'password' => password_hash('admin', PASSWORD_BCRYPT),
            'role' => 'user',
        ];

        $service = new AuthService($repo, new FakeAuth(), new FakeLogger());

        $result = $service->loginAdmin([
            'identifier' => 'admin',
            'password' => 'admin',
        ]);

        $this->assertArrayHasKey('errors', $result);
        $this->assertSame(['Invalid admin credentials.'], $result['errors']['credentials']);
    }

    public function testAdminLoginReturnsTokenForAdminAccount(): void
    {
        $repo = new FakeUserRepository();
        $repo->user = [
            'id' => 9,
            'email' => 'admin',
            'name' => 'System Admin',
            'password' => password_hash('admin', PASSWORD_BCRYPT),
            'role' => 'admin',
        ];
        $auth = new FakeAuth();

        $service = new AuthService($repo, $auth, new FakeLogger());

        $result = $service->loginAdmin([
            'identifier' => 'admin',
            'password' => 'admin',
        ]);

        $this->assertArrayHasKey('data', $result);
        $this->assertSame('fake-token', $result['data']['token']);
        $this->assertSame('admin', $result['data']['user']['email']);
        $this->assertSame(['user_id' => 9, 'role' => 'admin'], $auth->payload);
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
        $repo->profileStats = ['tickets' => 2, 'orders' => 1, 'spent' => 99.5];
        $repo->recentOrders = [
            [
                'order_code' => 'T-1',
                'order_type' => 'ticket',
                'items_count' => 2,
                'order_date' => '2026-03-13 10:00:00',
                'total_amount' => 99.5,
                'status' => 'paid',
            ],
        ];
        $auth = new FakeAuth();
        $auth->payload = ['user_id' => 1];

        $service = new AuthService($repo, $auth, new FakeLogger());

        $result = $service->profile('token');

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayNotHasKey('password', $result['data']);
        $this->assertSame($repo->profileStats, $result['data']['stats']);
        $this->assertSame($repo->recentOrders, $result['data']['orders']);
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

    public function testLogoutReturnsErrorForMissingToken(): void
    {
        $service = new AuthService(new FakeUserRepository(), new FakeAuth(), new FakeLogger());

        $result = $service->logout('');

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('token', $result['errors']);
    }

    public function testLogoutReturnsSuccess(): void
    {
        $auth = new FakeAuth();
        $auth->payload = ['user_id' => 5, 'role' => 'user'];
        $service = new AuthService(new FakeUserRepository(), $auth, new FakeLogger());

        $result = $service->logout('token');

        $this->assertArrayHasKey('data', $result);
        $this->assertSame('Logged out', $result['data']['message']);
    }
}

class FakeUserRepository extends UserRepository
{
    public ?string $existingEmail = null;
    public ?array $user = null;
    public ?array $userById = null;
    public ?string $lastLoginIdentifier = null;
    public array $createdData = [];
    public array $profileStats = ['tickets' => 0, 'orders' => 0, 'spent' => 0.0];
    public array $recentOrders = [];

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

    public function findByLoginIdentifier(string $identifier): ?array
    {
        $this->lastLoginIdentifier = $identifier;

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

    public function createWithTransaction(array $data): int
    {
        $this->createdData = $data;

        return 123;
    }

    public function getProfileStats(int $userId): array
    {
        return $this->profileStats;
    }

    public function getRecentOrders(int $userId, int $limit = 10): array
    {
        return $this->recentOrders;
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

    public function error(string $message, array $context = []): void
    {
    }
}
