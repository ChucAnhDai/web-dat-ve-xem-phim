<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class CartItemRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function listByCartId(int $cartId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, cart_id, product_id, quantity, price, created_at, updated_at
             FROM cart_items
             WHERE cart_id = :cart_id
             ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute(['cart_id' => $cartId]);

        return $stmt->fetchAll() ?: [];
    }

    public function findByCartAndProduct(int $cartId, int $productId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, cart_id, product_id, quantity, price, created_at, updated_at
             FROM cart_items
             WHERE cart_id = :cart_id
               AND product_id = :product_id
             LIMIT 1'
        );
        $stmt->execute([
            'cart_id' => $cartId,
            'product_id' => $productId,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO cart_items (cart_id, product_id, quantity, price, created_at, updated_at)
             VALUES (:cart_id, :product_id, :quantity, :price, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            'cart_id' => $data['cart_id'],
            'product_id' => $data['product_id'],
            'quantity' => $data['quantity'],
            'price' => $data['price'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateQuantityAndPrice(int $id, int $quantity, float $price): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE cart_items
             SET quantity = :quantity,
                 price = :price,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'quantity' => $quantity,
            'price' => $price,
        ]);
    }

    public function deleteByCartAndProduct(int $cartId, int $productId): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM cart_items
             WHERE cart_id = :cart_id
               AND product_id = :product_id'
        );

        return $stmt->execute([
            'cart_id' => $cartId,
            'product_id' => $productId,
        ]);
    }

    public function deleteByCartId(int $cartId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id');

        return $stmt->execute(['cart_id' => $cartId]);
    }
}
