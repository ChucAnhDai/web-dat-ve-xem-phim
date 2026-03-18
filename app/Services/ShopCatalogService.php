<?php

namespace App\Services;

use App\Core\Logger;
use App\Repositories\ProductCategoryRepository;
use App\Repositories\ProductImageRepository;
use App\Repositories\ProductRepository;
use App\Support\AssetUrlResolver;
use App\Validators\ShopCatalogValidator;
use Throwable;

class ShopCatalogService
{
    private ProductRepository $products;
    private ProductCategoryRepository $categories;
    private ProductImageRepository $images;
    private ShopCatalogValidator $validator;
    private Logger $logger;
    private AssetUrlResolver $assetUrlResolver;
    private array $config;

    public function __construct(
        ?ProductRepository $products = null,
        ?ProductCategoryRepository $categories = null,
        ?ProductImageRepository $images = null,
        ?ShopCatalogValidator $validator = null,
        ?Logger $logger = null,
        ?array $config = null,
        ?AssetUrlResolver $assetUrlResolver = null
    ) {
        $this->config = $config ?? require dirname(__DIR__, 2) . '/config/shop.php';
        $this->products = $products ?? new ProductRepository();
        $this->categories = $categories ?? new ProductCategoryRepository();
        $this->images = $images ?? new ProductImageRepository();
        $this->validator = $validator ?? new ShopCatalogValidator($this->config);
        $this->logger = $logger ?? new Logger();
        $this->assetUrlResolver = $assetUrlResolver ?? new AssetUrlResolver((string) (getenv('APP_URL') ?: ''));
    }

