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
        return $this->listSeatMapForShowtimeSession($showtimeId);
    }

    public function listSeatMapForShowtimeSession(int $showtimeId, ?string $sessionToken = null): array
    {
        $sessionToken = trim((string) ($sessionToken ?? ''));

        $stmt = $this->db->prepare("
            SELECT
                se.id,
                se.seat_row,
                se.seat_number,
                se.seat_type,
                se.status,
                MAX(
                    CASE
                        WHEN td.id IS NOT NULL AND o.status = 'paid' THEN 1
                        ELSE 0
                    END
                ) AS is_booked,
                MAX(
                    CASE
                        WHEN td.id IS NOT NULL AND o.status = 'pending' THEN 1
                        ELSE 0
                    END
                ) AS is_payment_pending,
                MAX(
                    CASE
                        WHEN th.id IS NOT NULL THEN 1
                        ELSE 0
                    END
                ) AS is_held,
                MAX(
                    CASE
                        WHEN th.id IS NOT NULL AND th.session_token = :session_token THEN 1
                        ELSE 0
                    END
                ) AS held_by_current_session,
                MAX(
                    CASE
                        WHEN td.id IS NOT NULL
                             AND o.status = 'pending'
                             AND :session_token <> ''
                             AND o.session_token = :session_token THEN 1
                        ELSE 0
                    END
                ) AS pending_by_current_session,
                MAX(
                    CASE
                        WHEN td.id IS NOT NULL AND o.status = 'pending' THEN o.hold_expires_at
                        ELSE NULL
                    END
                ) AS pending_expires_at,
                MAX(th.hold_expires_at) AS hold_expires_at
            FROM showtimes s
            INNER JOIN rooms r ON r.id = s.room_id
            INNER JOIN cinemas c ON c.id = r.cinema_id
            INNER JOIN movies m ON m.id = s.movie_id
            INNER JOIN seats se ON se.room_id = r.id
            LEFT JOIN ticket_details td ON td.showtime_id = s.id AND td.seat_id = se.id
            LEFT JOIN ticket_orders o ON o.id = td.order_id
            LEFT JOIN ticket_seat_holds th
                ON th.showtime_id = s.id
               AND th.seat_id = se.id
               AND th.hold_expires_at > CURRENT_TIMESTAMP
            WHERE s.id = :showtime_id
              AND s.status = 'published'
              AND m.status IN ('now_showing', 'coming_soon')
              AND r.status = 'active'
              AND c.status = 'active'
              AND se.status <> 'archived'
            GROUP BY se.id, se.seat_row, se.seat_number, se.seat_type, se.status
            ORDER BY se.seat_row ASC, se.seat_number ASC, se.id ASC
        ");
        $stmt->execute([
            'showtime_id' => $showtimeId,
            'session_token' => $sessionToken,
        ]);

        return $stmt->fetchAll() ?: [];
    }

    public function listRoomLayout(int $roomId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                room_id,
                seat_row,
                seat_number,
                seat_type,
                status,
                created_at,
                updated_at
            FROM seats
            WHERE room_id = :room_id
              AND status <> 'archived'
            ORDER BY seat_row ASC, seat_number ASC, id ASC
        ");
        $stmt->execute(['room_id' => $roomId]);

        return $stmt->fetchAll() ?: [];
    }

    public function summarizeRoomLayout(int $roomId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN seat_type = 'normal' THEN 1 ELSE 0 END) AS normal_count,
                SUM(CASE WHEN seat_type = 'vip' THEN 1 ELSE 0 END) AS vip_count,
                SUM(CASE WHEN seat_type = 'couple' THEN 1 ELSE 0 END) AS couple_count,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS available_count,
                SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) AS maintenance_count,
                SUM(CASE WHEN status = 'disabled' THEN 1 ELSE 0 END) AS disabled_count
            FROM seats
            WHERE room_id = :room_id
              AND status <> 'archived'
        ");
        $stmt->execute(['room_id' => $roomId]);
        $row = $stmt->fetch() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'normal' => (int) ($row['normal_count'] ?? 0),
            'vip' => (int) ($row['vip_count'] ?? 0),
            'couple' => (int) ($row['couple_count'] ?? 0),
            'available' => (int) ($row['available_count'] ?? 0),
            'maintenance' => (int) ($row['maintenance_count'] ?? 0),
            'disabled' => (int) ($row['disabled_count'] ?? 0),
        ];
    }

    public function replaceRoomLayout(int $roomId, array $seats): void
    {
        $deleteStmt = $this->db->prepare('DELETE FROM seats WHERE room_id = :room_id');
        $deleteStmt->execute(['room_id' => $roomId]);

        if (empty($seats)) {
            return;
        }

        $insertStmt = $this->db->prepare("
            INSERT INTO seats (room_id, seat_row, seat_number, seat_type, status)
            VALUES (:room_id, :seat_row, :seat_number, :seat_type, :status)
        ");

        foreach ($seats as $seat) {
            $insertStmt->execute([
                'room_id' => $roomId,
                'seat_row' => $seat['seat_row'],
                'seat_number' => $seat['seat_number'],
                'seat_type' => $seat['seat_type'],
                'status' => $seat['status'],
            ]);
        }
    }

    public function hasBookedTicketsForRoom(int $roomId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM ticket_details td
            INNER JOIN ticket_orders o ON o.id = td.order_id
            INNER JOIN seats s ON s.id = td.seat_id
            WHERE s.room_id = :room_id
              AND o.status IN ('pending', 'paid')
        ");
        $stmt->execute(['room_id' => $roomId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function findRoomSeatsByIds(int $roomId, array $seatIds): array
    {
        if ($roomId <= 0 || $seatIds === []) {
            return [];
        }

        $params = ['room_id' => $roomId];
        $placeholders = $this->seatIdPlaceholders($seatIds, $params);

        $stmt = $this->db->prepare("
            SELECT
                id,
                room_id,
                seat_row,
                seat_number,
                seat_type,
                status
            FROM seats
            WHERE room_id = :room_id
              AND status <> 'archived'
              AND id IN ({$placeholders})
            ORDER BY seat_row ASC, seat_number ASC, id ASC
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function findBookedSeatIdsForShowtime(int $showtimeId, array $seatIds): array
    {
        if ($showtimeId <= 0 || $seatIds === []) {
            return [];
        }

        $params = ['showtime_id' => $showtimeId];
        $placeholders = $this->seatIdPlaceholders($seatIds, $params);

        $stmt = $this->db->prepare("
            SELECT DISTINCT td.seat_id
            FROM ticket_details td
            INNER JOIN ticket_orders o ON o.id = td.order_id
            WHERE td.showtime_id = :showtime_id
              AND td.seat_id IN ({$placeholders})
              AND o.status IN ('pending', 'paid')
            ORDER BY td.seat_id ASC
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return array_map(static function ($value): int {
            return (int) $value;
        }, $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    private function seatIdPlaceholders(array $seatIds, array &$params): string
    {
        $placeholders = [];
        foreach (array_values($seatIds) as $index => $seatId) {
            $key = 'seat_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int) $seatId;
        }

        return implode(', ', $placeholders);
    }
}
