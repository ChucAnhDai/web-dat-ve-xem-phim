<?php

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\Concerns\PaginatesQueries;
use PDO;

class ShopOrderRepository
{
    use PaginatesQueries;

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function createOrder(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO shop_orders (
                order_code,
                user_id,
                session_token,
                address_id,
                contact_name,
                contact_email,
                contact_phone,
                fulfillment_method,
                shipping_address_text,
                shipping_city,
                shipping_district,
                item_count,
                subtotal_price,
                discount_amount,
                fee_amount,
                shipping_amount,
                total_price,
                currency,
                status,
                payment_due_at,
                confirmed_at,
                fulfilled_at,
                cancelled_at
            )
            VALUES (
                :order_code,
                :user_id,
                :session_token,
                :address_id,
                :contact_name,
                :contact_email,
                :contact_phone,
                :fulfillment_method,
                :shipping_address_text,
                :shipping_city,
                :shipping_district,
                :item_count,
                :subtotal_price,
                :discount_amount,
                :fee_amount,
                :shipping_amount,
                :total_price,
                :currency,
                :status,
                :payment_due_at,
                :confirmed_at,
                :fulfilled_at,
                :cancelled_at
            )
        ');
        $stmt->execute([
            'order_code' => $data['order_code'],
            'user_id' => $data['user_id'] ?? null,
            'session_token' => $data['session_token'] ?? null,
            'address_id' => $data['address_id'] ?? null,
            'contact_name' => $data['contact_name'],
            'contact_email' => $data['contact_email'],
            'contact_phone' => $data['contact_phone'],
            'fulfillment_method' => $data['fulfillment_method'],
            'shipping_address_text' => $data['shipping_address_text'] ?? null,
            'shipping_city' => $data['shipping_city'] ?? null,
            'shipping_district' => $data['shipping_district'] ?? null,
            'item_count' => $data['item_count'],
            'subtotal_price' => $data['subtotal_price'],
            'discount_amount' => $data['discount_amount'],
            'fee_amount' => $data['fee_amount'],
            'shipping_amount' => $data['shipping_amount'],
            'total_price' => $data['total_price'],
            'currency' => $data['currency'] ?? 'VND',
            'status' => $data['status'],
            'payment_due_at' => $data['payment_due_at'] ?? null,
            'confirmed_at' => $data['confirmed_at'] ?? null,
            'fulfilled_at' => $data['fulfilled_at'] ?? null,
            'cancelled_at' => $data['cancelled_at'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findById(int $orderId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM shop_orders WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $orderId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findActivePendingOrderBySession(string $sessionToken): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, order_code, user_id, session_token, status, payment_due_at
            FROM shop_orders
            WHERE session_token = :session_token
              AND status = 'pending'
              AND payment_due_at IS NOT NULL
              AND payment_due_at > CURRENT_TIMESTAMP
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute(['session_token' => $sessionToken]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findActivePendingOrderByUser(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, order_code, user_id, session_token, status, payment_due_at
            FROM shop_orders
            WHERE user_id = :user_id
              AND status = 'pending'
              AND payment_due_at IS NOT NULL
              AND payment_due_at > CURRENT_TIMESTAMP
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function paginateMemberOrders(int $userId, array $filters): array
    {
        $filters['owner_user_id'] = $userId;

        return $this->paginateOrders($filters);
    }

    public function summarizeMemberOrders(int $userId, array $filters): array
    {
        $filters['owner_user_id'] = $userId;

        return $this->summarizeOrders($filters);
    }

    public function paginateSessionOrders(string $sessionToken, array $filters): array
    {
        $filters['owner_session_token'] = $sessionToken;
        $filters['guest_only'] = true;

        return $this->paginateOrders($filters);
    }

    public function summarizeSessionOrders(string $sessionToken, array $filters): array
    {
        $filters['owner_session_token'] = $sessionToken;
        $filters['guest_only'] = true;

        return $this->summarizeOrders($filters);
    }

    public function paginateAdminOrders(array $filters): array
    {
        return $this->paginateOrders($filters);
    }

    public function summarizeAdminOrders(array $filters): array
    {
        return $this->summarizeOrders($filters);
    }

    public function paginateAdminDetails(array $filters): array
    {
        return $this->paginateDetails($filters);
    }

    public function summarizeAdminDetails(array $filters): array
    {
        return $this->summarizeDetails($filters);
    }

    public function listFulfillmentQueue(int $limit = 8): array
    {
        $limit = max(1, min(20, $limit));
        $stmt = $this->db->prepare("
            SELECT
                o.id,
                o.order_code,
                o.user_id,
                o.session_token,
                u.name AS user_name,
                o.contact_name,
                o.contact_email,
                o.contact_phone,
                o.fulfillment_method,
                o.shipping_address_text,
                o.shipping_city,
                o.shipping_district,
                o.item_count,
                o.subtotal_price,
                o.discount_amount,
                o.fee_amount,
                o.shipping_amount,
                o.total_price,
                o.currency,
                o.status,
                o.payment_due_at,
                o.confirmed_at,
                o.fulfilled_at,
                o.cancelled_at,
                o.order_date,
                o.updated_at,
                (
                    SELECT p.payment_method
                    FROM payments p
                    WHERE p.shop_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS payment_method,
                (
                    SELECT p.payment_status
                    FROM payments p
                    WHERE p.shop_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS payment_status,
                (
                    SELECT p.transaction_code
                    FROM payments p
                    WHERE p.shop_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS transaction_code,
                (
                    SELECT p.checkout_url
                    FROM payments p
                    WHERE p.shop_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS checkout_url
            FROM shop_orders o
            LEFT JOIN users u ON u.id = o.user_id
            WHERE o.status IN ('pending', 'confirmed', 'preparing', 'ready', 'shipping')
            ORDER BY
                CASE o.status
                    WHEN 'pending' THEN 0
                    WHEN 'confirmed' THEN 1
                    WHEN 'preparing' THEN 2
                    WHEN 'ready' THEN 3
                    WHEN 'shipping' THEN 4
                    ELSE 5
                END,
                o.order_date ASC,
                o.id ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function findOrderHeaderById(int $orderId): ?array
    {
        $stmt = $this->db->prepare($this->orderHeaderSelectSql() . '
            WHERE o.id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $orderId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findOrderHeaderByCode(string $orderCode): ?array
    {
        $stmt = $this->db->prepare($this->orderHeaderSelectSql() . '
            WHERE o.order_code = :order_code
            LIMIT 1
        ');
        $stmt->execute(['order_code' => $orderCode]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function listOrderContextRowsByOrderIds(array $orderIds): array
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
                o.order_code,
                o.user_id,
                o.session_token,
                u.name AS user_name,
                o.contact_name,
                o.contact_email,
                o.contact_phone,
                o.fulfillment_method,
                o.shipping_address_text,
                o.shipping_city,
                o.shipping_district,
                o.item_count,
                o.total_price,
                o.currency,
                o.status AS order_status,
                o.payment_due_at,
                o.confirmed_at,
                o.fulfilled_at,
                o.cancelled_at,
                o.order_date,
                (
                    SELECT pay.payment_method
                    FROM payments pay
                    WHERE pay.shop_order_id = o.id
                    ORDER BY pay.id DESC
                    LIMIT 1
                ) AS payment_method,
                (
                    SELECT pay.payment_status
                    FROM payments pay
                    WHERE pay.shop_order_id = o.id
                    ORDER BY pay.id DESC
                    LIMIT 1
                ) AS payment_status,
                (
                    SELECT pay.transaction_code
                    FROM payments pay
                    WHERE pay.shop_order_id = o.id
                    ORDER BY pay.id DESC
                    LIMIT 1
                ) AS transaction_code,
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
            INNER JOIN shop_orders o ON o.id = od.order_id
            LEFT JOIN products p ON p.id = od.product_id
            LEFT JOIN users u ON u.id = o.user_id
            WHERE od.order_id IN ({$placeholders})
            ORDER BY od.order_id ASC, od.id ASC
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function listExpiredPendingOrderIds(): array
    {
        $stmt = $this->db->query("
            SELECT id
            FROM shop_orders
            WHERE status = 'pending'
              AND payment_due_at IS NOT NULL
              AND payment_due_at <= CURRENT_TIMESTAMP
            ORDER BY id ASC
        ");

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function markOrdersConfirmed(array $orderIds, ?string $confirmedAt = null): int
    {
        if ($orderIds === []) {
            return 0;
        }

        $confirmedAt = $confirmedAt ?: date('Y-m-d H:i:s');
        $params = ['confirmed_at' => $confirmedAt];
        $placeholders = $this->orderIdPlaceholders($orderIds, $params);
        $stmt = $this->db->prepare("
            UPDATE shop_orders
            SET status = 'confirmed',
                confirmed_at = :confirmed_at,
                updated_at = CURRENT_TIMESTAMP
            WHERE id IN ({$placeholders})
              AND status = 'pending'
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, $key === 'confirmed_at' ? PDO::PARAM_STR : PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function markOrdersIssue(array $orderIds, string $status, ?string $timestamp = null): int
    {
        if ($orderIds === [] || !in_array($status, ['cancelled', 'expired'], true)) {
            return 0;
        }

        $timestamp = $timestamp ?: date('Y-m-d H:i:s');
        $params = [
            'status_value' => $status,
            'cancelled_at' => $status === 'cancelled' ? $timestamp : null,
        ];
        $placeholders = $this->orderIdPlaceholders($orderIds, $params);
        $stmt = $this->db->prepare("
            UPDATE shop_orders
            SET status = :status_value,
                cancelled_at = CASE WHEN :status_value = 'cancelled' THEN :cancelled_at ELSE cancelled_at END,
                updated_at = CURRENT_TIMESTAMP
            WHERE id IN ({$placeholders})
              AND status = 'pending'
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, $key === 'status_value' || $key === 'cancelled_at' ? PDO::PARAM_STR : PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function updateOrderStatus(int $orderId, string $status, array $fields = [], array $allowedCurrentStatuses = []): bool
    {
        $assignments = [
            'status = :status',
            'updated_at = CURRENT_TIMESTAMP',
        ];
        $params = [
            'id' => $orderId,
            'status' => $status,
        ];

        foreach (['confirmed_at', 'fulfilled_at', 'cancelled_at', 'payment_due_at'] as $field) {
            if (!array_key_exists($field, $fields)) {
                continue;
            }

            $assignments[] = $field . ' = :' . $field;
            $params[$field] = $fields[$field];
        }

        $where = 'id = :id';
        if ($allowedCurrentStatuses !== []) {
            $statusParams = [];
            $placeholders = [];
            foreach (array_values($allowedCurrentStatuses) as $index => $allowedStatus) {
                $key = 'current_status_' . $index;
                $placeholders[] = ':' . $key;
                $statusParams[$key] = (string) $allowedStatus;
            }
            $params += $statusParams;
            $where .= ' AND status IN (' . implode(', ', $placeholders) . ')';
        }

        $stmt = $this->db->prepare('UPDATE shop_orders SET ' . implode(', ', $assignments) . ' WHERE ' . $where);
        foreach ($params as $key => $value) {
            $type = PDO::PARAM_STR;
            if (is_int($value)) {
                $type = PDO::PARAM_INT;
            } elseif ($value === null) {
                $type = PDO::PARAM_NULL;
            }

            $stmt->bindValue(':' . $key, $value, $type);
        }
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function orderCodeExists(string $orderCode): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM shop_orders WHERE order_code = :order_code');
        $stmt->execute(['order_code' => $orderCode]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function paginateOrders(array $filters): array
    {
        [$whereSql, $params] = $this->buildOrderWhereClause($filters);

        $selectSql = "
            SELECT
                o.id,
                o.order_code,
                o.user_id,
                o.session_token,
                u.name AS user_name,
                o.address_id,
                o.contact_name,
                o.contact_email,
                o.contact_phone,
                o.fulfillment_method,
                o.shipping_address_text,
                o.shipping_city,
                o.shipping_district,
                o.item_count,
                o.subtotal_price,
                o.discount_amount,
                o.fee_amount,
                o.shipping_amount,
                o.total_price,
                o.currency,
                o.status,
                o.payment_due_at,
                o.confirmed_at,
                o.fulfilled_at,
                o.cancelled_at,
                o.order_date,
                o.updated_at,
                (
                    SELECT p.payment_method
                    FROM payments p
                    WHERE p.shop_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS payment_method,
                (
                    SELECT p.payment_status
                    FROM payments p
                    WHERE p.shop_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS payment_status,
                (
                    SELECT p.transaction_code
                    FROM payments p
                    WHERE p.shop_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS transaction_code,
                (
                    SELECT p.checkout_url
                    FROM payments p
                    WHERE p.shop_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS checkout_url
            FROM shop_orders o
            LEFT JOIN users u ON u.id = o.user_id
            {$whereSql}
            ORDER BY o.order_date DESC, o.id DESC
        ";
        $countSql = "
            SELECT COUNT(*)
            FROM shop_orders o
            LEFT JOIN users u ON u.id = o.user_id
            {$whereSql}
        ";

        return $this->paginateQuery(
            $this->db,
            $selectSql,
            $countSql,
            $params,
            (int) ($filters['page'] ?? 1),
            (int) ($filters['per_page'] ?? 20)
        );
    }

    private function summarizeOrders(array $filters): array
    {
        [$whereSql, $params] = $this->buildOrderWhereClause($filters);
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total_orders,
                SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
                SUM(CASE WHEN o.status IN ('confirmed', 'preparing', 'ready', 'shipping') THEN 1 ELSE 0 END) AS active_orders,
                SUM(CASE WHEN o.status = 'completed' THEN 1 ELSE 0 END) AS completed_orders,
                SUM(CASE WHEN o.status IN ('cancelled', 'expired', 'refunded') THEN 1 ELSE 0 END) AS issue_orders
            FROM shop_orders o
            LEFT JOIN users u ON u.id = o.user_id
            {$whereSql}
        ");
        $this->bindValues($stmt, $params);
        $stmt->execute();

        return $stmt->fetch() ?: [
            'total_orders' => 0,
            'pending_orders' => 0,
            'active_orders' => 0,
            'completed_orders' => 0,
            'issue_orders' => 0,
        ];
    }

    private function paginateDetails(array $filters): array
    {
        [$whereSql, $params] = $this->buildDetailWhereClause($filters);

        $selectSql = "
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
                o.order_code,
                o.user_id,
                o.session_token,
                u.name AS user_name,
                o.contact_name,
                o.contact_email,
                o.contact_phone,
                o.fulfillment_method,
                o.shipping_address_text,
                o.shipping_city,
                o.shipping_district,
                o.currency,
                o.status AS order_status,
                o.order_date,
                (
                    SELECT pay.payment_method
                    FROM payments pay
                    WHERE pay.shop_order_id = o.id
                    ORDER BY pay.id DESC
                    LIMIT 1
                ) AS payment_method,
                (
                    SELECT pay.payment_status
                    FROM payments pay
                    WHERE pay.shop_order_id = o.id
                    ORDER BY pay.id DESC
                    LIMIT 1
                ) AS payment_status,
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
            INNER JOIN shop_orders o ON o.id = od.order_id
            LEFT JOIN products p ON p.id = od.product_id
            LEFT JOIN users u ON u.id = o.user_id
            {$whereSql}
            ORDER BY o.order_date DESC, od.id DESC
        ";
        $countSql = "
            SELECT COUNT(*)
            FROM order_details od
            INNER JOIN shop_orders o ON o.id = od.order_id
            LEFT JOIN products p ON p.id = od.product_id
            LEFT JOIN users u ON u.id = o.user_id
            {$whereSql}
        ";

        return $this->paginateQuery(
            $this->db,
            $selectSql,
            $countSql,
            $params,
            (int) ($filters['page'] ?? 1),
            (int) ($filters['per_page'] ?? 20)
        );
    }

    private function summarizeDetails(array $filters): array
    {
        [$whereSql, $params] = $this->buildDetailWhereClause($filters);
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total_rows,
                COUNT(DISTINCT o.id) AS total_orders,
                SUM(CASE WHEN o.status IN ('confirmed', 'preparing', 'ready', 'shipping') THEN 1 ELSE 0 END) AS active_rows,
                SUM(CASE WHEN o.fulfillment_method = 'pickup' THEN 1 ELSE 0 END) AS pickup_rows,
                SUM(CASE WHEN o.fulfillment_method = 'delivery' THEN 1 ELSE 0 END) AS delivery_rows
            FROM order_details od
            INNER JOIN shop_orders o ON o.id = od.order_id
            LEFT JOIN products p ON p.id = od.product_id
            LEFT JOIN users u ON u.id = o.user_id
            {$whereSql}
        ");
        $this->bindValues($stmt, $params);
        $stmt->execute();

        return $stmt->fetch() ?: [
            'total_rows' => 0,
            'total_orders' => 0,
            'active_rows' => 0,
            'pickup_rows' => 0,
            'delivery_rows' => 0,
        ];
    }

    private function buildOrderWhereClause(array $filters): array
    {
        $conditions = ['WHERE 1 = 1'];
        $params = [];

        if (isset($filters['owner_user_id']) && (int) $filters['owner_user_id'] > 0) {
            $conditions[] = 'AND o.user_id = :owner_user_id';
            $params['owner_user_id'] = (int) $filters['owner_user_id'];
        }

        if (($filters['owner_session_token'] ?? '') !== '') {
            $conditions[] = 'AND o.session_token = :owner_session_token';
            $params['owner_session_token'] = (string) $filters['owner_session_token'];
        }

        if (!empty($filters['guest_only'])) {
            $conditions[] = 'AND o.user_id IS NULL';
        }

        if (($filters['search'] ?? '') !== '') {
            $conditions[] = '
                AND (
                    o.order_code LIKE :search
                    OR o.contact_name LIKE :search
                    OR o.contact_email LIKE :search
                    OR o.contact_phone LIKE :search
                    OR COALESCE(u.name, \'\') LIKE :search
                )
            ';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (($filters['status'] ?? null) !== null) {
            $conditions[] = 'AND o.status = :status';
            $params['status'] = (string) $filters['status'];
        }

        if (($filters['payment_method'] ?? null) !== null) {
            $conditions[] = "
                AND (
                    SELECT p.payment_method
                    FROM payments p
                    WHERE p.shop_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) = :payment_method
            ";
            $params['payment_method'] = (string) $filters['payment_method'];
        }

        if (($filters['fulfillment_method'] ?? null) !== null) {
            $conditions[] = 'AND o.fulfillment_method = :fulfillment_method';
            $params['fulfillment_method'] = (string) $filters['fulfillment_method'];
        }

        return [implode("\n", $conditions), $params];
    }

    private function orderHeaderSelectSql(): string
    {
        return "
            SELECT
                o.id,
                o.order_code,
                o.user_id,
                o.session_token,
                u.name AS user_name,
                o.address_id,
                o.contact_name,
                o.contact_email,
                o.contact_phone,
                o.fulfillment_method,
                o.shipping_address_text,
                o.shipping_city,
                o.shipping_district,
                o.item_count,
                o.subtotal_price,
                o.discount_amount,
                o.fee_amount,
                o.shipping_amount,
                o.total_price,
                o.currency,
                o.status,
                o.payment_due_at,
                o.confirmed_at,
                o.fulfilled_at,
                o.cancelled_at,
                o.order_date,
                o.updated_at,
                (
                    SELECT p.payment_method
                    FROM payments p
                    WHERE p.shop_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS payment_method,
                (
                    SELECT p.payment_status
                    FROM payments p
                    WHERE p.shop_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS payment_status,
                (
                    SELECT p.transaction_code
                    FROM payments p
                    WHERE p.shop_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS transaction_code,
                (
                    SELECT p.checkout_url
                    FROM payments p
                    WHERE p.shop_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS checkout_url
            FROM shop_orders o
            LEFT JOIN users u ON u.id = o.user_id
        ";
    }

    private function buildDetailWhereClause(array $filters): array
    {
        $conditions = ['WHERE 1 = 1'];
        $params = [];

        if (($filters['search'] ?? '') !== '') {
            $conditions[] = '
                AND (
                    o.order_code LIKE :search
                    OR o.contact_name LIKE :search
                    OR o.contact_email LIKE :search
                    OR o.contact_phone LIKE :search
                    OR COALESCE(u.name, \'\') LIKE :search
                    OR od.product_name_snapshot LIKE :search
                    OR COALESCE(od.product_sku_snapshot, \'\') LIKE :search
                )
            ';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (($filters['status'] ?? null) !== null) {
            $conditions[] = 'AND o.status = :status';
            $params['status'] = (string) $filters['status'];
        }

        if (($filters['payment_method'] ?? null) !== null) {
            $conditions[] = "
                AND (
                    SELECT pay.payment_method
                    FROM payments pay
                    WHERE pay.shop_order_id = o.id
                    ORDER BY pay.id DESC
                    LIMIT 1
                ) = :payment_method
            ";
            $params['payment_method'] = (string) $filters['payment_method'];
        }

        if (($filters['fulfillment_method'] ?? null) !== null) {
            $conditions[] = 'AND o.fulfillment_method = :fulfillment_method';
            $params['fulfillment_method'] = (string) $filters['fulfillment_method'];
        }

        return [implode("\n", $conditions), $params];
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
