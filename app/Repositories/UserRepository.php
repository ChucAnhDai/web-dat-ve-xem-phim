<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class UserRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function findByLoginIdentifier(string $identifier): ?array
    {
        $normalizedIdentifier = trim($identifier);
        if ($normalizedIdentifier === '') {
            return null;
        }

        $normalizedEmail = strtolower($normalizedIdentifier);
        $normalizedPhone = preg_replace('/[^0-9+]/', '', $normalizedIdentifier) ?: '';

        $stmt = $this->db->prepare(
            'SELECT *
             FROM users
             WHERE LOWER(email) = LOWER(:email)
                OR phone = :phone
             LIMIT 1'
        );
        $stmt->execute([
            'email' => $normalizedEmail,
            'phone' => $normalizedPhone,
        ]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO users (name, email, password, phone, role) VALUES (:name, :email, :password, :phone, :role)');
        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'] ?? 'user',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function createWithTransaction(array $data): int
    {
        $this->db->beginTransaction();
        try {
            $id = $this->create($data);
            $this->db->commit();
            return $id;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function getProfileStats(int $userId): array
    {
        $ticketCount = (int) $this->scalar(
            'SELECT COUNT(td.id)
             FROM ticket_orders o
             LEFT JOIN ticket_details td ON td.order_id = o.id
             WHERE o.user_id = :user_id
               AND o.status NOT IN (:cancelled, :expired, :refunded)',
            [
                'user_id' => $userId,
                'cancelled' => 'cancelled',
                'expired' => 'expired',
                'refunded' => 'refunded',
            ]
        );

        $orderCount = (int) $this->scalar(
            'SELECT COUNT(*)
             FROM shop_orders
             WHERE user_id = :user_id',
            ['user_id' => $userId]
        );

        $totalSpent = (float) $this->scalar(
            'SELECT
                COALESCE((
                    SELECT SUM(total_price)
                    FROM ticket_orders
                    WHERE user_id = :ticket_user_id
                      AND status NOT IN (:ticket_cancelled, :ticket_expired, :ticket_refunded)
                ), 0) +
                COALESCE((
                    SELECT SUM(total_price)
                    FROM shop_orders
                    WHERE user_id = :shop_user_id
                      AND status NOT IN (:shop_cancelled, :shop_expired, :shop_refunded)
                ), 0)',
            [
                'ticket_user_id' => $userId,
                'ticket_cancelled' => 'cancelled',
                'ticket_expired' => 'expired',
                'ticket_refunded' => 'refunded',
                'shop_user_id' => $userId,
                'shop_cancelled' => 'cancelled',
                'shop_expired' => 'expired',
                'shop_refunded' => 'refunded',
            ]
        );

        return [
            'tickets' => $ticketCount,
            'orders' => $orderCount,
            'spent' => round($totalSpent, 2),
        ];
    }

    public function getRecentOrders(int $userId, int $limit = 10): array
    {
        $limit = max(1, min($limit, 20));
        $ticketOrderCodeSql = $this->fallbackOrderCodeSql('o', 'T');
        $shopOrderCodeSql = $this->fallbackOrderCodeSql('o', 'S');
        $sql = "
            SELECT *
            FROM (
                SELECT
                    o.id AS order_id,
                    {$ticketOrderCodeSql} AS order_code,
                    'ticket' AS order_type,
                    COALESCE(COUNT(td.id), 0) AS items_count,
                    o.order_date AS order_date,
                    o.total_price AS total_amount,
                    o.status AS status
                FROM ticket_orders o
                LEFT JOIN ticket_details td ON td.order_id = o.id
                WHERE o.user_id = :ticket_user_id
                GROUP BY o.id, o.order_date, o.total_price, o.status

                UNION ALL

                SELECT
                    o.id AS order_id,
                    {$shopOrderCodeSql} AS order_code,
                    'shop' AS order_type,
                    COALESCE(SUM(od.quantity), 0) AS items_count,
                    o.order_date AS order_date,
                    o.total_price AS total_amount,
                    o.status AS status
                FROM shop_orders o
                LEFT JOIN order_details od ON od.order_id = o.id
                WHERE o.user_id = :shop_user_id
                GROUP BY o.id, o.order_date, o.total_price, o.status
            ) recent_orders
            ORDER BY order_date DESC
            LIMIT {$limit}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'ticket_user_id' => $userId,
            'shop_user_id' => $userId,
        ]);

        return $stmt->fetchAll() ?: [];
    }

    private function fallbackOrderCodeSql(string $tableAlias, string $prefix): string
    {
        $safePrefix = strtoupper(preg_replace('/[^A-Z0-9_-]/i', '', $prefix) ?: 'O');
        $column = sprintf('%s.order_code', $tableAlias);
        $idColumn = sprintf('%s.id', $tableAlias);
        $driver = strtolower((string) $this->db->getAttribute(PDO::ATTR_DRIVER_NAME));

        if ($driver === 'sqlite') {
            return sprintf(
                "COALESCE(NULLIF(%s, ''), '%s-' || %s)",
                $column,
                $safePrefix,
                $idColumn
            );
        }

        return sprintf(
            "COALESCE(NULLIF(%s, ''), CONCAT('%s-', %s))",
            $column,
            $safePrefix,
            $idColumn
        );
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];
        foreach ($data as $key => $value) {
            if ($key === 'id') continue;
            $fields[] = "$key = :$key";
            $params[$key] = $value;
        }
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function findAllPaginated(int $page = 1, int $limit = 10, string $search = '', string $role = '', string $status = ''): array
    {
        $offset = ($page - 1) * $limit;
        $params = [];
        $where = [];

        if ($search !== '') {
            $where[] = "(name LIKE :search OR email LIKE :search OR phone LIKE :search)";
            $params['search'] = "%$search%";
        }

        if ($role !== '' && $role !== 'All Roles') {
            $where[] = "role = :role";
            $params['role'] = strtolower($role);
        }

        if ($status !== '' && $status !== 'All Status') {
            $where[] = "status = :status";
            $params['status'] = $status;
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT id, name, email, phone, role, status, created_at FROM users {$whereSql} ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countAll(string $search = '', string $role = '', string $status = ''): int
    {
        $params = [];
        $where = [];

        if ($search !== '') {
            $where[] = "(name LIKE :search OR email LIKE :search OR phone LIKE :search)";
            $params['search'] = "%$search%";
        }

        if ($role !== '' && $role !== 'All Roles') {
            $where[] = "role = :role";
            $params['role'] = strtolower($role);
        }

        if ($status !== '' && $status !== 'All Status') {
            $where[] = "status = :status";
            $params['status'] = $status;
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT COUNT(*) FROM users {$whereSql}";
        
        return (int) $this->scalar($sql, $params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function getStats(): array
    {
        return [
            'total' => (int) $this->scalar('SELECT COUNT(*) FROM users'),
            'active' => (int) $this->scalar("SELECT COUNT(*) FROM users WHERE status = 'Active'"),
            'suspended' => (int) $this->scalar("SELECT COUNT(*) FROM users WHERE status = 'Suspended'"),
            'new_this_week' => (int) $this->scalar("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")
        ];
    }

    private function scalar(string $sql, array $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn();
    }
}
