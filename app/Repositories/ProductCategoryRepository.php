<?php

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\Concerns\PaginatesQueries;
use PDO;

class ProductCategoryRepository
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
                (
                    SELECT COUNT(*)
                    FROM products p
                    WHERE p.category_id = c.id
                ) AS product_count
            FROM product_categories c
            {$where}
            ORDER BY c.display_order ASC, c.name ASC
        ";
        $countSql = "
            SELECT COUNT(*)
            FROM product_categories c
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
                COUNT(DISTINCT CASE WHEN c.visibility = 'featured' THEN c.id END) AS featured_categories,
                COUNT(DISTINCT CASE WHEN c.visibility = 'standard' THEN c.id END) AS standard_categories,
                COUNT(DISTINCT CASE WHEN c.visibility = 'hidden' THEN c.id END) AS hidden_categories,
                COUNT(DISTINCT CASE WHEN c.status = 'active' THEN c.id END) AS active_categories,
                COUNT(DISTINCT CASE WHEN c.status = 'inactive' THEN c.id END) AS inactive_categories,
                COUNT(DISTINCT CASE WHEN c.status = 'archived' THEN c.id END) AS archived_categories,
                COUNT(DISTINCT p.id) AS tagged_products
            FROM product_categories c
            LEFT JOIN products p ON p.category_id = c.id
            {$where}
        ");
        $this->bindValues($stmt, $params);
        $stmt->execute();

        $row = $stmt->fetch() ?: [];

        return [
            'total' => (int) ($row['total_categories'] ?? 0),
            'featured' => (int) ($row['featured_categories'] ?? 0),
            'standard' => (int) ($row['standard_categories'] ?? 0),
            'hidden' => (int) ($row['hidden_categories'] ?? 0),
            'active' => (int) ($row['active_categories'] ?? 0),
            'inactive' => (int) ($row['inactive_categories'] ?? 0),
            'archived' => (int) ($row['archived_categories'] ?? 0),
            'products_tagged' => (int) ($row['tagged_products'] ?? 0),
        ];
    }

    public function listPublicOptions(array $filters = []): array
    {
        $conditions = [
            "c.status = 'active'",
            "c.visibility <> 'hidden'",
            "EXISTS (
                SELECT 1
                FROM products p
                WHERE p.category_id = c.id
                  AND p.status = 'active'
                  AND p.visibility <> 'hidden'
            )",
        ];
        $params = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(c.name LIKE :search OR c.slug LIKE :search OR c.description LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['featured_only'])) {
            $conditions[] = "c.visibility = 'featured'";
        }

        $where = ' WHERE ' . implode(' AND ', $conditions);
        $stmt = $this->db->prepare("
            SELECT
                c.id,
                c.name,
                c.slug,
                c.description,
                c.display_order,
                c.visibility,
                (
                    SELECT COUNT(*)
                    FROM products p
                    WHERE p.category_id = c.id
                      AND p.status = 'active'
                      AND p.visibility <> 'hidden'
                ) AS product_count
            FROM product_categories c
            {$where}
            ORDER BY
                CASE WHEN c.visibility = 'featured' THEN 0 ELSE 1 END,
                c.display_order ASC,
                c.name ASC
        ");
        $this->bindValues($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.*,
                (
                    SELECT COUNT(*)
                    FROM products p
                    WHERE p.category_id = c.id
                ) AS product_count
            FROM product_categories c
            WHERE c.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findBySlug(string $slug, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT * FROM product_categories WHERE slug = :slug';
        $params = ['slug' => $slug];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $this->bindValues($stmt, $params);
        $stmt->execute();
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO product_categories (name, slug, description, display_order, visibility, status, created_at, updated_at)
             VALUES (:name, :slug, :description, :display_order, :visibility, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'],
            'display_order' => $data['display_order'],
            'visibility' => $data['visibility'],
            'status' => $data['status'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE product_categories
             SET name = :name,
                 slug = :slug,
                 description = :description,
                 display_order = :display_order,
                 visibility = :visibility,
                 status = :status,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'],
            'display_order' => $data['display_order'],
            'visibility' => $data['visibility'],
            'status' => $data['status'],
        ]);
    }

    public function archive(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE product_categories
             SET visibility = :visibility,
                 status = :status,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'visibility' => 'hidden',
            'status' => 'archived',
        ]);
    }

    public function hasNonArchivedProducts(int $categoryId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM products
             WHERE category_id = :category_id
               AND status <> :archived'
        );
        $stmt->execute([
            'category_id' => $categoryId,
            'archived' => 'archived',
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function buildFilterParts(array $filters): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(c.name LIKE :search OR c.slug LIKE :search OR c.description LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['visibility'])) {
            $conditions[] = 'c.visibility = :visibility';
            $params['visibility'] = $filters['visibility'];
        }
        if (!empty($filters['status'])) {
            $conditions[] = 'c.status = :status';
            $params['status'] = $filters['status'];
        }

        return [
            'where' => $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '',
            'params' => $params,
        ];
    }
}
