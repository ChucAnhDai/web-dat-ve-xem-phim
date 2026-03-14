<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class SeatRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function listSeatMapForShowtime(int $showtimeId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                se.id,
                se.seat_row,
                se.seat_number,
                se.seat_type,
                MAX(
                    CASE
                        WHEN td.id IS NOT NULL AND o.status IN ('pending', 'paid') THEN 1
                        ELSE 0
                    END
                ) AS is_booked
            FROM showtimes s
            INNER JOIN rooms r ON r.id = s.room_id
            INNER JOIN seats se ON se.room_id = r.id
            LEFT JOIN ticket_details td ON td.showtime_id = s.id AND td.seat_id = se.id
            LEFT JOIN ticket_orders o ON o.id = td.order_id
            WHERE s.id = :showtime_id
            GROUP BY se.id, se.seat_row, se.seat_number, se.seat_type
            ORDER BY se.seat_row ASC, se.seat_number ASC, se.id ASC
        ");
        $stmt->execute(['showtime_id' => $showtimeId]);

        return $stmt->fetchAll() ?: [];
    }
}
