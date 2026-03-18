<?php

namespace Tests\Unit;

use App\Core\Logger;
use App\Repositories\UserRepository;
use App\Services\DefaultMemberProvisioningService;
use PHPUnit\Framework\TestCase;

class DefaultMemberProvisioningServiceTest extends TestCase
{
    public function testProvisionCreatesMemberWhenAccountDoesNotExist(): void
    {
        $repository = new DefaultMemberFakeUserRepository();
        $service = new DefaultMemberProvisioningService($repository, new DefaultMemberFakeLogger());

        $result = $service->provision([
            'email' => 'member@example.com',
            'password' => 'member123',
            'name' => 'Local Member',
            'phone' => '0900000001',
        ]);

        $this->assertTrue($result['created']);
        $this->assertSame('member@example.com', $repository->createdData['email']);
        $this->assertSame('user', $repository->createdData['role']);
        $this->assertTrue(password_verify('member123', $repository->createdData['password']));
    }

    public function testProvisionUpdatesExistingMemberAccount(): void
    {
        $repository = new DefaultMemberFakeUserRepository();
        $repository->existingUser = [
            'id' => 11,
            'email' => 'member@example.com',
            'role' => 'admin',
            'password' => password_hash('old-password', PASSWORD_BCRYPT),
        ];

        $service = new DefaultMemberProvisioningService($repository, new DefaultMemberFakeLogger());

        $result = $service->provision([
            'email' => 'member@example.com',
            'password' => 'member123',
            'name' => 'Local Member',
            'phone' => '0900000001',
        ]);

        $this->assertFalse($result['created']);
        $this->assertSame(11, $result['id']);
        $this->assertSame('user', $repository->updatedData['role']);
        $this->assertTrue(password_verify('member123', $repository->updatedData['password']));
    }
}

class DefaultMemberFakeUserRepository extends UserRepository
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

        return 21;
    }

    public function update(int $id, array $data): bool
    {
        $this->updatedData = $data;

        return true;
    }
}

class DefaultMemberFakeLogger extends Logger
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
