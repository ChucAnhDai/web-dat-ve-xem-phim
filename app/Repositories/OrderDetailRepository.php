<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class OrderDetailRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function createMany(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $stmt = $this->db->prepare('
            INSERT INTO order_details (
                order_id,
                product_id,
                product_name_snapshot,
                product_sku_snapshot,
                quantity,
                price,
                discount_amount,
                line_total
            )
            VALUES (
                :order_id,
                :product_id,
                :product_name_snapshot,
                :product_sku_snapshot,
                :quantity,
                :price,
                :discount_amount,
                :line_total
            )
        ');

        foreach ($rows as $row) {
            $stmt->execute([
                'order_id' => $row['order_id'],
                'product_id' => $row['product_id'] ?? null,
                'product_name_snapshot' => $row['product_name_snapshot'],
                'product_sku_snapshot' => $row['product_sku_snapshot'] ?? null,
                'quantity' => $row['quantity'],
                'price' => $row['price'],
                'discount_amount' => $row['discount_amount'] ?? 0,
                'line_total' => $row['line_total'],
            ]);
        }
    }

    public function listByOrderIds(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $params = [];
        $placeholders = $this->orderIdPlaceholders($orderIds, $params);
        $stmt = $this->db->prepare("
            SELECT
                od.id,
                od.order_id,
                od.product_id,
                od.product_name_snapshot,
                od.product_sku_snapshot,
                od.quantity,
                od.price,
                od.discount_amount,
                od.line_total,
                od.created_at,
                od.updated_at,
                p.slug AS product_slug,
                (
                    SELECT pi.image_url
                    FROM product_images pi
                    WHERE pi.product_id = od.product_id
                      AND pi.status = 'active'
                    ORDER BY
                        CASE pi.asset_type
                            WHEN 'thumbnail' THEN 0
                            WHEN 'banner' THEN 1
                            WHEN 'gallery' THEN 2
                            WHEN 'lifestyle' THEN 3
                            ELSE 4
                        END,
                        pi.is_primary DESC,
                        pi.sort_order ASC,
                        pi.id ASC
                    LIMIT 1
                ) AS primary_image_url,
                COALESCE((
                    SELECT COALESCE(pi.alt_text, od.product_name_snapshot)
                    FROM product_images pi
                    WHERE pi.product_id = od.product_id
                      AND pi.status = 'active'
                    ORDER BY
                        CASE pi.asset_type
                            WHEN 'thumbnail' THEN 0
                            WHEN 'banner' THEN 1
                            WHEN 'gallery' THEN 2
                            WHEN 'lifestyle' THEN 3
                            ELSE 4
                        END,
                        pi.is_primary DESC,
                        pi.sort_order ASC,
                        pi.id ASC
                    LIMIT 1
                ), od.product_name_snapshot) AS primary_image_alt
            FROM order_details od
            LEFT JOIN products p ON p.id = od.product_id
            WHERE od.order_id IN ({$placeholders})
            ORDER BY od.order_id ASC, od.id ASC
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function listProductQuantitiesByOrderIds(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $params = [];
        $placeholders = $this->orderIdPlaceholders($orderIds, $params);
        $stmt = $this->db->prepare("
            SELECT
                product_id,
                SUM(quantity) AS quantity
            FROM order_details
            WHERE order_id IN ({$placeholders})
              AND product_id IS NOT NULL
            GROUP BY product_id
            ORDER BY product_id ASC
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    private function orderIdPlaceholders(array $orderIds, array &$params): string
    {
        $placeholders = [];
        foreach (array_values($orderIds) as $index => $orderId) {
            $key = 'order_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int) $orderId;
        }

        return implode(', ', $placeholders);
    }
}
