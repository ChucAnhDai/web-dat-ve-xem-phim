<?php

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\Concerns\PaginatesQueries;
use PDO;

class CinemaRepository
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
                c.id,
                c.slug,
                c.name,
                c.city,
                c.address,
                c.manager_name,
                c.support_phone,
                c.status,
                c.opening_time,
                c.closing_time,
                c.latitude,
                c.longitude,
                c.description,
                c.created_at,
                c.updated_at,
                COUNT(DISTINCT CASE WHEN r.status <> 'archived' THEN r.id END) AS room_count,
                COALESCE(SUM(CASE WHEN r.status <> 'archived' THEN r.total_seats ELSE 0 END), 0) AS total_seats
            FROM cinemas c
            LEFT JOIN rooms r ON r.cinema_id = c.id
            {$where}
            GROUP BY
                c.id,
                c.slug,
                c.name,
                c.city,
                c.address,
                c.manager_name,
                c.support_phone,
                c.status,
                c.opening_time,
                c.closing_time,
                c.latitude,
                c.longitude,
                c.description,
                c.created_at,
                c.updated_at
            ORDER BY c.updated_at DESC, c.id DESC
        ";

        $countSql = "
            SELECT COUNT(*)
            FROM cinemas c
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
                COUNT(DISTINCT c.city) AS city_count,
                SUM(CASE WHEN c.status = 'active' THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN c.status = 'renovation' THEN 1 ELSE 0 END) AS renovation_count,
                SUM(CASE WHEN c.status = 'closed' THEN 1 ELSE 0 END) AS closed_count,
                SUM(CASE WHEN c.status = 'archived' THEN 1 ELSE 0 END) AS archived_count
            FROM cinemas c
            {$where}
        ");
        $this->bindValues($stmt, $params);
        $stmt->execute();

        $summary = $stmt->fetch() ?: [];

        $roomStmt = $this->db->prepare("
            SELECT
                COUNT(DISTINCT r.id) AS room_count,
                COALESCE(SUM(r.total_seats), 0) AS total_seats
            FROM cinemas c
            LEFT JOIN rooms r ON r.cinema_id = c.id AND r.status <> 'archived'
            {$where}
        ");
        $this->bindValues($roomStmt, $params);
        $roomStmt->execute();
        $roomSummary = $roomStmt->fetch() ?: [];

        return [
            'total' => (int) ($summary['total'] ?? 0),
            'city_count' => (int) ($summary['city_count'] ?? 0),
            'active' => (int) ($summary['active_count'] ?? 0),
            'renovation' => (int) ($summary['renovation_count'] ?? 0),
            'closed' => (int) ($summary['closed_count'] ?? 0),
            'archived' => (int) ($summary['archived_count'] ?? 0),
            'room_count' => (int) ($roomSummary['room_count'] ?? 0),
            'total_seats' => (int) ($roomSummary['total_seats'] ?? 0),
        ];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.id,
                c.slug,
                c.name,
                c.city,
                c.address,
                c.manager_name,
                c.support_phone,
                c.status,
                c.opening_time,
                c.closing_time,
                c.latitude,
                c.longitude,
                c.description,
                c.created_at,
                c.updated_at,
                COUNT(DISTINCT CASE WHEN r.status <> 'archived' THEN r.id END) AS room_count,
                COALESCE(SUM(CASE WHEN r.status <> 'archived' THEN r.total_seats ELSE 0 END), 0) AS total_seats
            FROM cinemas c
            LEFT JOIN rooms r ON r.cinema_id = c.id
            WHERE c.id = :id
            GROUP BY
                c.id,
                c.slug,
                c.name,
                c.city,
                c.address,
                c.manager_name,
                c.support_phone,
                c.status,
                c.opening_time,
                c.closing_time,
                c.latitude,
                c.longitude,
                c.description,
                c.created_at,
                c.updated_at
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findBySlug(string $slug, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT * FROM cinemas WHERE slug = :slug';
        $params = ['slug' => $slug];
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
            INSERT INTO cinemas (
                slug, name, city, address, manager_name, support_phone, status,
                opening_time, closing_time, latitude, longitude, description
            ) VALUES (
                :slug, :name, :city, :address, :manager_name, :support_phone, :status,
                :opening_time, :closing_time, :latitude, :longitude, :description
            )
        ");
        $stmt->execute([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'city' => $data['city'],
            'address' => $data['address'],
            'manager_name' => $data['manager_name'],
            'support_phone' => $data['support_phone'],
            'status' => $data['status'],
            'opening_time' => $data['opening_time'],
            'closing_time' => $data['closing_time'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'description' => $data['description'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE cinemas
            SET
                slug = :slug,
                name = :name,
                city = :city,
                address = :address,
                manager_name = :manager_name,
                support_phone = :support_phone,
                status = :status,
                opening_time = :opening_time,
                closing_time = :closing_time,
                latitude = :latitude,
                longitude = :longitude,
                description = :description
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $id,
            'slug' => $data['slug'],
            'name' => $data['name'],
            'city' => $data['city'],
            'address' => $data['address'],
            'manager_name' => $data['manager_name'],
            'support_phone' => $data['support_phone'],
            'status' => $data['status'],
            'opening_time' => $data['opening_time'],
            'closing_time' => $data['closing_time'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'description' => $data['description'],
        ]);
    }

    public function archive(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE cinemas SET status = 'archived' WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function listOptions(?string $status = null, string $scope = 'active'): array
    {
        $sql = "
            SELECT id, slug, name, city, status
            FROM cinemas
        ";
        $conditions = [];
        $params = [];

        if ($scope === 'archived') {
            $conditions[] = "status = 'archived'";
        } elseif ($scope === 'active') {
            $conditions[] = "status <> 'archived'";
        }
        if ($status !== null) {
            $conditions[] = 'status = :status';
            $params['status'] = $status;
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY city ASC, name ASC, id ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public function listCities(array $filters = []): array
    {
        $normalizedFilters = $filters;
        unset($normalizedFilters['city'], $normalizedFilters['page'], $normalizedFilters['per_page']);

        ['where' => $where, 'params' => $params] = $this->buildFilterParts($normalizedFilters);

        $stmt = $this->db->prepare("
            SELECT DISTINCT c.city
            FROM cinemas c
            {$where}
            ORDER BY c.city ASC
        ");
        $this->bindValues($stmt, $params);
        $stmt->execute();

        return array_values(array_filter(array_map(static function ($row): ?string {
            $city = trim((string) ($row['city'] ?? ''));

            return $city === '' ? null : $city;
        }, $stmt->fetchAll() ?: [])));
    }

    public function hasActiveRooms(int $cinemaId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM rooms
            WHERE cinema_id = :cinema_id
              AND status = 'active'
        ");
        $stmt->execute(['cinema_id' => $cinemaId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function hasFuturePublishedShowtimes(int $cinemaId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM showtimes s
            INNER JOIN rooms r ON r.id = s.room_id
            WHERE r.cinema_id = :cinema_id
              AND s.status = 'published'
              AND s.show_date >= CURRENT_DATE
        ");
        $stmt->execute(['cinema_id' => $cinemaId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function buildFilterParts(array $filters): array
    {
        $conditions = [];
        $params = [];
        $scope = $filters['scope'] ?? 'active';

        if ($scope === 'archived') {
            $conditions[] = "c.status = 'archived'";
        } elseif ($scope === 'active') {
            $conditions[] = "c.status <> 'archived'";
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(c.name LIKE :search OR c.slug LIKE :search OR c.city LIKE :search OR c.address LIKE :search OR c.manager_name LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['city'])) {
            $conditions[] = 'c.city = :city';
            $params['city'] = $filters['city'];
        }
        if (!empty($filters['status'])) {
            $conditions[] = 'c.status = :status';
            $params['status'] = $filters['status'];
        }

        return [
            'where' => empty($conditions) ? '' : ' WHERE ' . implode(' AND ', $conditions),
            'params' => $params,
        ];
    }
}
