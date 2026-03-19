<?php

namespace App\Services\Admin;

use App\Repositories\UserRepository;
use App\Core\Logger;
use App\Core\Validator;
use Exception;

class AdminUserService
{
    private UserRepository $userRepository;
    private Logger $logger;

    public function __construct(?UserRepository $userRepository = null, ?Logger $logger = null)
    {
        $this->userRepository = $userRepository ?? new UserRepository();
        $this->logger = $logger ?? new Logger();
    }

    public function listUsers(int $page = 1, int $limit = 10, string $search = '', string $role = '', string $status = ''): array
    {
        try {
            $users = $this->userRepository->findAllPaginated($page, $limit, $search, $role, $status);
            $total = $this->userRepository->countAll($search, $role, $status);

            return [
                'data' => [
                    'users' => $users,
                    'pagination' => [
                        'current_page' => $page,
                        'limit' => $limit,
                        'total_items' => $total,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]
            ];
        } catch (Exception $e) {
            $this->logger->error('Failed to list users', ['error' => $e->getMessage()]);
            return ['errors' => ['server' => ['Failed to fetch users.']]];
        }
    }

    public function getUser(int $id): array
    {
        try {
            $user = $this->userRepository->findById($id);
            if (!$user) {
                return ['errors' => ['user' => ['User not found.']]];
            }
            unset($user['password']);
            return ['data' => $user];
        } catch (Exception $e) {
            $this->logger->error('Failed to get user', ['id' => $id, 'error' => $e->getMessage()]);
            return ['errors' => ['server' => ['Failed to fetch user details.']]];
        }
    }

    public function createUser(array $data): array
    {
        $errors = Validator::required($data, ['name', 'email', 'password', 'role']);
        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        $email = strtolower(trim($data['email']));
        if (Validator::email($email)) {
            $errors['email'][] = 'Invalid email format.';
        }

        if ($this->userRepository->findByEmail($email)) {
            $errors['email'][] = 'Email already exists.';
        }

        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        $data['status'] = $data['status'] ?? 'Active';

        try {
            $id = $this->userRepository->create($data);
            $this->logger->info('User created by admin', ['user_id' => $id]);
            return ['data' => ['id' => $id, 'message' => 'User created successfully.']];
        } catch (Exception $e) {
            $this->logger->error('Failed to create user', ['error' => $e->getMessage()]);
            return ['errors' => ['server' => ['Failed to create user.']]];
        }
    }

    public function updateUser(int $id, array $data): array
    {
        $user = $this->userRepository->findById($id);
        if (!$user) {
            return ['errors' => ['user' => ['User not found.']]];
        }

        if (isset($data['email'])) {
            $email = strtolower(trim($data['email']));
            $existing = $this->userRepository->findByEmail($email);
            if ($existing && (int)$existing['id'] !== $id) {
                return ['errors' => ['email' => ['Email already exists.']]];
            }
        }

        if (isset($data['password']) && trim($data['password']) !== '') {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        } else {
            unset($data['password']);
        }

        try {
            $this->userRepository->update($id, $data);
            $this->logger->info('User updated by admin', ['user_id' => $id]);
            return ['data' => ['message' => 'User updated successfully.']];
        } catch (Exception $e) {
            $this->logger->error('Failed to update user', ['id' => $id, 'error' => $e->getMessage()]);
            return ['errors' => ['server' => ['Failed to update user.']]];
        }
    }

    public function deleteUser(int $id): array
    {
        try {
            $this->userRepository->delete($id);
            $this->logger->info('User deleted by admin', ['user_id' => $id]);
            return ['data' => ['message' => 'User deleted successfully.']];
        } catch (Exception $e) {
            $this->logger->error('Failed to delete user', ['id' => $id, 'error' => $e->getMessage()]);
            return ['errors' => ['server' => ['Failed to delete user.']]];
        }
    }

    public function getStats(): array
    {
        try {
            return ['data' => $this->userRepository->getStats()];
        } catch (Exception $e) {
            $this->logger->error('Failed to get user stats', ['error' => $e->getMessage()]);
            return ['errors' => ['server' => ['Failed to fetch stats.']]];
        }
    }
}
