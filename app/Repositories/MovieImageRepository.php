<?php

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\Concerns\PaginatesQueries;
use PDO;

class MovieImageRepository
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
                mi.*,
                m.title AS movie_title,
                m.slug AS movie_slug
            FROM movie_images mi
            INNER JOIN movies m ON m.id = mi.movie_id
            {$where}
            ORDER BY mi.updated_at DESC, mi.id DESC
        ";
        $countSql = "
            SELECT COUNT(*)
            FROM movie_images mi
            INNER JOIN movies m ON m.id = mi.movie_id
            {$where}
        ";

        return $this->paginateQuery($this->db, $selectSql, $countSql, $params, $filters['page'], $filters['per_page']);
    }

    public function summarize(array $filters): array
    {
        ['where' => $where, 'params' => $params] = $this->buildFilterParts($filters);

        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total_assets,
                SUM(CASE WHEN mi.asset_type = 'poster' THEN 1 ELSE 0 END) AS poster_assets,
                SUM(CASE WHEN mi.asset_type = 'banner' THEN 1 ELSE 0 END) AS banner_assets,
                SUM(CASE WHEN mi.asset_type = 'gallery' THEN 1 ELSE 0 END) AS gallery_assets,
                SUM(CASE WHEN mi.status = 'draft' THEN 1 ELSE 0 END) AS draft_assets,
                SUM(CASE WHEN mi.status = 'active' THEN 1 ELSE 0 END) AS active_assets,
                SUM(CASE WHEN mi.status = 'archived' THEN 1 ELSE 0 END) AS archived_assets
            FROM movie_images mi
            INNER JOIN movies m ON m.id = mi.movie_id
            {$where}
        ");
        $this->bindValues($stmt, $params);
        $stmt->execute();

        $row = $stmt->fetch() ?: [];

        return [
            'total' => (int) ($row['total_assets'] ?? 0),
            'poster' => (int) ($row['poster_assets'] ?? 0),
            'banner' => (int) ($row['banner_assets'] ?? 0),
            'gallery' => (int) ($row['gallery_assets'] ?? 0),
            'draft' => (int) ($row['draft_assets'] ?? 0),
            'active' => (int) ($row['active_assets'] ?? 0),
            'archived' => (int) ($row['archived_assets'] ?? 0),
        ];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT mi.*, m.title AS movie_title, m.slug AS movie_slug
             FROM movie_images mi
             INNER JOIN movies m ON m.id = mi.movie_id
             WHERE mi.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function listActiveAssetsForMovie(int $movieId, ?string $assetType = null): array
    {
        $sql = "
            SELECT
                id,
                movie_id,
                asset_type,
                image_url,
                alt_text,
                sort_order,
                is_primary,
                status,
                created_at,
                updated_at
            FROM movie_images
            WHERE movie_id = :movie_id
              AND status = 'active'
        ";
        $params = ['movie_id' => $movieId];

        if ($assetType !== null) {
            $sql .= ' AND asset_type = :asset_type';
            $params['asset_type'] = $assetType;
        }

        $sql .= ' ORDER BY is_primary DESC, sort_order ASC, id ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO movie_images (
                movie_id, asset_type, image_url, alt_text, sort_order, is_primary, status, created_at, updated_at
            ) VALUES (
                :movie_id, :asset_type, :image_url, :alt_text, :sort_order, :is_primary, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )'
        );
        $stmt->execute([
            'movie_id' => $data['movie_id'],
            'asset_type' => $data['asset_type'],
            'image_url' => $data['image_url'],
            'alt_text' => $data['alt_text'],
            'sort_order' => $data['sort_order'],
            'is_primary' => $data['is_primary'],
            'status' => $data['status'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE movie_images
             SET movie_id = :movie_id,
                 asset_type = :asset_type,
                 image_url = :image_url,
                 alt_text = :alt_text,
                 sort_order = :sort_order,
                 is_primary = :is_primary,
                 status = :status,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'movie_id' => $data['movie_id'],
            'asset_type' => $data['asset_type'],
            'image_url' => $data['image_url'],
            'alt_text' => $data['alt_text'],
            'sort_order' => $data['sort_order'],
            'is_primary' => $data['is_primary'],
            'status' => $data['status'],
        ]);
    }

    public function archive(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE movie_images
             SET status = :status, is_primary = 0, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'status' => 'archived',
        ]);
    }

    public function clearPrimaryFlagForMovie(int $movieId, string $assetType, ?int $excludeId = null): void
    {
        $sql = '
            UPDATE movie_images
            SET is_primary = 0, updated_at = CURRENT_TIMESTAMP
            WHERE movie_id = :movie_id
              AND asset_type = :asset_type
        ';
        $params = [
            'movie_id' => $movieId,
            'asset_type' => $assetType,
        ];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    private function buildFilterParts(array $filters): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(m.title LIKE :search OR mi.alt_text LIKE :search OR mi.image_url LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['movie_id'])) {
            $conditions[] = 'mi.movie_id = :movie_id';
            $params['movie_id'] = (int) $filters['movie_id'];
        }
        if (!empty($filters['asset_type'])) {
            $conditions[] = 'mi.asset_type = :asset_type';
            $params['asset_type'] = $filters['asset_type'];
        }
        if (!empty($filters['status'])) {
            $conditions[] = 'mi.status = :status';
            $params['status'] = $filters['status'];
        }

        return [
            'where' => $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '',
            'params' => $params,
        ];
    }
}
