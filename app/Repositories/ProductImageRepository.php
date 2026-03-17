<?php

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\Concerns\PaginatesQueries;
use PDO;

class ProductImageRepository
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
                pi.*,
                p.name AS product_name,
                p.slug AS product_slug,
                p.status AS product_status
            FROM product_images pi
            INNER JOIN products p ON p.id = pi.product_id
            {$where}
            ORDER BY pi.updated_at DESC, pi.id DESC
        ";
        $countSql = "
            SELECT COUNT(*)
            FROM product_images pi
            INNER JOIN products p ON p.id = pi.product_id
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
                SUM(CASE WHEN pi.asset_type = 'thumbnail' THEN 1 ELSE 0 END) AS thumbnail_assets,
                SUM(CASE WHEN pi.asset_type = 'gallery' THEN 1 ELSE 0 END) AS gallery_assets,
                SUM(CASE WHEN pi.asset_type = 'banner' THEN 1 ELSE 0 END) AS banner_assets,
                SUM(CASE WHEN pi.asset_type = 'lifestyle' THEN 1 ELSE 0 END) AS lifestyle_assets,
                SUM(CASE WHEN pi.status = 'draft' THEN 1 ELSE 0 END) AS draft_assets,
                SUM(CASE WHEN pi.status = 'active' THEN 1 ELSE 0 END) AS active_assets,
                SUM(CASE WHEN pi.status = 'archived' THEN 1 ELSE 0 END) AS archived_assets,
                SUM(CASE WHEN pi.is_primary = 1 THEN 1 ELSE 0 END) AS primary_assets
            FROM product_images pi
            INNER JOIN products p ON p.id = pi.product_id
            {$where}
        ");
        $this->bindValues($stmt, $params);
        $stmt->execute();

        $row = $stmt->fetch() ?: [];

        return [
            'total' => (int) ($row['total_assets'] ?? 0),
            'thumbnail' => (int) ($row['thumbnail_assets'] ?? 0),
            'gallery' => (int) ($row['gallery_assets'] ?? 0),
            'banner' => (int) ($row['banner_assets'] ?? 0),
            'lifestyle' => (int) ($row['lifestyle_assets'] ?? 0),
            'draft' => (int) ($row['draft_assets'] ?? 0),
            'active' => (int) ($row['active_assets'] ?? 0),
            'archived' => (int) ($row['archived_assets'] ?? 0),
            'primary' => (int) ($row['primary_assets'] ?? 0),
        ];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT pi.*, p.name AS product_name, p.slug AS product_slug, p.status AS product_status
             FROM product_images pi
             INNER JOIN products p ON p.id = pi.product_id
             WHERE pi.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO product_images (
                product_id, asset_type, image_url, alt_text, sort_order, is_primary, status, created_at, updated_at
            ) VALUES (
                :product_id, :asset_type, :image_url, :alt_text, :sort_order, :is_primary, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )'
        );
        $stmt->execute([
            'product_id' => $data['product_id'],
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
            'UPDATE product_images
             SET product_id = :product_id,
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
            'product_id' => $data['product_id'],
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
            'UPDATE product_images
             SET status = :status,
                 is_primary = 0,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'status' => 'archived',
        ]);
    }

    public function clearPrimaryFlagForProduct(int $productId, string $assetType, ?int $excludeId = null): void
    {
        $sql = '
            UPDATE product_images
            SET is_primary = 0, updated_at = CURRENT_TIMESTAMP
            WHERE product_id = :product_id
              AND asset_type = :asset_type
        ';
        $params = [
            'product_id' => $productId,
            'asset_type' => $assetType,
        ];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    public function listActiveAssetsForProduct(int $productId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                product_id,
                asset_type,
                image_url,
                alt_text,
                sort_order,
                is_primary,
                status,
                created_at,
                updated_at
            FROM product_images
            WHERE product_id = :product_id
              AND status = 'active'
            ORDER BY
                CASE asset_type
                    WHEN 'thumbnail' THEN 0
                    WHEN 'gallery' THEN 1
                    WHEN 'banner' THEN 2
                    WHEN 'lifestyle' THEN 3
                    ELSE 4
            END,
                is_primary DESC,
                sort_order ASC,
                id ASC
        ");
        $stmt->execute(['product_id' => $productId]);

        return $stmt->fetchAll() ?: [];
    }

    public function listNonArchivedAssetsForProduct(int $productId, array $assetTypes = []): array
    {
        $conditions = [
            'product_id = :product_id',
            "status <> 'archived'",
        ];
        $params = ['product_id' => $productId];

        if ($assetTypes !== []) {
            $placeholders = [];
            foreach (array_values($assetTypes) as $index => $assetType) {
                $placeholder = ':asset_type_' . $index;
                $placeholders[] = $placeholder;
                $params['asset_type_' . $index] = $assetType;
            }

            $conditions[] = 'asset_type IN (' . implode(', ', $placeholders) . ')';
        }

        $whereSql = ' WHERE ' . implode(' AND ', $conditions);
        $stmt = $this->db->prepare("
            SELECT
                id,
                product_id,
                asset_type,
                image_url,
                alt_text,
                sort_order,
                is_primary,
                status,
                created_at,
                updated_at
            FROM product_images
            {$whereSql}
            ORDER BY
                CASE asset_type
                    WHEN 'thumbnail' THEN 0
                    WHEN 'gallery' THEN 1
                    WHEN 'banner' THEN 2
                    WHEN 'lifestyle' THEN 3
                    ELSE 4
                END,
                is_primary DESC,
                sort_order ASC,
                id ASC
        ");
        $this->bindValues($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    private function buildFilterParts(array $filters): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(p.name LIKE :search OR pi.alt_text LIKE :search OR pi.image_url LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['product_id'])) {
            $conditions[] = 'pi.product_id = :product_id';
            $params['product_id'] = (int) $filters['product_id'];
        }
        if (!empty($filters['asset_type'])) {
            $conditions[] = 'pi.asset_type = :asset_type';
            $params['asset_type'] = $filters['asset_type'];
        }
        if (!empty($filters['status'])) {
            $conditions[] = 'pi.status = :status';
            $params['status'] = $filters['status'];
        }

        return [
            'where' => $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '',
            'params' => $params,
        ];
    }
}
