<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class DashboardRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function getStats(): array
    {
        $stats = [];

        // Total Movies
        $stmt = $this->db->query("SELECT COUNT(*) FROM movies WHERE status <> 'archived'");
        $stats['total_movies'] = (int) $stmt->fetchColumn();
        
        // New Movies this month
        $stmt = $this->db->query("SELECT COUNT(*) FROM movies WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stats['new_movies_month'] = (int) $stmt->fetchColumn();

        // Total Users
        $stmt = $this->db->query("SELECT COUNT(*) FROM users");
        $stats['total_users'] = (int) $stmt->fetchColumn();

        // New Users this week
        $stmt = $this->db->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['new_users_week'] = (int) $stmt->fetchColumn();

        // Tickets Sold Today
        $stmt = $this->db->query("
            SELECT SUM(seat_count) 
            FROM ticket_orders 
            WHERE status = 'paid' 
              AND DATE(order_date) = CURRENT_DATE
        ");
        $stats['tickets_sold_today'] = (int) $stmt->fetchColumn();

        // Shop Revenue (current month)
        $stmt = $this->db->query("
            SELECT SUM(total_price) 
            FROM shop_orders 
            WHERE status IN ('paid', 'confirmed', 'preparing', 'ready', 'shipping', 'completed')
              AND MONTH(order_date) = MONTH(CURRENT_DATE)
              AND YEAR(order_date) = YEAR(CURRENT_DATE)
        ");
        $stats['shop_revenue'] = (float) $stmt->fetchColumn();

        // Orders Pending
        $stmt = $this->db->query("
            SELECT (
                SELECT COUNT(*) FROM ticket_orders WHERE status = 'pending'
            ) + (
                SELECT COUNT(*) FROM shop_orders WHERE status = 'pending'
            )
        ");
        $stats['orders_pending'] = (int) $stmt->fetchColumn();

        // Active Promotions
        $stmt = $this->db->query("SELECT COUNT(*) FROM promotions WHERE status = 'active'");
        $stats['active_promotions'] = (int) $stmt->fetchColumn();

        return $stats;
    }

    public function getTicketSalesChart(int $days = 14): array
    {
        $stmt = $this->db->prepare("
            SELECT DATE_FORMAT(order_date, '%d') as day, SUM(seat_count) as total
            FROM ticket_orders
            WHERE status = 'paid'
              AND order_date >= DATE_SUB(CURRENT_DATE, INTERVAL :days DAY)
            GROUP BY DATE(order_date)
            ORDER BY DATE(order_date) ASC
        ");
        $stmt->bindValue(':days', $days - 1, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRevenueSplit(): array
    {
        $stmt = $this->db->query("
            SELECT 
                MONTH(order_date) as month,
                SUM(CASE WHEN type = 'ticket' THEN total_price ELSE 0 END) as cinema_rev,
                SUM(CASE WHEN type = 'shop' THEN total_price ELSE 0 END) as shop_rev
            FROM (
                SELECT order_date, total_price, 'ticket' as type FROM ticket_orders WHERE status = 'paid'
                UNION ALL
                SELECT order_date, total_price, 'shop' as type FROM shop_orders WHERE status IN ('paid', 'completed', 'confirmed', 'preparing', 'ready', 'shipping')
            ) combined
            WHERE order_date >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
            GROUP BY MONTH(order_date), YEAR(order_date)
            ORDER BY YEAR(order_date) ASC, MONTH(order_date) ASC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentTicketOrders(int $limit = 5): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                o.order_code as id,
                o.contact_name as user,
                (SELECT m.title FROM ticket_details td 
                 JOIN showtimes s ON s.id = td.showtime_id 
                 JOIN movies m ON m.id = s.movie_id 
                 WHERE td.order_id = o.id LIMIT 1) as movie,
                o.seat_count as seats,
                o.total_price as total,
                o.status
            FROM ticket_orders o
            ORDER BY o.order_date DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentShopOrders(int $limit = 5): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                o.order_code as id,
                o.contact_name as user,
                o.item_count as items,
                o.total_price as total,
                (SELECT payment_method FROM payments WHERE shop_order_id = o.id ORDER BY id DESC LIMIT 1) as payment,
                o.status
            FROM shop_orders o
            ORDER BY o.order_date DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTopMovies(int $limit = 4): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                m.title,
                COUNT(td.id) as tickets
            FROM movies m
            JOIN showtimes s ON s.movie_id = m.id
            JOIN ticket_details td ON td.showtime_id = s.id
            WHERE td.status = 'paid'
              AND td.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY m.id
            ORDER BY tickets DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLowStockProducts(int $limit = 5): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                p.name,
                c.name as cat,
                p.stock,
                CASE 
                    WHEN p.stock = 0 THEN 'red'
                    WHEN p.stock <= 10 THEN 'orange'
                    ELSE 'blue'
                END as level
            FROM products p
            LEFT JOIN product_categories c ON c.id = p.category_id
            WHERE p.status = 'active'
              AND p.stock <= 20
            ORDER BY p.stock ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
