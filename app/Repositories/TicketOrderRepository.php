<?php

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\Concerns\PaginatesQueries;
use PDO;

class TicketOrderRepository
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
            INSERT INTO ticket_orders (
                order_code,
                user_id,
                session_token,
                contact_name,
                contact_email,
                contact_phone,
                fulfillment_method,
                seat_count,
                subtotal_price,
                discount_amount,
                fee_amount,
                total_price,
                currency,
                status,
                hold_expires_at,
                paid_at
            )
            VALUES (
                :order_code,
                :user_id,
                :session_token,
                :contact_name,
                :contact_email,
                :contact_phone,
                :fulfillment_method,
                :seat_count,
                :subtotal_price,
                :discount_amount,
                :fee_amount,
                :total_price,
                :currency,
                :status,
                :hold_expires_at,
                :paid_at
            )
        ');
        $stmt->execute([
            'order_code' => $data['order_code'],
            'user_id' => $data['user_id'],
            'session_token' => $data['session_token'] ?? null,
            'contact_name' => $data['contact_name'],
            'contact_email' => $data['contact_email'],
            'contact_phone' => $data['contact_phone'],
            'fulfillment_method' => $data['fulfillment_method'],
            'seat_count' => $data['seat_count'],
            'subtotal_price' => $data['subtotal_price'],
            'discount_amount' => $data['discount_amount'],
            'fee_amount' => $data['fee_amount'],
            'total_price' => $data['total_price'],
            'currency' => $data['currency'],
            'status' => $data['status'],
            'hold_expires_at' => $data['hold_expires_at'],
            'paid_at' => $data['paid_at'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findActivePendingOrderBySession(string $sessionToken): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                order_code,
                user_id,
                status,
                hold_expires_at
            FROM ticket_orders
            WHERE session_token = :session_token
              AND status = 'pending'
              AND hold_expires_at IS NOT NULL
              AND hold_expires_at > CURRENT_TIMESTAMP
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([
            'session_token' => $sessionToken,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findActivePendingOrderByUser(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                order_code,
                user_id,
                status,
                hold_expires_at
            FROM ticket_orders
            WHERE user_id = :user_id
              AND status = 'pending'
              AND hold_expires_at IS NOT NULL
              AND hold_expires_at > CURRENT_TIMESTAMP
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([
            'user_id' => $userId,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function createTicketDetails(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $stmt = $this->db->prepare('
            INSERT INTO ticket_details (
                order_id,
                showtime_id,
                seat_id,
                ticket_code,
                status,
                base_price,
                surcharge_amount,
                discount_amount,
                price,
                qr_payload
            )
            VALUES (
                :order_id,
                :showtime_id,
                :seat_id,
                :ticket_code,
                :status,
                :base_price,
                :surcharge_amount,
                :discount_amount,
                :price,
                :qr_payload
            )
        ');

        foreach ($rows as $row) {
            $stmt->execute([
                'order_id' => $row['order_id'],
                'showtime_id' => $row['showtime_id'],
                'seat_id' => $row['seat_id'],
                'ticket_code' => $row['ticket_code'],
                'status' => $row['status'],
                'base_price' => $row['base_price'],
                'surcharge_amount' => $row['surcharge_amount'],
                'discount_amount' => $row['discount_amount'],
                'price' => $row['price'],
                'qr_payload' => $row['qr_payload'],
            ]);
        }
    }

    public function listExpiredPendingOrderIds(): array
    {
        $stmt = $this->db->query("
            SELECT id
            FROM ticket_orders
            WHERE status = 'pending'
              AND hold_expires_at IS NOT NULL
              AND hold_expires_at <= CURRENT_TIMESTAMP
            ORDER BY id ASC
        ");

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function expireOrders(array $orderIds): int
    {
        if ($orderIds === []) {
            return 0;
        }

        $params = [];
        $placeholders = $this->orderIdPlaceholders($orderIds, $params);
        $stmt = $this->db->prepare("
            UPDATE ticket_orders
            SET status = 'expired',
                updated_at = CURRENT_TIMESTAMP
            WHERE id IN ({$placeholders})
              AND status = 'pending'
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function expireTicketDetailsForOrderIds(array $orderIds): int
    {
        if ($orderIds === []) {
            return 0;
        }

        $params = [];
        $placeholders = $this->orderIdPlaceholders($orderIds, $params);
        $stmt = $this->db->prepare("
            UPDATE ticket_details
            SET status = 'expired',
                updated_at = CURRENT_TIMESTAMP
            WHERE order_id IN ({$placeholders})
              AND status = 'pending'
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function markOrdersPaid(array $orderIds, ?string $paidAt = null): int
    {
        if ($orderIds === []) {
            return 0;
        }

        $paidAt = $paidAt ?: date('Y-m-d H:i:s');
        $params = ['paid_at' => $paidAt];
        $placeholders = $this->orderIdPlaceholders($orderIds, $params);
        $stmt = $this->db->prepare("
            UPDATE ticket_orders
            SET status = 'paid',
                paid_at = :paid_at,
                hold_expires_at = NULL,
                updated_at = CURRENT_TIMESTAMP
            WHERE id IN ({$placeholders})
              AND status <> 'paid'
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, $key === 'paid_at' ? PDO::PARAM_STR : PDO::PARAM_INT);
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
            UPDATE ticket_orders
            SET status = :status_value,
                cancelled_at = CASE WHEN :status_value = 'cancelled' THEN :cancelled_at ELSE cancelled_at END,
                hold_expires_at = NULL,
                updated_at = CURRENT_TIMESTAMP
            WHERE id IN ({$placeholders})
              AND status NOT IN ('paid', 'refunded')
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, $key === 'status_value' || $key === 'cancelled_at' ? PDO::PARAM_STR : PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function markTicketDetailsStatusForOrderIds(array $orderIds, string $status): int
    {
        if ($orderIds === [] || !in_array($status, ['pending', 'paid', 'cancelled', 'expired', 'refunded', 'used'], true)) {
            return 0;
        }

        $params = ['status_value' => $status];
        $placeholders = $this->orderIdPlaceholders($orderIds, $params);
        $stmt = $this->db->prepare("
            UPDATE ticket_details
            SET status = :status_value,
                updated_at = CURRENT_TIMESTAMP
            WHERE order_id IN ({$placeholders})
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, $key === 'status_value' ? PDO::PARAM_STR : PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function orderCodeExists(string $orderCode): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM ticket_orders WHERE order_code = :order_code');
        $stmt->execute(['order_code' => $orderCode]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function ticketCodeExists(string $ticketCode): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM ticket_details WHERE ticket_code = :ticket_code');
        $stmt->execute(['ticket_code' => $ticketCode]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function paginateUserOrders(int $userId, array $filters): array
    {
        return $this->paginateOrders($this->buildOrderFilters($filters, $userId));
    }

    public function summarizeUserOrders(int $userId, array $filters): array
    {
        return $this->summarizeOrders($this->buildOrderFilters($filters, $userId));
    }

    public function paginateAdminOrders(array $filters): array
    {
        return $this->paginateOrders($this->buildOrderFilters($filters, null));
    }

    public function summarizeAdminOrders(array $filters): array
    {
        return $this->summarizeOrders($this->buildOrderFilters($filters, null));
    }

    public function findOrderHeaderById(int $orderId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                o.id,
                o.order_code,
                o.user_id,
                o.session_token,
                o.contact_name,
                o.contact_email,
                o.contact_phone,
                o.fulfillment_method,
                o.seat_count,
                o.subtotal_price,
                o.discount_amount,
                o.fee_amount,
                o.total_price,
                o.currency,
                o.status,
                o.hold_expires_at,
                o.paid_at,
                o.order_date,
                o.updated_at,
                (
                    SELECT p.payment_method
                    FROM payments p
                    WHERE p.ticket_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS payment_method,
                (
                    SELECT p.payment_status
                    FROM payments p
                    WHERE p.ticket_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS payment_status,
                (
                    SELECT p.transaction_code
                    FROM payments p
                    WHERE p.ticket_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS transaction_code
            FROM ticket_orders o
            WHERE o.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $orderId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findOrderHeaderByCode(string $orderCode): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                o.id,
                o.order_code,
                o.user_id,
                o.session_token,
                o.contact_name,
                o.contact_email,
                o.contact_phone,
                o.fulfillment_method,
                o.seat_count,
                o.subtotal_price,
                o.discount_amount,
                o.fee_amount,
                o.total_price,
                o.currency,
                o.status,
                o.hold_expires_at,
                o.paid_at,
                o.order_date,
                o.updated_at,
                (
                    SELECT p.payment_method
                    FROM payments p
                    WHERE p.ticket_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS payment_method,
                (
                    SELECT p.payment_status
                    FROM payments p
                    WHERE p.ticket_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS payment_status,
                (
                    SELECT p.transaction_code
                    FROM payments p
                    WHERE p.ticket_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS transaction_code
            FROM ticket_orders o
            WHERE o.order_code = :order_code
            LIMIT 1
        ");
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
                td.id,
                td.order_id,
                td.showtime_id,
                td.seat_id,
                td.ticket_code,
                td.status AS ticket_status,
                td.base_price,
                td.surcharge_amount,
                td.discount_amount,
                td.price,
                td.qr_payload,
                td.scanned_at,
                td.created_at,
                o.order_code,
                o.user_id,
                o.contact_name,
                o.contact_email,
                o.contact_phone,
                o.fulfillment_method,
                o.status AS order_status,
                o.order_date,
                (
                    SELECT p.payment_method
                    FROM payments p
                    WHERE p.ticket_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS payment_method,
                (
                    SELECT p.payment_status
                    FROM payments p
                    WHERE p.ticket_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS payment_status,
                (
                    SELECT p.transaction_code
                    FROM payments p
                    WHERE p.ticket_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS transaction_code,
                s.show_date,
                s.start_time,
                s.end_time,
                s.presentation_type,
                s.language_version,
                m.id AS movie_id,
                m.slug AS movie_slug,
                m.title AS movie_title,
                m.poster_url,
                c.id AS cinema_id,
                c.name AS cinema_name,
                c.city AS cinema_city,
                r.id AS room_id,
                r.room_name,
                se.seat_row,
                se.seat_number,
                se.seat_type
            FROM ticket_details td
            INNER JOIN ticket_orders o ON o.id = td.order_id
            INNER JOIN showtimes s ON s.id = td.showtime_id
            INNER JOIN movies m ON m.id = s.movie_id
            INNER JOIN rooms r ON r.id = s.room_id
            INNER JOIN cinemas c ON c.id = r.cinema_id
            INNER JOIN seats se ON se.id = td.seat_id
            WHERE td.order_id IN ({$placeholders})
            ORDER BY td.order_id ASC, td.id ASC
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function paginateUserTickets(int $userId, array $filters): array
    {
        return $this->paginateTickets($this->buildTicketFilters($filters, $userId));
    }

    public function summarizeUserTickets(int $userId, array $filters): array
    {
        return $this->summarizeTickets($this->buildTicketFilters($filters, $userId));
    }

    public function paginateAdminTickets(array $filters): array
    {
        return $this->paginateTickets($this->buildTicketFilters($filters, null));
    }

    public function summarizeAdminTickets(array $filters): array
    {
        return $this->summarizeTickets($this->buildTicketFilters($filters, null));
    }

    public function findTicketRowById(int $ticketId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                td.id,
                td.order_id,
                td.showtime_id,
                td.seat_id,
                td.ticket_code,
                td.status AS ticket_status,
                td.base_price,
                td.surcharge_amount,
                td.discount_amount,
                td.price,
                td.qr_payload,
                td.scanned_at,
                td.created_at,
                o.order_code,
                o.user_id,
                o.session_token,
                o.contact_name,
                o.contact_email,
                o.contact_phone,
                o.fulfillment_method,
                o.status AS order_status,
                o.order_date,
                (
                    SELECT p.payment_method
                    FROM payments p
                    WHERE p.ticket_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS payment_method,
                (
                    SELECT p.payment_status
                    FROM payments p
                    WHERE p.ticket_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS payment_status,
                (
                    SELECT p.transaction_code
                    FROM payments p
                    WHERE p.ticket_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS transaction_code,
                s.show_date,
                s.start_time,
                s.end_time,
                s.presentation_type,
                s.language_version,
                m.title AS movie_title,
                m.slug AS movie_slug,
                m.poster_url,
                c.name AS cinema_name,
                c.city AS cinema_city,
                r.room_name,
                se.seat_row,
                se.seat_number,
                se.seat_type
            FROM ticket_details td
            INNER JOIN ticket_orders o ON o.id = td.order_id
            INNER JOIN showtimes s ON s.id = td.showtime_id
            INNER JOIN movies m ON m.id = s.movie_id
            INNER JOIN rooms r ON r.id = s.room_id
            INNER JOIN cinemas c ON c.id = r.cinema_id
            INNER JOIN seats se ON se.id = td.seat_id
            WHERE td.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $ticketId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function paginateOrders(array $filterParts): array
    {
        $where = $filterParts['where'];
        $params = $filterParts['params'];
        $filters = $filterParts['filters'];

        $selectSql = "
            SELECT
                o.id,
                o.order_code,
                o.user_id,
                o.session_token,
                o.contact_name,
                o.contact_email,
                o.contact_phone,
                o.fulfillment_method,
                o.seat_count,
                o.subtotal_price,
                o.discount_amount,
                o.fee_amount,
                o.total_price,
                o.currency,
                o.status,
                o.hold_expires_at,
                o.paid_at,
                o.order_date,
                o.updated_at,
                (
                    SELECT p.payment_method
                    FROM payments p
                    WHERE p.ticket_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS payment_method,
                (
                    SELECT p.payment_status
                    FROM payments p
                    WHERE p.ticket_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS payment_status,
                (
                    SELECT p.transaction_code
                    FROM payments p
                    WHERE p.ticket_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS transaction_code
            FROM ticket_orders o
            {$where}
            ORDER BY o.order_date DESC, o.id DESC
        ";

        $countSql = "
            SELECT COUNT(*)
            FROM ticket_orders o
            {$where}
        ";

        return $this->paginateQuery(
            $this->db,
            $selectSql,
            $countSql,
            $params,
            $filters['page'],
            $filters['per_page']
        );
    }

    private function summarizeOrders(array $filterParts): array
    {
        $where = $filterParts['where'];
        $params = $filterParts['params'];

        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total_orders,
                SUM(CASE WHEN o.status = 'paid' THEN 1 ELSE 0 END) AS paid_orders,
                SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
                SUM(CASE WHEN o.status IN ('cancelled', 'expired', 'refunded') THEN 1 ELSE 0 END) AS risk_orders
            FROM ticket_orders o
            {$where}
        ");
        $this->bindValues($stmt, $params);
        $stmt->execute();
        $row = $stmt->fetch() ?: [];

        return [
            'total_orders' => (int) ($row['total_orders'] ?? 0),
            'paid_orders' => (int) ($row['paid_orders'] ?? 0),
            'pending_orders' => (int) ($row['pending_orders'] ?? 0),
            'risk_orders' => (int) ($row['risk_orders'] ?? 0),
        ];
    }

    private function paginateTickets(array $filterParts): array
    {
        $where = $filterParts['where'];
        $params = $filterParts['params'];
        $filters = $filterParts['filters'];

        $selectSql = "
            SELECT
                td.id,
                td.order_id,
                td.showtime_id,
                td.seat_id,
                td.ticket_code,
                td.status AS ticket_status,
                td.base_price,
                td.surcharge_amount,
                td.discount_amount,
                td.price,
                td.qr_payload,
                td.scanned_at,
                td.created_at,
                o.order_code,
                o.user_id,
                o.contact_name,
                o.contact_email,
                o.contact_phone,
                o.fulfillment_method,
                o.status AS order_status,
                o.order_date,
                (
                    SELECT p.payment_method
                    FROM payments p
                    WHERE p.ticket_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS payment_method,
                (
                    SELECT p.payment_status
                    FROM payments p
                    WHERE p.ticket_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS payment_status,
                (
                    SELECT p.transaction_code
                    FROM payments p
                    WHERE p.ticket_order_id = o.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) AS transaction_code,
                s.show_date,
                s.start_time,
                s.end_time,
                s.presentation_type,
                s.language_version,
                m.title AS movie_title,
                m.poster_url,
                c.name AS cinema_name,
                c.city AS cinema_city,
                r.room_name,
                se.seat_row,
                se.seat_number,
                se.seat_type
            FROM ticket_details td
            INNER JOIN ticket_orders o ON o.id = td.order_id
            INNER JOIN showtimes s ON s.id = td.showtime_id
            INNER JOIN movies m ON m.id = s.movie_id
            INNER JOIN rooms r ON r.id = s.room_id
            INNER JOIN cinemas c ON c.id = r.cinema_id
            INNER JOIN seats se ON se.id = td.seat_id
            {$where}
            ORDER BY s.show_date DESC, s.start_time DESC, td.id DESC
        ";

        $countSql = "
            SELECT COUNT(*)
            FROM ticket_details td
            INNER JOIN ticket_orders o ON o.id = td.order_id
            INNER JOIN showtimes s ON s.id = td.showtime_id
            INNER JOIN movies m ON m.id = s.movie_id
            INNER JOIN rooms r ON r.id = s.room_id
            INNER JOIN cinemas c ON c.id = r.cinema_id
            INNER JOIN seats se ON se.id = td.seat_id
            {$where}
        ";

        return $this->paginateQuery(
            $this->db,
            $selectSql,
            $countSql,
            $params,
            $filters['page'],
            $filters['per_page']
        );
    }

    private function summarizeTickets(array $filterParts): array
    {
        $where = $filterParts['where'];
        $params = $filterParts['params'];

        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total_tickets,
                SUM(CASE WHEN td.status = 'paid' THEN 1 ELSE 0 END) AS paid_tickets,
                SUM(CASE WHEN td.status = 'pending' THEN 1 ELSE 0 END) AS pending_tickets,
                SUM(CASE WHEN td.status = 'used' THEN 1 ELSE 0 END) AS used_tickets,
                SUM(CASE WHEN td.status IN ('cancelled', 'expired', 'refunded') THEN 1 ELSE 0 END) AS issue_tickets
            FROM ticket_details td
            INNER JOIN ticket_orders o ON o.id = td.order_id
            INNER JOIN showtimes s ON s.id = td.showtime_id
            INNER JOIN movies m ON m.id = s.movie_id
            INNER JOIN rooms r ON r.id = s.room_id
            INNER JOIN cinemas c ON c.id = r.cinema_id
            INNER JOIN seats se ON se.id = td.seat_id
            {$where}
        ");
        $this->bindValues($stmt, $params);
        $stmt->execute();
        $row = $stmt->fetch() ?: [];

        return [
            'total_tickets' => (int) ($row['total_tickets'] ?? 0),
            'paid_tickets' => (int) ($row['paid_tickets'] ?? 0),
            'pending_tickets' => (int) ($row['pending_tickets'] ?? 0),
            'used_tickets' => (int) ($row['used_tickets'] ?? 0),
            'issue_tickets' => (int) ($row['issue_tickets'] ?? 0),
        ];
    }

    private function buildOrderFilters(array $filters, ?int $userId): array
    {
        $conditions = [];
        $params = [];

        if ($userId !== null) {
            $conditions[] = 'o.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        if (!empty($filters['search'])) {
            $conditions[] = "
                (
                    o.order_code LIKE :search
                    OR o.contact_name LIKE :search
                    OR o.contact_email LIKE :search
                    OR o.contact_phone LIKE :search
                    OR EXISTS (
                        SELECT 1
                        FROM ticket_details td
                        INNER JOIN showtimes s ON s.id = td.showtime_id
                        INNER JOIN movies m ON m.id = s.movie_id
                        INNER JOIN rooms r ON r.id = s.room_id
                        INNER JOIN cinemas c ON c.id = r.cinema_id
                        WHERE td.order_id = o.id
                          AND (
                              m.title LIKE :search
                              OR c.name LIKE :search
                              OR r.room_name LIKE :search
                          )
                    )
                )
            ";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['status'])) {
            $conditions[] = 'o.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['payment_method'])) {
            $conditions[] = '
                EXISTS (
                    SELECT 1
                    FROM payments p
                    WHERE p.ticket_order_id = o.id
                      AND p.payment_method = :payment_method
                )
            ';
            $params['payment_method'] = $filters['payment_method'];
        }

        return [
            'where' => $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions),
            'params' => $params,
            'filters' => $filters,
        ];
    }

    private function buildTicketFilters(array $filters, ?int $userId): array
    {
        $conditions = [];
        $params = [];

        if ($userId !== null) {
            $conditions[] = 'o.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        if (!empty($filters['search'])) {
            $conditions[] = "
                (
                    td.ticket_code LIKE :search
                    OR o.order_code LIKE :search
                    OR o.contact_name LIKE :search
                    OR o.contact_email LIKE :search
                    OR m.title LIKE :search
                    OR c.name LIKE :search
                    OR r.room_name LIKE :search
                )
            ";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['status'])) {
            $conditions[] = 'td.status = :status';
            $params['status'] = $filters['status'];
        }

        return [
            'where' => $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions),
            'params' => $params,
            'filters' => $filters,
        ];
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
