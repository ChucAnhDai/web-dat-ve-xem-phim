<?php

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\Concerns\PaginatesQueries;
use PDO;

class RoomRepository
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

        $selectSql = "
            SELECT
                r.id,
                r.cinema_id,
                c.slug AS cinema_slug,
                c.name AS cinema_name,
                c.city AS cinema_city,
                c.status AS cinema_status,
                r.room_name,
                r.room_type,
                r.screen_label,
                r.projection_type,
                r.sound_profile,
                r.cleaning_buffer_minutes,
                r.total_seats,
                r.status,
                r.created_at,
                r.updated_at
            FROM rooms r
            INNER JOIN cinemas c ON c.id = r.cinema_id
            {$where}
            ORDER BY c.name ASC, r.room_name ASC, r.id ASC
        ";

        $countSql = "
            SELECT COUNT(*)
            FROM rooms r
            INNER JOIN cinemas c ON c.id = r.cinema_id
            {$where}
        ";

        return $this->paginateQuery($this->db, $selectSql, $countSql, $params, $filters['page'], $filters['per_page']);
    }

    public function summarize(array $filters): array
    {
        ['where' => $where, 'params' => $params] = $this->buildFilterParts($filters);

        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN r.status = 'active' THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN r.status = 'maintenance' THEN 1 ELSE 0 END) AS maintenance_count,
                SUM(CASE WHEN r.status = 'closed' THEN 1 ELSE 0 END) AS closed_count,
                SUM(CASE WHEN r.status = 'archived' THEN 1 ELSE 0 END) AS archived_count,
                COUNT(DISTINCT r.room_type) AS type_count,
                COALESCE(SUM(r.total_seats), 0) AS total_seats
            FROM rooms r
            INNER JOIN cinemas c ON c.id = r.cinema_id
            {$where}
        ");
        $this->bindValues($stmt, $params);
        $stmt->execute();
        $summary = $stmt->fetch() ?: [];

        return [
            'total' => (int) ($summary['total'] ?? 0),
            'active' => (int) ($summary['active_count'] ?? 0),
            'maintenance' => (int) ($summary['maintenance_count'] ?? 0),
            'closed' => (int) ($summary['closed_count'] ?? 0),
            'archived' => (int) ($summary['archived_count'] ?? 0),
            'type_count' => (int) ($summary['type_count'] ?? 0),
            'total_seats' => (int) ($summary['total_seats'] ?? 0),
        ];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                r.id,
                r.cinema_id,
                c.slug AS cinema_slug,
                c.name AS cinema_name,
                c.city AS cinema_city,
                c.status AS cinema_status,
                r.room_name,
                r.room_type,
                r.screen_label,
                r.projection_type,
                r.sound_profile,
                r.cleaning_buffer_minutes,
                r.total_seats,
                r.status,
                r.created_at,
                r.updated_at
            FROM rooms r
            INNER JOIN cinemas c ON c.id = r.cinema_id
            WHERE r.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findDuplicateName(int $cinemaId, string $roomName, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT * FROM rooms WHERE cinema_id = :cinema_id AND room_name = :room_name';
        $params = [
            'cinema_id' => $cinemaId,
            'room_name' => $roomName,
        ];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO rooms (
                cinema_id, room_name, room_type, screen_label, projection_type, sound_profile,
                cleaning_buffer_minutes, total_seats, status
            ) VALUES (
                :cinema_id, :room_name, :room_type, :screen_label, :projection_type, :sound_profile,
                :cleaning_buffer_minutes, :total_seats, :status
            )
        ");
        $stmt->execute([
            'cinema_id' => $data['cinema_id'],
            'room_name' => $data['room_name'],
            'room_type' => $data['room_type'],
            'screen_label' => $data['screen_label'],
            'projection_type' => $data['projection_type'],
            'sound_profile' => $data['sound_profile'],
            'cleaning_buffer_minutes' => $data['cleaning_buffer_minutes'],
            'total_seats' => 0,
            'status' => $data['status'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE rooms
            SET
                cinema_id = :cinema_id,
                room_name = :room_name,
                room_type = :room_type,
                screen_label = :screen_label,
                projection_type = :projection_type,
                sound_profile = :sound_profile,
                cleaning_buffer_minutes = :cleaning_buffer_minutes,
                status = :status
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $id,
            'cinema_id' => $data['cinema_id'],
            'room_name' => $data['room_name'],
            'room_type' => $data['room_type'],
            'screen_label' => $data['screen_label'],
            'projection_type' => $data['projection_type'],
            'sound_profile' => $data['sound_profile'],
            'cleaning_buffer_minutes' => $data['cleaning_buffer_minutes'],
            'status' => $data['status'],
        ]);
    }

    public function updateTotalSeats(int $roomId, int $totalSeats): void
    {
        $stmt = $this->db->prepare('UPDATE rooms SET total_seats = :total_seats WHERE id = :id');
        $stmt->execute([
            'id' => $roomId,
            'total_seats' => $totalSeats,
        ]);
    }

    public function archive(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE rooms SET status = 'archived' WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function listOptions(?int $cinemaId = null, ?string $status = null, string $scope = 'active'): array
    {
        $conditions = [];
        $params = [];

        if ($scope === 'archived') {
            $conditions[] = "r.status = 'archived'";
        } elseif ($scope === 'active') {
            $conditions[] = "r.status <> 'archived'";
            $conditions[] = "c.status <> 'archived'";
        }
        if ($cinemaId !== null) {
            $conditions[] = 'r.cinema_id = :cinema_id';
            $params['cinema_id'] = $cinemaId;
        }
        if ($status !== null) {
            $conditions[] = 'r.status = :status';
            $params['status'] = $status;
        }

        $sql = "
            SELECT
                r.id,
                r.cinema_id,
                c.name AS cinema_name,
                c.status AS cinema_status,
                r.room_name,
                r.room_type,
                r.screen_label,
                r.status,
                r.total_seats
            FROM rooms r
            INNER JOIN cinemas c ON c.id = r.cinema_id
        ";
        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY c.name ASC, r.room_name ASC, r.id ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public function hasFuturePublishedShowtimes(int $roomId): bool
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

    public function hasBookedTickets(int $roomId): bool
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

    private function buildFilterParts(array $filters): array
    {
        $conditions = [];
        $params = [];
        $scope = $filters['scope'] ?? 'active';

        if ($scope === 'archived') {
            $conditions[] = "r.status = 'archived'";
        } elseif ($scope === 'active') {
            $conditions[] = "r.status <> 'archived'";
            $conditions[] = "c.status <> 'archived'";
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(r.room_name LIKE :search OR c.name LIKE :search OR r.screen_label LIKE :search OR r.room_type LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['cinema_id'])) {
            $conditions[] = 'r.cinema_id = :cinema_id';
            $params['cinema_id'] = (int) $filters['cinema_id'];
        }
        if (!empty($filters['room_type'])) {
            $conditions[] = 'r.room_type = :room_type';
            $params['room_type'] = $filters['room_type'];
        }
        if (!empty($filters['status'])) {
            $conditions[] = 'r.status = :status';
            $params['status'] = $filters['status'];
        }

        return [
            'where' => empty($conditions) ? '' : ' WHERE ' . implode(' AND ', $conditions),
            'params' => $params,
        ];
    }
}
