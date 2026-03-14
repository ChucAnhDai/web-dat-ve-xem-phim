<?php

namespace Tests\Unit;

use App\Core\Logger;
use App\Repositories\UserRepository;
use App\Services\DefaultAdminProvisioningService;
use PHPUnit\Framework\TestCase;

class DefaultAdminProvisioningServiceTest extends TestCase
{
    public function testProvisionCreatesAdminWhenAccountDoesNotExist(): void
    {
        $repository = new DefaultAdminFakeUserRepository();
        $service = new DefaultAdminProvisioningService($repository, new DefaultAdminFakeLogger());

        $result = $service->provision([
            'email' => 'admin',
            'password' => 'admin',
            'name' => 'System Admin',
            'phone' => '0000000000',
        ]);

        $this->assertTrue($result['created']);
        $this->assertSame('admin', $repository->createdData['email']);
        $this->assertSame('admin', $repository->createdData['role']);
        $this->assertTrue(password_verify('admin', $repository->createdData['password']));
    }

    public function testProvisionUpdatesExistingAdminAccount(): void
    {
        $repository = new DefaultAdminFakeUserRepository();
        $repository->existingUser = [
            'id' => 7,
            'email' => 'admin',
            'role' => 'user',
            'password' => password_hash('old-password', PASSWORD_BCRYPT),
        ];

        $service = new DefaultAdminProvisioningService($repository, new DefaultAdminFakeLogger());

        $result = $service->provision([
            'email' => 'admin',
            'password' => 'admin',
            'name' => 'System Admin',
            'phone' => '0000000000',
        ]);

        $this->assertFalse($result['created']);
        $this->assertSame(7, $result['id']);
        $this->assertSame('admin', $repository->updatedData['role']);
        $this->assertTrue(password_verify('admin', $repository->updatedData['password']));
    }
}

class DefaultAdminFakeUserRepository extends UserRepository
{
    public ?array $existingUser = null;
    public array $createdData = [];
    public array $updatedData = [];

    public function __construct()
    {
    }

    public function findByEmail(string $email): ?array
    {
        return $this->existingUser;
    }

    public function createWithTransaction(array $data): int
    {
        $this->createdData = $data;

        return 15;
    }

    public function update(int $id, array $data): bool
    {
        $this->updatedData = $data;

        return true;
    }
}

class DefaultAdminFakeLogger extends Logger
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