    public function listCategories(array $filters): array
    {
        $startedAt = microtime(true);
        $normalizedFilters = $this->validator->normalizeCategoryFilters($filters);

        try {
            $items = $this->categories->listPublicOptions($normalizedFilters);
        } catch (Throwable $exception) {
            $this->logger->error('Public shop categories load failed', [
                'filters' => $normalizedFilters,
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to load shop categories.']], 500);
        }

        return $this->success([
            'items' => array_map([$this, 'mapCategory'], $items),
            'meta' => [
                'total' => count($items),
            ],
            'source' => [
                'provider' => 'local',
                'mode' => 'shop-public',
            ],
        ]);
    }

    public function listProducts(array $filters): array
    {
        $startedAt = microtime(true);
        $normalizedFilters = $this->validator->normalizeProductFilters($filters);

        try {
            $page = $this->products->paginatePublicCatalog($normalizedFilters, $this->lowStockThreshold());
            $summary = $this->products->summarizePublicCatalog($normalizedFilters, $this->lowStockThreshold());
        } catch (Throwable $exception) {
            $this->logger->error('Public shop catalog load failed', [
                'filters' => $normalizedFilters,
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to load shop catalog.']], 500);
        }

        return $this->success([
            'items' => array_map([$this, 'mapCatalogProduct'], $page['items']),
            'meta' => $this->paginationMeta($page),
            'summary' => $this->buildCatalogSummary($summary),
            'filters' => [
                'search' => $normalizedFilters['search'],
                'category_slug' => $normalizedFilters['category_slug'],
                'sort' => $normalizedFilters['sort'],
                'featured_only' => (int) $normalizedFilters['featured_only'],
                'min_price' => $normalizedFilters['min_price'],
                'max_price' => $normalizedFilters['max_price'],
                'stock_state' => $normalizedFilters['stock_state'],
            ],
            'source' => [
                'provider' => 'local',
                'mode' => 'shop-public',
            ],
        ]);
    }

    public function getProductDetail(string $slug): array
    {
        $startedAt = microtime(true);
        $normalizedSlug = $this->validator->normalizeProductSlug($slug);
        if ($normalizedSlug === '') {
            return $this->error(['product' => ['Product not found.']], 404);
        }

        try {
            $product = $this->products->findPublicDetailBySlug($normalizedSlug, $this->lowStockThreshold());
            if ($product === null) {
                return $this->error(['product' => ['Product not found.']], 404);
            }

            $gallery = $this->images->listActiveAssetsForProduct((int) ($product['id'] ?? 0));
            $related = $this->products->listPublicRelatedProducts(
                (int) ($product['id'] ?? 0),
                (int) ($product['category_id'] ?? 0),
                4,
                $this->lowStockThreshold()
            );

            if (empty($related)) {
                $related = $this->products->listPublicRelatedProducts(
                    (int) ($product['id'] ?? 0),
                    null,
                    4,
                    $this->lowStockThreshold()
                );
            }
        } catch (Throwable $exception) {
            $this->logger->error('Public shop product detail load failed', [
                'slug' => $normalizedSlug,
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to load product details.']], 500);
        }

        return $this->success([
            'product' => $this->mapDetailProduct($product),
            'gallery' => $this->mapGallery($gallery, $product),
            'related_products' => array_map([$this, 'mapCatalogProduct'], $related),
            'source' => [
                'provider' => 'local',
                'mode' => 'shop-public',
            ],
        ]);
    }

    private function mapCategory(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => $row['name'] ?? null,
            'slug' => $row['slug'] ?? null,
            'description' => $row['description'] ?? null,
            'display_order' => (int) ($row['display_order'] ?? 0),
            'visibility' => $row['visibility'] ?? null,
            'product_count' => (int) ($row['product_count'] ?? 0),
            'is_featured' => ($row['visibility'] ?? '') === 'featured',
        ];
    }

    private function mapCatalogProduct(array $row): array
    {
        $price = round((float) ($row['price'] ?? 0), 2);
        $compareAtPrice = isset($row['compare_at_price']) && $row['compare_at_price'] !== null
            ? round((float) $row['compare_at_price'], 2)
            : null;

        return [
            'id' => (int) ($row['id'] ?? 0),
            'category_id' => (int) ($row['category_id'] ?? 0),
            'category_name' => $row['category_name'] ?? null,
            'category_slug' => $row['category_slug'] ?? null,
            'slug' => $row['slug'] ?? null,
            'sku' => $row['sku'] ?? null,
            'name' => $row['name'] ?? null,
            'summary' => $row['short_description'] ?? ($row['detail_description'] ?? $row['description'] ?? null),
            'short_description' => $row['short_description'] ?? null,
            'price' => $price,
            'compare_at_price' => $compareAtPrice,
            'currency' => $row['currency'] ?? ($this->config['currency'] ?? 'VND'),
            'track_inventory' => (int) ($row['track_inventory'] ?? 1),
            'max_quantity_available' => $this->maxQuantityAvailable($row),
            'stock_state' => $row['stock_state'] ?? 'in_stock',
            'is_available' => ($row['stock_state'] ?? 'in_stock') !== 'out_of_stock',
            'visibility' => $row['visibility'] ?? 'standard',
            'brand' => $row['brand'] ?? null,
            'primary_image_url' => $this->assetUrlResolver->resolve($row['primary_image_url'] ?? null),
            'primary_image_alt' => $row['primary_image_alt'] ?? ($row['name'] ?? 'Product'),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function mapDetailProduct(array $row): array
    {
        $product = $this->mapCatalogProduct($row);
        $product['description'] = $row['description'] ?? null;
        $product['detail_description'] = $row['detail_description'] ?? null;
        $product['stock'] = (int) ($row['stock'] ?? 0);
        $product['track_inventory'] = (int) ($row['track_inventory'] ?? 1);
        $product['weight'] = $row['weight'] ?? null;
        $product['origin'] = $row['origin'] ?? null;
        $product['attributes'] = $this->decodeAttributes($row['attributes_json'] ?? null);
        $product['highlights'] = $this->buildHighlights($product, $row);

        return $product;
    }

    private function mapGallery(array $rows, array $product): array
    {
        $gallery = array_map(function (array $row) use ($product): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'asset_type' => $row['asset_type'] ?? 'gallery',
                'image_url' => $this->assetUrlResolver->resolve($row['image_url'] ?? null),
                'alt_text' => $row['alt_text'] ?? ($product['name'] ?? 'Product image'),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'is_primary' => (int) ($row['is_primary'] ?? 0),
            ];
        }, $rows);

        if (!empty($gallery)) {
            return $gallery;
        }

        if (!empty($product['primary_image_url'])) {
            return [[
                'id' => (int) ($product['id'] ?? 0),
                'asset_type' => 'thumbnail',
                'image_url' => $product['primary_image_url'],
                'alt_text' => $product['primary_image_alt'] ?? ($product['name'] ?? 'Product image'),
                'sort_order' => 0,
                'is_primary' => 1,
            ]];
        }

        return [];
    }

    private function buildHighlights(array $product, array $row): array
    {
        $highlights = [];

        if (!empty($product['brand'])) {
            $highlights[] = [
                'label' => 'Brand',
                'value' => $product['brand'],
            ];
        }
        if (!empty($row['weight'])) {
            $highlights[] = [
                'label' => 'Weight',
                'value' => $row['weight'],
            ];
        }
        if (!empty($row['origin'])) {
            $highlights[] = [
                'label' => 'Origin',
                'value' => $row['origin'],
            ];
        }

        $attributes = $this->decodeAttributes($row['attributes_json'] ?? null);
        if (is_array($attributes)) {
            foreach ($attributes as $key => $value) {
                if (!is_scalar($value) && !is_array($value)) {
                    continue;
                }

                $highlights[] = [
                    'label' => $this->humanize((string) $key),
                    'value' => is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value,
                ];

                if (count($highlights) >= 6) {
                    break;
                }
            }
        }

        return $highlights;
    }

    private function buildCatalogSummary(array $summary): array
    {
        return [
            'total' => (int) ($summary['total'] ?? 0),
            'featured' => (int) ($summary['featured'] ?? 0),
            'in_stock' => (int) ($summary['in_stock'] ?? 0),
            'low_stock' => (int) ($summary['low_stock'] ?? 0),
            'out_of_stock' => (int) ($summary['out_of_stock'] ?? 0),
        ];
    }

    private function paginationMeta(array $page): array
    {
        $totalPages = (int) ceil(($page['total'] ?: 0) / max(1, $page['per_page']));

        return [
            'total' => (int) ($page['total'] ?? 0),
            'page' => (int) ($page['page'] ?? 1),
            'per_page' => (int) ($page['per_page'] ?? 12),
            'total_pages' => max(1, $totalPages),
        ];
    }

    private function decodeAttributes($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : null;
    }

    private function humanize(string $value): string
    {
        $normalized = trim(str_replace(['_', '-'], ' ', $value));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return ucwords(strtolower($normalized));
    }

    private function lowStockThreshold(): int
    {
        return max(1, (int) ($this->config['products']['low_stock_threshold'] ?? 10));
    }

    private function maxQuantityAvailable(array $row): int
    {
        if ((int) ($row['track_inventory'] ?? 1) === 1) {
            return max(0, (int) ($row['stock'] ?? 0));
        }

        return max(1, (int) ($this->config['cart']['max_quantity_per_item'] ?? 10));
    }

    private function durationMs(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 2);
    }

    private function success($data, int $status = 200): array
    {
        return [
            'status' => $status,
            'data' => $data,
        ];
    }

    private function error(array $errors, int $status): array
    {
        return [
            'status' => $status,
            'errors' => $errors,
        ];
    }
}
