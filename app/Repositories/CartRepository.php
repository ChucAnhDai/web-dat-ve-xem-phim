<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class CartRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function findActiveGuestBySessionToken(string $sessionToken): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT *
             FROM carts
             WHERE session_token = :session_token
               AND user_id IS NULL
               AND status = 'active'
             ORDER BY updated_at DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute(['session_token' => $sessionToken]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM carts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findActiveUserCartByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT *
             FROM carts
             WHERE user_id = :user_id
               AND status = 'active'
             ORDER BY updated_at DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO carts (user_id, session_token, currency, status, expires_at, created_at, updated_at)
             VALUES (:user_id, :session_token, :currency, :status, :expires_at, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            'user_id' => $data['user_id'] ?? null,
            'session_token' => $data['session_token'] ?? null,
            'currency' => $data['currency'] ?? 'VND',
            'status' => $data['status'] ?? 'active',
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function assignGuestCartToUser(int $cartId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE carts
             SET user_id = :user_id,
                 session_token = NULL,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $cartId,
            'user_id' => $userId,
        ]);
    }

    public function updateExpiry(int $cartId, ?string $expiresAt): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE carts
             SET expires_at = :expires_at,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $cartId,
            'expires_at' => $expiresAt,
        ]);
    }

    public function updateStatus(int $cartId, string $status): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE carts
             SET status = :status,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $cartId,
            'status' => $status,
        ]);
    }
}
