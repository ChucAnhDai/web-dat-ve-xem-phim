<?php

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\Concerns\PaginatesQueries;
use PDO;

class ProductRepository
{
    use PaginatesQueries;

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function paginate(array $filters, int $lowStockThreshold): array
    {
        $threshold = max(1, $lowStockThreshold);
        ['where' => $where, 'params' => $params, 'joins' => $joins] = $this->buildFilterParts($filters, $threshold);

        $selectSql = "
            SELECT
                p.*,
                c.name AS category_name,
                c.slug AS category_slug,
                c.visibility AS category_visibility,
                c.status AS category_status,
                d.brand,
                d.weight,
                d.origin,
                d.description AS detail_description,
                d.attributes_json,
                {$this->adminPrimaryImageUrlSql('p.id')} AS primary_image_url,
                {$this->adminPrimaryImageAltSql('p.id', 'p.name')} AS primary_image_alt,
                CASE
                    WHEN p.status = 'archived' THEN 'archived'
                    WHEN p.track_inventory = 0 THEN 'in_stock'
                    WHEN p.stock <= 0 THEN 'out_of_stock'
                    WHEN p.stock <= {$threshold} THEN 'low_stock'
                    ELSE 'in_stock'
                END AS stock_state
            FROM products p
            {$joins}
            {$where}
            ORDER BY p.sort_order ASC, p.updated_at DESC, p.name ASC
        ";
        $countSql = "
            SELECT COUNT(*)
            FROM products p
            {$joins}
            {$where}
        ";

        return $this->paginateQuery($this->db, $selectSql, $countSql, $params, $filters['page'], $filters['per_page']);
    }

