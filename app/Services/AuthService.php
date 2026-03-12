<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\Validator;
use App\Repositories\UserRepository;
use Exception;

class AuthService
{
    private UserRepository $users;
    private Auth $auth;
    private Logger $logger;

    public function __construct(?UserRepository $users = null, ?Auth $auth = null, ?Logger $logger = null)
    {
        $this->users = $users ?? new UserRepository();
        $this->auth = $auth ?? new Auth();
        $this->logger = $logger ?? new Logger();
    }

    public function register(array $data): array
    {
        $errors = Validator::required($data, ['name', 'email', 'password']);
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $name = trim((string) ($data['name'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        $emailError = Validator::email($email);
        if ($emailError) {
            $errors['email'][] = $emailError;
        }
        $passwordError = Validator::minLength($password, 8);
        if ($passwordError) {
            $errors['password'][] = $passwordError;
        }

        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        $existing = $this->users->findByEmail($email);
        if ($existing) {
            return ['errors' => ['email' => ['Email already exists.']]];
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $role = $this->sanitizeRole($data['role'] ?? null);

        try {
            $id = $this->users->createWithTransaction([
                'name' => $name,
                'email' => $email,
                'password' => $hashed,
                'phone' => $data['phone'] ?? null,
                'role' => $role,
            ]);
        } catch (Exception $exception) {
            $this->logger->error('User registration failed', ['error' => $exception->getMessage()]);
            return ['errors' => ['server' => ['Registration failed. Please try again.']]];
        }

        $token = $this->auth->generateToken(['user_id' => $id, 'role' => $role]);
        $this->logger->info('User registered', ['user_id' => $id]);

        return ['data' => ['id' => $id, 'token' => $token]];
    }

    public function login(array $data): array
    {
        $errors = Validator::required($data, ['email', 'password']);
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        $user = $this->users->findByEmail($email);
        if (!$user || !password_verify($password, $user['password'])) {
            $this->logger->error('User login failed', ['email' => $email]);
            return ['errors' => ['credentials' => ['Invalid credentials.']]];
        }

        $token = $this->auth->generateToken(['user_id' => $user['id'], 'role' => $user['role']]);
        $this->logger->info('User login', ['user_id' => $user['id']]);

        return ['data' => ['token' => $token]];
    }

    public function logout(string $token): array
    {
        if (trim($token) === '') {
            return ['errors' => ['token' => ['Missing bearer token.']]];
        }

        try {
            $payload = $this->auth->verifyToken($token);
            $this->logger->info('User logout', ['user_id' => $payload['user_id'] ?? null]);
            return ['data' => ['message' => 'Logged out']];
        } catch (Exception $exception) {
            return ['errors' => ['token' => [$exception->getMessage()]]];
        }
    }

    private function sanitizeRole(?string $role): string
    {
        $role = strtolower(trim((string) $role));
        $allowed = ['user', 'admin'];
        if ($role === '' || !in_array($role, $allowed, true)) {
            return 'user';
        }

        return $role;
    }

    public function profile(string $token): array
    {
        try {
            $payload = $this->auth->verifyToken($token);
            $user = $this->users->findById((int) $payload['user_id']);
            if (!$user) {
                return ['errors' => ['user' => ['User not found.']]];
            }

            unset($user['password']);
            return ['data' => $user];
        } catch (Exception $e) {
            return ['errors' => ['token' => [$e->getMessage()]]];
        }
    }
}
