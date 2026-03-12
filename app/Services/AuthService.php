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

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->auth = new Auth();
        $this->logger = new Logger();
    }

    public function register(array $data): array
    {
        $errors = Validator::required($data, ['name', 'email', 'password']);
        $emailError = Validator::email($data['email'] ?? null);
        if ($emailError) {
            $errors['email'][] = $emailError;
        }
        $passwordError = Validator::minLength($data['password'] ?? null, 6);
        if ($passwordError) {
            $errors['password'][] = $passwordError;
        }
        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        $existing = $this->users->findByEmail($data['email']);
        if ($existing) {
            return ['errors' => ['email' => ['Email already exists.']]];
        }

        $hashed = password_hash($data['password'], PASSWORD_BCRYPT);
        $role = $data['role'] ?? 'user';
        $id = $this->users->create([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'password' => $hashed,
            'phone' => $data['phone'] ?? null,
            'role' => $role,
        ]);

        $token = $this->auth->generateToken(['user_id' => $id, 'role' => $role]);
        $this->logger->info('User registered', ['user_id' => $id]);

        return ['data' => ['id' => $id, 'token' => $token]];
    }

    public function login(array $data): array
    {
        $errors = Validator::required($data, ['email', 'password']);
        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        $user = $this->users->findByEmail($data['email']);
        if (!$user || !password_verify($data['password'], $user['password'])) {
            return ['errors' => ['credentials' => ['Invalid credentials.']]];
        }

        $token = $this->auth->generateToken(['user_id' => $user['id'], 'role' => $user['role']]);
        $this->logger->info('User login', ['user_id' => $user['id']]);

        return ['data' => ['token' => $token]];
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
