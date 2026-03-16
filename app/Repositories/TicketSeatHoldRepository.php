<?php

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\Concerns\PaginatesQueries;
use PDO;

class TicketSeatHoldRepository
{
    use PaginatesQueries;

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function purgeExpired(): int
    {
        $stmt = $this->db->prepare('DELETE FROM ticket_seat_holds WHERE hold_expires_at <= CURRENT_TIMESTAMP');
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function findActiveConflicts(int $showtimeId, array $seatIds, string $sessionToken): array
    {
        if ($seatIds === []) {
            return [];
        }

        $params = [
            'showtime_id' => $showtimeId,
            'session_token' => $sessionToken,
        ];
        $placeholders = $this->seatIdPlaceholders($seatIds, $params);

        $stmt = $this->db->prepare("
            SELECT seat_id, session_token, hold_expires_at
            FROM ticket_seat_holds
            WHERE showtime_id = :showtime_id
              AND hold_expires_at > CURRENT_TIMESTAMP
              AND session_token <> :session_token
              AND seat_id IN ({$placeholders})
            ORDER BY seat_id ASC
        ");
        $this->bindValues($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function releaseForSessionAndShowtime(int $showtimeId, string $sessionToken): int
    {
        $stmt = $this->db->prepare('
            DELETE FROM ticket_seat_holds
            WHERE showtime_id = :showtime_id
              AND session_token = :session_token
        ');
        $stmt->execute([
            'showtime_id' => $showtimeId,
            'session_token' => $sessionToken,
        ]);

        return $stmt->rowCount();
    }

    public function releaseForSession(string $sessionToken): int
    {
        $stmt = $this->db->prepare('
            DELETE FROM ticket_seat_holds
            WHERE session_token = :session_token
        ');
        $stmt->execute([
            'session_token' => $sessionToken,
        ]);

        return $stmt->rowCount();
    }

    public function createHold(int $showtimeId, int $seatId, ?int $userId, string $sessionToken, string $holdExpiresAt): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO ticket_seat_holds (showtime_id, seat_id, user_id, session_token, hold_expires_at)
            VALUES (:showtime_id, :seat_id, :user_id, :session_token, :hold_expires_at)
        ');
        $stmt->execute([
            'showtime_id' => $showtimeId,
            'seat_id' => $seatId,
            'user_id' => $userId,
            'session_token' => $sessionToken,
            'hold_expires_at' => $holdExpiresAt,
        ]);
    }

    public function listActiveSeatIdsForSessionAndShowtime(int $showtimeId, string $sessionToken): array
    {
        $stmt = $this->db->prepare('
            SELECT seat_id
            FROM ticket_seat_holds
            WHERE showtime_id = :showtime_id
              AND session_token = :session_token
              AND hold_expires_at > CURRENT_TIMESTAMP
            ORDER BY seat_id ASC
        ');
        $stmt->execute([
            'showtime_id' => $showtimeId,
            'session_token' => $sessionToken,
        ]);

        return array_map(static function ($value): int {
            return (int) $value;
        }, $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function listActiveRowsForSessionAndShowtime(int $showtimeId, string $sessionToken): array
    {
        $stmt = $this->db->prepare('
            SELECT
                id,
                showtime_id,
                seat_id,
                user_id,
                session_token,
                hold_expires_at
            FROM ticket_seat_holds
            WHERE showtime_id = :showtime_id
              AND session_token = :session_token
              AND hold_expires_at > CURRENT_TIMESTAMP
            ORDER BY seat_id ASC, id ASC
        ');
        $stmt->execute([
            'showtime_id' => $showtimeId,
            'session_token' => $sessionToken,
        ]);

        return $stmt->fetchAll() ?: [];
    }

    public function listActiveQueue(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));

        $stmt = $this->db->prepare("
            SELECT
                th.id,
                th.showtime_id,
                th.seat_id,
                th.user_id,
                th.session_token,
                th.hold_expires_at,
                u.name AS user_name,
                u.email AS user_email,
                u.phone AS user_phone,
                m.title AS movie_title,
                s.show_date,
                s.start_time,
                c.name AS cinema_name,
                r.room_name,
                se.seat_row,
                se.seat_number
            FROM ticket_seat_holds th
            INNER JOIN showtimes s ON s.id = th.showtime_id
            INNER JOIN movies m ON m.id = s.movie_id
            INNER JOIN seats se ON se.id = th.seat_id
            INNER JOIN rooms r ON r.id = s.room_id
            INNER JOIN cinemas c ON c.id = r.cinema_id
            LEFT JOIN users u ON u.id = th.user_id
            WHERE th.hold_expires_at > CURRENT_TIMESTAMP
            ORDER BY th.hold_expires_at ASC, th.id ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
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