    public function summarize(array $filters, int $lowStockThreshold): array
    {
        $threshold = max(1, $lowStockThreshold);
        ['where' => $where, 'params' => $params, 'joins' => $joins] = $this->buildFilterParts($filters, $threshold);

        $stmt = $this->db->prepare("
            SELECT
                COUNT(DISTINCT p.id) AS total_products,
                COUNT(DISTINCT CASE WHEN p.status = 'draft' THEN p.id END) AS draft_products,
                COUNT(DISTINCT CASE WHEN p.status = 'active' THEN p.id END) AS active_products,
                COUNT(DISTINCT CASE WHEN p.status = 'inactive' THEN p.id END) AS inactive_products,
                COUNT(DISTINCT CASE WHEN p.status = 'archived' THEN p.id END) AS archived_products,
                COUNT(DISTINCT CASE
                    WHEN p.status <> 'archived'
                     AND (p.track_inventory = 0 OR p.stock > {$threshold})
                    THEN p.id
                END) AS in_stock_products,
                COUNT(DISTINCT CASE
                    WHEN p.status <> 'archived'
                     AND p.track_inventory = 1
                     AND p.stock > 0
                     AND p.stock <= {$threshold}
                    THEN p.id
                END) AS low_stock_products,
                COUNT(DISTINCT CASE
                    WHEN p.status <> 'archived'
                     AND p.track_inventory = 1
                     AND p.stock <= 0
                    THEN p.id
                END) AS out_of_stock_products
            FROM products p
            {$joins}
            {$where}
        ");
        $this->bindValues($stmt, $params);
        $stmt->execute();

        $row = $stmt->fetch() ?: [];

        return [
            'total' => (int) ($row['total_products'] ?? 0),
            'draft' => (int) ($row['draft_products'] ?? 0),
            'active' => (int) ($row['active_products'] ?? 0),
            'inactive' => (int) ($row['inactive_products'] ?? 0),
            'archived' => (int) ($row['archived_products'] ?? 0),
            'in_stock' => (int) ($row['in_stock_products'] ?? 0),
            'low_stock' => (int) ($row['low_stock_products'] ?? 0),
            'out_of_stock' => (int) ($row['out_of_stock_products'] ?? 0),
        ];
    }

    public function paginatePublicCatalog(array $filters, int $lowStockThreshold): array
    {
        $threshold = max(1, $lowStockThreshold);
        ['where' => $where, 'params' => $params, 'joins' => $joins] = $this->buildPublicCatalogFilterParts($filters, $threshold);
        $orderBy = $this->resolvePublicCatalogOrderBy($filters['sort'] ?? 'featured');

        $selectSql = "
            SELECT
                {$this->publicProjectionSql($threshold)}
            FROM products p
            {$joins}
            {$where}
            ORDER BY {$orderBy}
        ";
        $countSql = "
            SELECT COUNT(*)
            FROM products p
            {$joins}
            {$where}
        ";

        return $this->paginateQuery($this->db, $selectSql, $countSql, $params, $filters['page'], $filters['per_page']);
    }

    public function summarizePublicCatalog(array $filters, int $lowStockThreshold): array
    {
        $threshold = max(1, $lowStockThreshold);
        ['where' => $where, 'params' => $params, 'joins' => $joins] = $this->buildPublicCatalogFilterParts($filters, $threshold);

        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total_products,
                COUNT(CASE WHEN p.visibility = 'featured' THEN 1 END) AS featured_products,
                COUNT(CASE
                    WHEN p.track_inventory = 0 OR p.stock > {$threshold}
                    THEN 1
                END) AS in_stock_products,
                COUNT(CASE
                    WHEN p.track_inventory = 1
                     AND p.stock > 0
                     AND p.stock <= {$threshold}
                    THEN 1
                END) AS low_stock_products,
                COUNT(CASE
                    WHEN p.track_inventory = 1
                     AND p.stock <= 0
                    THEN 1
                END) AS out_of_stock_products
            FROM products p
            {$joins}
            {$where}
        ");
        $this->bindValues($stmt, $params);
        $stmt->execute();

        $row = $stmt->fetch() ?: [];

        return [
            'total' => (int) ($row['total_products'] ?? 0),
            'featured' => (int) ($row['featured_products'] ?? 0),
            'in_stock' => (int) ($row['in_stock_products'] ?? 0),
            'low_stock' => (int) ($row['low_stock_products'] ?? 0),
            'out_of_stock' => (int) ($row['out_of_stock_products'] ?? 0),
        ];
    }

    public function findById(int $id, int $lowStockThreshold): ?array
    {
        $threshold = max(1, $lowStockThreshold);
        $stmt = $this->db->prepare("
            SELECT
                p.*,
                c.name AS category_name,
                c.slug AS category_slug,
                c.visibility AS category_visibility,
                c.status AS category_status,
                d.brand,
                d.weight,
                d.origin,
                d.description AS detail_description,
                d.attributes_json,
                {$this->adminPrimaryImageUrlSql('p.id')} AS primary_image_url,
                {$this->adminPrimaryImageAltSql('p.id', 'p.name')} AS primary_image_alt,
                CASE
                    WHEN p.status = 'archived' THEN 'archived'
                    WHEN p.track_inventory = 0 THEN 'in_stock'
                    WHEN p.stock <= 0 THEN 'out_of_stock'
                    WHEN p.stock <= {$threshold} THEN 'low_stock'
                    ELSE 'in_stock'
                END AS stock_state
            FROM products p
            LEFT JOIN product_categories c ON c.id = p.category_id
            LEFT JOIN product_details d ON d.product_id = p.id
            WHERE p.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findBySlug(string $slug, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT * FROM products WHERE slug = :slug';
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

    public function findPublicDetailBySlug(string $slug, int $lowStockThreshold): ?array
    {
        $threshold = max(1, $lowStockThreshold);
        $stmt = $this->db->prepare("
            SELECT
                {$this->publicProjectionSql($threshold)}
            FROM products p
            INNER JOIN product_categories c ON c.id = p.category_id
            LEFT JOIN product_details d ON d.product_id = p.id
            WHERE p.slug = :slug
              AND p.status = 'active'
              AND p.visibility <> 'hidden'
              AND c.status = 'active'
              AND c.visibility <> 'hidden'
            LIMIT 1
        ");
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function listPublicRelatedProducts(int $productId, ?int $categoryId, int $limit, int $lowStockThreshold): array
    {
        $threshold = max(1, $lowStockThreshold);
        $safeLimit = max(1, min($limit, 8));
        $conditions = [
            "p.status = 'active'",
            "p.visibility <> 'hidden'",
            "c.status = 'active'",
            "c.visibility <> 'hidden'",
            'p.id <> :product_id',
        ];
        $params = ['product_id' => $productId];

        if ($categoryId !== null && $categoryId > 0) {
            $conditions[] = 'p.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        $where = ' WHERE ' . implode(' AND ', $conditions);
        $stmt = $this->db->prepare("
            SELECT
                {$this->publicProjectionSql($threshold)}
            FROM products p
            INNER JOIN product_categories c ON c.id = p.category_id
            LEFT JOIN product_details d ON d.product_id = p.id
            {$where}
            ORDER BY
                CASE WHEN p.visibility = 'featured' THEN 0 ELSE 1 END,
                p.sort_order ASC,
                COALESCE(p.updated_at, p.created_at) DESC,
                p.name ASC
            LIMIT {$safeLimit}
        ");
        $this->bindValues($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function listPublicCartProductsByIds(array $productIds, int $lowStockThreshold): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds), static function (int $id): bool {
            return $id > 0;
        })));
        if ($ids === []) {
            return [];
        }

        $threshold = max(1, $lowStockThreshold);
        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $productId) {
            $placeholder = ':product_id_' . $index;
            $placeholders[] = $placeholder;
            $params['product_id_' . $index] = $productId;
        }

        $inList = implode(', ', $placeholders);
        $stmt = $this->db->prepare("
            SELECT
                {$this->publicProjectionSql($threshold)}
            FROM products p
            INNER JOIN product_categories c ON c.id = p.category_id
            LEFT JOIN product_details d ON d.product_id = p.id
            WHERE p.id IN ({$inList})
              AND p.status = 'active'
              AND p.visibility <> 'hidden'
              AND c.status = 'active'
              AND c.visibility <> 'hidden'
        ");
        $this->bindValues($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function findBySku(string $sku, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT * FROM products WHERE sku = :sku';
        $params = ['sku' => $sku];

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
            'INSERT INTO products (
                category_id, slug, sku, name, short_description, description, price, compare_at_price, stock, currency,
                track_inventory, status, visibility, sort_order, created_at, updated_at
            ) VALUES (
                :category_id, :slug, :sku, :name, :short_description, :description, :price, :compare_at_price, :stock, :currency,
                :track_inventory, :status, :visibility, :sort_order, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )'
        );
        $stmt->execute([
            'category_id' => $data['category_id'],
            'slug' => $data['slug'],
            'sku' => $data['sku'],
            'name' => $data['name'],
            'short_description' => $data['short_description'],
            'description' => $data['description'],
            'price' => $data['price'],
            'compare_at_price' => $data['compare_at_price'],
            'stock' => $data['stock'],
            'currency' => $data['currency'],
            'track_inventory' => $data['track_inventory'],
            'status' => $data['status'],
            'visibility' => $data['visibility'],
            'sort_order' => $data['sort_order'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE products
             SET category_id = :category_id,
                 slug = :slug,
                 sku = :sku,
                 name = :name,
                 short_description = :short_description,
                 description = :description,
                 price = :price,
                 compare_at_price = :compare_at_price,
                 stock = :stock,
                 currency = :currency,
                 track_inventory = :track_inventory,
                 status = :status,
                 visibility = :visibility,
                 sort_order = :sort_order,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'category_id' => $data['category_id'],
            'slug' => $data['slug'],
            'sku' => $data['sku'],
            'name' => $data['name'],
            'short_description' => $data['short_description'],
            'description' => $data['description'],
            'price' => $data['price'],
            'compare_at_price' => $data['compare_at_price'],
            'stock' => $data['stock'],
            'currency' => $data['currency'],
            'track_inventory' => $data['track_inventory'],
            'status' => $data['status'],
            'visibility' => $data['visibility'],
            'sort_order' => $data['sort_order'],
        ]);
    }

    public function archive(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE products
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

    private function buildFilterParts(array $filters, int $lowStockThreshold): array
    {
        $conditions = [];
        $params = [];
        $joins = '
            LEFT JOIN product_categories c ON c.id = p.category_id
            LEFT JOIN product_details d ON d.product_id = p.id
        ';

        if (!empty($filters['search'])) {
            $conditions[] = '(p.name LIKE :search OR p.slug LIKE :search OR p.sku LIKE :search OR c.name LIKE :search OR d.brand LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['category_id'])) {
            $conditions[] = 'p.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }
        if (!empty($filters['status'])) {
            $conditions[] = 'p.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['visibility'])) {
            $conditions[] = 'p.visibility = :visibility';
            $params['visibility'] = $filters['visibility'];
        }
        if (!empty($filters['stock_state'])) {
            switch ($filters['stock_state']) {
                case 'in_stock':
                    $conditions[] = "p.status <> 'archived' AND (p.track_inventory = 0 OR p.stock > {$lowStockThreshold})";
                    break;
                case 'low_stock':
                    $conditions[] = "p.status <> 'archived' AND p.track_inventory = 1 AND p.stock > 0 AND p.stock <= {$lowStockThreshold}";
                    break;
                case 'out_of_stock':
                    $conditions[] = "p.status <> 'archived' AND p.track_inventory = 1 AND p.stock <= 0";
                    break;
            }
        }

        return [
            'where' => $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '',
            'params' => $params,
            'joins' => $joins,
        ];
    }

    private function buildPublicCatalogFilterParts(array $filters, int $lowStockThreshold): array
    {
        $conditions = [
            "p.status = 'active'",
            "p.visibility <> 'hidden'",
            "c.status = 'active'",
            "c.visibility <> 'hidden'",
        ];
        $params = [];
        $joins = '
            INNER JOIN product_categories c ON c.id = p.category_id
            LEFT JOIN product_details d ON d.product_id = p.id
        ';

        if (!empty($filters['search'])) {
            $conditions[] = '(p.name LIKE :search OR p.short_description LIKE :search OR p.description LIKE :search OR c.name LIKE :search OR d.brand LIKE :search OR d.origin LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['category_slug'])) {
            $conditions[] = 'c.slug = :category_slug';
            $params['category_slug'] = $filters['category_slug'];
        }
        if (!empty($filters['featured_only'])) {
            $conditions[] = "p.visibility = 'featured'";
        }
        if ($filters['min_price'] !== null) {
            $conditions[] = 'p.price >= :min_price';
            $params['min_price'] = (float) $filters['min_price'];
        }
        if ($filters['max_price'] !== null) {
            $conditions[] = 'p.price <= :max_price';
            $params['max_price'] = (float) $filters['max_price'];
        }
        if (!empty($filters['stock_state'])) {
            switch ($filters['stock_state']) {
                case 'in_stock':
                    $conditions[] = "(p.track_inventory = 0 OR p.stock > {$lowStockThreshold})";
                    break;
                case 'low_stock':
                    $conditions[] = "p.track_inventory = 1 AND p.stock > 0 AND p.stock <= {$lowStockThreshold}";
                    break;
                case 'out_of_stock':
                    $conditions[] = "p.track_inventory = 1 AND p.stock <= 0";
                    break;
            }
        }

        return [
            'where' => ' WHERE ' . implode(' AND ', $conditions),
            'params' => $params,
            'joins' => $joins,
        ];
    }

    private function resolvePublicCatalogOrderBy(string $sort): string
    {
        switch ($sort) {
            case 'price_asc':
                return 'p.price ASC, p.sort_order ASC, p.name ASC';
            case 'price_desc':
                return 'p.price DESC, p.sort_order ASC, p.name ASC';
            case 'name_asc':
                return 'p.name ASC, p.sort_order ASC';
            case 'name_desc':
                return 'p.name DESC, p.sort_order ASC';
            case 'newest':
                return 'COALESCE(p.updated_at, p.created_at) DESC, p.sort_order ASC, p.name ASC';
            case 'oldest':
                return 'COALESCE(p.created_at, p.updated_at) ASC, p.sort_order ASC, p.name ASC';
            case 'featured':
            default:
                return "CASE WHEN p.visibility = 'featured' THEN 0 ELSE 1 END, p.sort_order ASC, COALESCE(p.updated_at, p.created_at) DESC, p.name ASC";
        }
    }

    private function publicProjectionSql(int $lowStockThreshold): string
    {
        $threshold = max(1, $lowStockThreshold);

        return "
            p.id,
            p.category_id,
            c.name AS category_name,
            c.slug AS category_slug,
            c.visibility AS category_visibility,
            p.slug,
            p.sku,
            p.name,
            p.short_description,
            p.description,
            p.price,
            p.compare_at_price,
            p.stock,
            p.currency,
            p.track_inventory,
            p.status,
            p.visibility,
            p.sort_order,
            p.created_at,
            p.updated_at,
            d.brand,
            d.weight,
            d.origin,
            d.description AS detail_description,
            d.attributes_json,
            (
                SELECT pi.image_url
                FROM product_images pi
                WHERE pi.product_id = p.id
                  AND pi.status = 'active'
                ORDER BY
                    CASE pi.asset_type
                        WHEN 'thumbnail' THEN 0
                        WHEN 'banner' THEN 1
                        WHEN 'gallery' THEN 2
                        WHEN 'lifestyle' THEN 3
                        ELSE 4
                    END,
                    pi.is_primary DESC,
                    pi.sort_order ASC,
                    pi.id ASC
                LIMIT 1
            ) AS primary_image_url,
            COALESCE((
                SELECT COALESCE(pi.alt_text, p.name)
                FROM product_images pi
                WHERE pi.product_id = p.id
                  AND pi.status = 'active'
                ORDER BY
                    CASE pi.asset_type
                        WHEN 'thumbnail' THEN 0
                        WHEN 'banner' THEN 1
                        WHEN 'gallery' THEN 2
                        WHEN 'lifestyle' THEN 3
                        ELSE 4
                    END,
                    pi.is_primary DESC,
                    pi.sort_order ASC,
                    pi.id ASC
                LIMIT 1
            ), p.name) AS primary_image_alt,
            CASE
                WHEN p.track_inventory = 0 THEN 'in_stock'
                WHEN p.stock <= 0 THEN 'out_of_stock'
                WHEN p.stock <= {$threshold} THEN 'low_stock'
                ELSE 'in_stock'
            END AS stock_state
        ";
    }

    private function adminPrimaryImageUrlSql(string $productIdExpression): string
    {
        return "
            (
                SELECT pi.image_url
                FROM product_images pi
                WHERE pi.product_id = {$productIdExpression}
                  AND pi.status <> 'archived'
                ORDER BY
                    CASE pi.asset_type
                        WHEN 'thumbnail' THEN 0
                        WHEN 'banner' THEN 1
                        WHEN 'gallery' THEN 2
                        WHEN 'lifestyle' THEN 3
                        ELSE 4
                    END,
                    pi.is_primary DESC,
                    pi.sort_order ASC,
                    pi.id ASC
                LIMIT 1
            )
        ";
    }

    private function adminPrimaryImageAltSql(string $productIdExpression, string $fallbackNameExpression): string
    {
        return "
            COALESCE((
                SELECT COALESCE(pi.alt_text, {$fallbackNameExpression})
                FROM product_images pi
                WHERE pi.product_id = {$productIdExpression}
                  AND pi.status <> 'archived'
                ORDER BY
                    CASE pi.asset_type
                        WHEN 'thumbnail' THEN 0
                        WHEN 'banner' THEN 1
                        WHEN 'gallery' THEN 2
                        WHEN 'lifestyle' THEN 3
                        ELSE 4
                    END,
                    pi.is_primary DESC,
                    pi.sort_order ASC,
                    pi.id ASC
                LIMIT 1
            ), {$fallbackNameExpression})
        ";
    }
}
