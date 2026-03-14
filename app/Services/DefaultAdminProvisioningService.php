<?php

namespace App\Services;

use App\Core\Logger;
use App\Repositories\UserRepository;
use Throwable;

class DefaultAdminProvisioningService
{
    private UserRepository $users;
    private Logger $logger;

    public function __construct(?UserRepository $users = null, ?Logger $logger = null)
    {
        $this->users = $users ?? new UserRepository();
        $this->logger = $logger ?? new Logger();
    }

    public function provision(array $data = []): array
    {
        $email = strtolower(trim((string) ($data['email'] ?? 'admin')));
        $password = (string) ($data['password'] ?? 'admin');
        $name = trim((string) ($data['name'] ?? 'System Admin'));
        $phone = trim((string) ($data['phone'] ?? '0000000000'));

        if ($email === '' || $password === '') {
            throw new \InvalidArgumentException('Default admin email and password are required.');
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $existing = $this->users->findByEmail($email);

        try {
            if ($existing) {
                $this->users->update((int) $existing['id'], [
                    'name' => $name,
                    'password' => $hashedPassword,
                    'phone' => $phone,
                    'role' => 'admin',
                ]);

                $this->logger->info('Default admin account updated', [
                    'user_id' => (int) $existing['id'],
                    'email' => $email,
                ]);

                return [
                    'id' => (int) $existing['id'],
                    'created' => false,
                    'email' => $email,
                ];
            }

            $id = $this->users->createWithTransaction([
                'name' => $name,
                'email' => $email,
                'password' => $hashedPassword,
                'phone' => $phone,
                'role' => 'admin',
            ]);

            $this->logger->info('Default admin account created', [
                'user_id' => $id,
                'email' => $email,
            ]);

            return [
                'id' => $id,
                'created' => true,
                'email' => $email,
            ];
        } catch (Throwable $exception) {
            $this->logger->error('Default admin account provisioning failed', [
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
