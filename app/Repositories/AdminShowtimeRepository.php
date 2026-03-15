<?php

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\Concerns\PaginatesQueries;
use PDO;

class AdminShowtimeRepository
{
    use PaginatesQueries;

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function paginate(array $filters): array
    {
        ['where' => $where, 'params' => $params] = $this->buildFilterParts($filters);

        $bookedSubquery = "
            SELECT td.showtime_id, COUNT(DISTINCT td.seat_id) AS booked_seats
            FROM ticket_details td
            INNER JOIN ticket_orders o ON o.id = td.order_id
            WHERE o.status IN ('pending', 'paid')
            GROUP BY td.showtime_id
        ";

        $selectSql = "
            SELECT
                s.id,
                s.movie_id,
                s.room_id,
                s.show_date,
                s.start_time,
                s.end_time,
                s.price,
                s.status,
                s.presentation_type,
                s.language_version,
                s.created_at,
                s.updated_at,
                m.slug AS movie_slug,
                m.title AS movie_title,
                m.duration_minutes,
                m.poster_url,
                c.id AS cinema_id,
                c.slug AS cinema_slug,
                c.name AS cinema_name,
                c.city AS cinema_city,
                c.status AS cinema_status,
                r.room_name,
                r.room_type,
                r.screen_label,
                r.total_seats,
                r.status AS room_status,
                COALESCE(bookings.booked_seats, 0) AS booked_seats
            FROM showtimes s
            INNER JOIN movies m ON m.id = s.movie_id
            INNER JOIN rooms r ON r.id = s.room_id
            INNER JOIN cinemas c ON c.id = r.cinema_id
            LEFT JOIN ({$bookedSubquery}) bookings ON bookings.showtime_id = s.id
            {$where}
            ORDER BY s.show_date ASC, s.start_time ASC, s.id ASC
        ";

        $countSql = "
            SELECT COUNT(*)
            FROM showtimes s
            INNER JOIN movies m ON m.id = s.movie_id
            INNER JOIN rooms r ON r.id = s.room_id
            INNER JOIN cinemas c ON c.id = r.cinema_id
            {$where}
        ";

        return $this->paginateQuery($this->db, $selectSql, $countSql, $params, $filters['page'], $filters['per_page']);
    }

