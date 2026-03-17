<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ProductDetailRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function findByProductId(int $productId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM product_details WHERE product_id = :product_id LIMIT 1');
        $stmt->execute(['product_id' => $productId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function upsertForProduct(int $productId, array $data): void
    {
        $existing = $this->findByProductId($productId);
        $hasPayload = $this->hasPayload($data);

        if ($existing === null && !$hasPayload) {
            return;
        }

        if ($existing === null) {
            $stmt = $this->db->prepare(
                'INSERT INTO product_details (
                    product_id, brand, weight, origin, description, attributes_json, created_at, updated_at
                ) VALUES (
                    :product_id, :brand, :weight, :origin, :description, :attributes_json, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )'
            );
            $stmt->execute([
                'product_id' => $productId,
                'brand' => $data['brand'],
                'weight' => $data['weight'],
                'origin' => $data['origin'],
                'description' => $data['description'],
                'attributes_json' => $data['attributes_json'],
            ]);

            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE product_details
             SET brand = :brand,
                 weight = :weight,
                 origin = :origin,
                 description = :description,
                 attributes_json = :attributes_json,
                 updated_at = CURRENT_TIMESTAMP
             WHERE product_id = :product_id'
        );
        $stmt->execute([
            'product_id' => $productId,
            'brand' => $data['brand'],
            'weight' => $data['weight'],
            'origin' => $data['origin'],
            'description' => $data['description'],
            'attributes_json' => $data['attributes_json'],
        ]);
    }

    private function hasPayload(array $data): bool
    {
        foreach (['brand', 'weight', 'origin', 'description', 'attributes_json'] as $field) {
            if (($data[$field] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }
}
