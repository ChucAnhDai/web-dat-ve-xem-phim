<?php

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\Concerns\PaginatesQueries;
use PDO;

class MovieCategoryRepository
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
                c.*,
                COUNT(DISTINCT a.movie_id) AS movie_count
            FROM movie_categories c
            LEFT JOIN movie_category_assignments a ON a.category_id = c.id
            {$where}
            GROUP BY c.id, c.name, c.slug, c.description, c.display_order, c.is_active, c.created_at, c.updated_at
            ORDER BY c.display_order ASC, c.name ASC
        ";
        $countSql = "
            SELECT COUNT(*)
            FROM movie_categories c
            {$where}
        ";

        return $this->paginateQuery($this->db, $selectSql, $countSql, $params, $filters['page'], $filters['per_page']);
    }

    public function summarize(array $filters): array
    {
        ['where' => $where, 'params' => $params] = $this->buildFilterParts($filters);

        $stmt = $this->db->prepare("
            SELECT
                COUNT(DISTINCT c.id) AS total_categories,
                SUM(CASE WHEN c.is_active = 1 THEN 1 ELSE 0 END) AS active_categories,
                SUM(CASE WHEN c.is_active = 0 THEN 1 ELSE 0 END) AS inactive_categories,
                COUNT(a.movie_id) AS tagged_movies
            FROM movie_categories c
            LEFT JOIN movie_category_assignments a ON a.category_id = c.id
            {$where}
        ");
        $this->bindValues($stmt, $params);
        $stmt->execute();

        $row = $stmt->fetch() ?: [];

        return [
            'total' => (int) ($row['total_categories'] ?? 0),
            'active' => (int) ($row['active_categories'] ?? 0),
            'inactive' => (int) ($row['inactive_categories'] ?? 0),
            'tagged_movies' => (int) ($row['tagged_movies'] ?? 0),
        ];
    }

    public function listPublicOptions(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.id,
                c.name,
                c.slug
            FROM movie_categories c
            INNER JOIN movie_category_assignments a
                ON a.category_id = c.id
            INNER JOIN movies m
                ON m.id = a.movie_id
               AND m.status IN ('now_showing', 'coming_soon')
            WHERE c.is_active = 1
            GROUP BY c.id, c.name, c.slug, c.display_order
            ORDER BY c.display_order ASC, c.name ASC
        ");
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM movie_categories WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findBySlug(string $slug, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT * FROM movie_categories WHERE slug = :slug';
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

    public function countByIds(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($ids) as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM movie_categories WHERE id IN (' . implode(', ', $placeholders) . ')');
        $this->bindValues($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO movie_categories (name, slug, description, display_order, is_active, created_at, updated_at)
             VALUES (:name, :slug, :description, :display_order, :is_active, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'],
            'display_order' => $data['display_order'],
            'is_active' => $data['is_active'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE movie_categories
             SET name = :name,
                 slug = :slug,
                 description = :description,
                 display_order = :display_order,
                 is_active = :is_active,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'],
            'display_order' => $data['display_order'],
            'is_active' => $data['is_active'],
        ]);
    }

    public function deactivate(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE movie_categories
             SET is_active = 0, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        return $stmt->execute(['id' => $id]);
    }

    private function buildFilterParts(array $filters): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(c.name LIKE :search OR c.slug LIKE :search OR c.description LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if ($filters['is_active'] !== null) {
            $conditions[] = 'c.is_active = :is_active';
            $params['is_active'] = (int) $filters['is_active'];
        }

        return [
            'where' => $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '',
            'params' => $params,
        ];
    }
}