    public function summarize(array $filters): array
    {
        ['where' => $where, 'params' => $params] = $this->buildFilterParts($filters);

        $bookedSubquery = "
            SELECT td.showtime_id, COUNT(DISTINCT td.seat_id) AS booked_seats
            FROM ticket_details td
            INNER JOIN ticket_orders o ON o.id = td.order_id
            WHERE o.status IN ('pending', 'paid')
            GROUP BY td.showtime_id
        ";

        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN s.status = 'published' THEN 1 ELSE 0 END) AS published_count,
                SUM(CASE WHEN s.status = 'draft' THEN 1 ELSE 0 END) AS draft_count,
                SUM(CASE WHEN s.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
                SUM(CASE WHEN s.show_date = CURRENT_DATE THEN 1 ELSE 0 END) AS today_count,
                SUM(CASE WHEN COALESCE(bookings.booked_seats, 0) >= r.total_seats AND r.total_seats > 0 THEN 1 ELSE 0 END) AS sold_out_count
            FROM showtimes s
            INNER JOIN movies m ON m.id = s.movie_id
            INNER JOIN rooms r ON r.id = s.room_id
            INNER JOIN cinemas c ON c.id = r.cinema_id
            LEFT JOIN ({$bookedSubquery}) bookings ON bookings.showtime_id = s.id
            {$where}
        ");
        $this->bindValues($stmt, $params);
        $stmt->execute();
        $row = $stmt->fetch() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'published' => (int) ($row['published_count'] ?? 0),
            'draft' => (int) ($row['draft_count'] ?? 0),
            'cancelled' => (int) ($row['cancelled_count'] ?? 0),
            'today' => (int) ($row['today_count'] ?? 0),
            'sold_out' => (int) ($row['sold_out_count'] ?? 0),
        ];
    }

    public function findById(int $id): ?array
    {
        $bookedSubquery = "
            SELECT td.showtime_id, COUNT(DISTINCT td.seat_id) AS booked_seats
            FROM ticket_details td
            INNER JOIN ticket_orders o ON o.id = td.order_id
            WHERE o.status IN ('pending', 'paid')
            GROUP BY td.showtime_id
        ";

        $stmt = $this->db->prepare("
            SELECT
                s.id,
                s.movie_id,
                s.room_id,
                s.show_date,
                s.start_time,
                s.end_time,
                s.price,
                s.status,
                s.presentation_type,
                s.language_version,
                s.created_at,
                s.updated_at,
                m.slug AS movie_slug,
                m.title AS movie_title,
                m.duration_minutes,
                m.poster_url,
                c.id AS cinema_id,
                c.slug AS cinema_slug,
                c.name AS cinema_name,
                c.city AS cinema_city,
                c.status AS cinema_status,
                r.room_name,
                r.room_type,
                r.screen_label,
                r.total_seats,
                r.cleaning_buffer_minutes,
                r.status AS room_status,
                COALESCE(bookings.booked_seats, 0) AS booked_seats
            FROM showtimes s
            INNER JOIN movies m ON m.id = s.movie_id
            INNER JOIN rooms r ON r.id = s.room_id
            INNER JOIN cinemas c ON c.id = r.cinema_id
            LEFT JOIN ({$bookedSubquery}) bookings ON bookings.showtime_id = s.id
            WHERE s.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO showtimes (
                movie_id, room_id, show_date, start_time, end_time, price, status, presentation_type, language_version
            ) VALUES (
                :movie_id, :room_id, :show_date, :start_time, :end_time, :price, :status, :presentation_type, :language_version
            )
        ");
        $stmt->execute([
            'movie_id' => $data['movie_id'],
            'room_id' => $data['room_id'],
            'show_date' => $data['show_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'price' => $data['price'],
            'status' => $data['status'],
            'presentation_type' => $data['presentation_type'],
            'language_version' => $data['language_version'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE showtimes
            SET
                movie_id = :movie_id,
                room_id = :room_id,
                show_date = :show_date,
                start_time = :start_time,
                end_time = :end_time,
                price = :price,
                status = :status,
                presentation_type = :presentation_type,
                language_version = :language_version
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $id,
            'movie_id' => $data['movie_id'],
            'room_id' => $data['room_id'],
            'show_date' => $data['show_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'price' => $data['price'],
            'status' => $data['status'],
            'presentation_type' => $data['presentation_type'],
            'language_version' => $data['language_version'],
        ]);
    }

    public function archive(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE showtimes SET status = 'archived' WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function hasOverlap(int $roomId, string $showDate, string $startTime, string $endTime, ?int $excludeId = null): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM showtimes
            WHERE room_id = :room_id
              AND show_date = :show_date
              AND status NOT IN ('cancelled', 'archived')
              AND NOT (:end_time <= start_time OR :start_time >= end_time)
        ";
        $params = [
            'room_id' => $roomId,
            'show_date' => $showDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function hasPublishedFutureShowtimesForRoom(int $roomId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM showtimes
            WHERE room_id = :room_id
              AND status = 'published'
              AND show_date >= CURRENT_DATE
        ");
        $stmt->execute(['room_id' => $roomId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function buildFilterParts(array $filters): array
    {
        $conditions = [];
        $params = [];
        $scope = $filters['scope'] ?? 'active';

        if ($scope === 'archived') {
            $conditions[] = "s.status = 'archived'";
        } elseif ($scope === 'active') {
            $conditions[] = "s.status <> 'archived'";
            $conditions[] = "m.status <> 'archived'";
            $conditions[] = "r.status <> 'archived'";
            $conditions[] = "c.status <> 'archived'";
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(m.title LIKE :search OR c.name LIKE :search OR r.room_name LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['movie_id'])) {
            $conditions[] = 's.movie_id = :movie_id';
            $params['movie_id'] = (int) $filters['movie_id'];
        }
        if (!empty($filters['cinema_id'])) {
            $conditions[] = 'c.id = :cinema_id';
            $params['cinema_id'] = (int) $filters['cinema_id'];
        }
        if (!empty($filters['room_id'])) {
            $conditions[] = 's.room_id = :room_id';
            $params['room_id'] = (int) $filters['room_id'];
        }
        if (!empty($filters['status'])) {
            $conditions[] = 's.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['show_date'])) {
            $conditions[] = 's.show_date = :show_date';
            $params['show_date'] = $filters['show_date'];
        }

        return [
            'where' => empty($conditions) ? '' : ' WHERE ' . implode(' AND ', $conditions),
            'params' => $params,
        ];
    }
}
