<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\ProductCategoryRepository;
use App\Repositories\ProductDetailRepository;
use App\Repositories\ProductImageRepository;
use App\Repositories\ProductRepository;
use App\Support\AssetUrlResolver;
use App\Validators\ProductManagementValidator;
use PDO;
use Throwable;

class ProductManagementService
{
    private PDO $db;
    private ProductCategoryRepository $categories;
    private ProductRepository $products;
    private ProductDetailRepository $details;
    private ProductImageRepository $images;
    private ProductManagementValidator $validator;
    private Logger $logger;
    private ProductImageUploadService $imageUploader;
    private AssetUrlResolver $assetUrlResolver;
    private array $config;

    public function __construct(
        ?PDO $db = null,
        ?ProductCategoryRepository $categories = null,
        ?ProductManagementValidator $validator = null,
        ?Logger $logger = null,
        ?array $config = null,
        ?ProductRepository $products = null,
        ?ProductDetailRepository $details = null,
        ?ProductImageRepository $images = null,
        ?ProductImageUploadService $imageUploader = null,
        ?AssetUrlResolver $assetUrlResolver = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->categories = $categories ?? new ProductCategoryRepository($this->db);
        $this->config = $config ?? require dirname(__DIR__, 2) . '/config/shop.php';
        $this->products = $products ?? new ProductRepository($this->db);
        $this->details = $details ?? new ProductDetailRepository($this->db);
        $this->images = $images ?? new ProductImageRepository($this->db);
        $this->validator = $validator ?? new ProductManagementValidator($this->config);
        $this->logger = $logger ?? new Logger();
        $this->imageUploader = $imageUploader ?? new ProductImageUploadService($this->config, $this->logger);
        $this->assetUrlResolver = $assetUrlResolver ?? new AssetUrlResolver((string) (getenv('APP_URL') ?: ''));
    }

    public function listCategories(array $filters): array
    {
        $normalizedFilters = $this->validator->normalizeCategoryFilters($filters);
        $page = $this->categories->paginate($normalizedFilters);

        return $this->success([
            'items' => array_map([$this, 'mapCategory'], $page['items']),
            'meta' => $this->paginationMeta($page),
            'summary' => $this->buildCategorySummary($this->categories->summarize($normalizedFilters)),
        ]);
    }

    public function getCategory(int $id): array
    {
        $category = $this->categories->findById($id);
        if (!$category) {
            return $this->error(['category' => ['Product category not found.']], 404);
        }

        return $this->success($this->mapCategory($category));
    }

    public function createCategory(array $payload, ?int $actorId = null): array
    {
        $startedAt = microtime(true);
        $validation = $this->validator->validateCategoryPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $data = $validation['data'];
        if ($this->categories->findBySlug($data['slug'])) {
            return $this->error(['slug' => ['Product category slug already exists.']], 409);
        }

        try {
            $categoryId = $this->transactional(function () use ($data): int {
                return $this->categories->create($data);
            });
        } catch (Throwable $exception) {
            $this->logger->error('Product category creation failed', [
                'actor_id' => $actorId,
                'slug' => $data['slug'],
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to create product category.']], 500);
        }

        $category = $this->categories->findById($categoryId);
        $this->logger->info('Product category created', [
            'actor_id' => $actorId,
            'category_id' => $categoryId,
            'slug' => $data['slug'],
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success($this->mapCategory($category ?: []), 201);
    }

    public function updateCategory(int $id, array $payload, ?int $actorId = null): array
    {
        $startedAt = microtime(true);
        $existing = $this->categories->findById($id);
        if (!$existing) {
            return $this->error(['category' => ['Product category not found.']], 404);
        }

        $validation = $this->validator->validateCategoryPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $data = $validation['data'];
        if ($this->categories->findBySlug($data['slug'], $id)) {
            return $this->error(['slug' => ['Product category slug already exists.']], 409);
        }

        if (($data['status'] ?? '') === 'archived' && $this->categories->hasNonArchivedProducts($id)) {
            $this->logger->info('Product category archival blocked by assigned products', [
                'actor_id' => $actorId,
                'category_id' => $id,
            ]);

            return $this->error(['category' => ['Cannot archive product category while non-archived products are still assigned.']], 409);
        }

        try {
            $this->transactional(function () use ($id, $data): void {
                $this->categories->update($id, $data);
            });
        } catch (Throwable $exception) {
            $this->logger->error('Product category update failed', [
                'actor_id' => $actorId,
                'category_id' => $id,
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to update product category.']], 500);
        }

        $category = $this->categories->findById($id);
        $this->logger->info('Product category updated', [
            'actor_id' => $actorId,
            'category_id' => $id,
            'status' => $data['status'],
            'visibility' => $data['visibility'],
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success($this->mapCategory($category ?: []));
    }

    public function archiveCategory(int $id, ?int $actorId = null): array
    {
        $startedAt = microtime(true);
        $existing = $this->categories->findById($id);
        if (!$existing) {
            return $this->error(['category' => ['Product category not found.']], 404);
        }

        if ($this->categories->hasNonArchivedProducts($id)) {
            $this->logger->info('Product category archive blocked by assigned products', [
                'actor_id' => $actorId,
                'category_id' => $id,
            ]);

            return $this->error(['category' => ['Cannot archive product category while non-archived products are still assigned.']], 409);
        }

        try {
            $this->transactional(function () use ($id): void {
                $this->categories->archive($id);
            });
        } catch (Throwable $exception) {
            $this->logger->error('Product category archive failed', [
                'actor_id' => $actorId,
                'category_id' => $id,
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to archive product category.']], 500);
        }

        $this->logger->info('Product category archived', [
            'actor_id' => $actorId,
            'category_id' => $id,
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success([
            'id' => $id,
            'status' => 'archived',
            'visibility' => 'hidden',
        ]);
    }

    public function listProducts(array $filters): array
    {
        $normalizedFilters = $this->validator->normalizeProductFilters($filters);
        $page = $this->products->paginate($normalizedFilters, $this->lowStockThreshold());

        return $this->success([
            'items' => array_map([$this, 'mapProduct'], $page['items']),
            'meta' => $this->paginationMeta($page),
            'summary' => $this->buildProductSummary($this->products->summarize($normalizedFilters, $this->lowStockThreshold())),
        ]);
    }

    public function getProduct(int $id): array
    {
        $product = $this->products->findById($id, $this->lowStockThreshold());
        if (!$product) {
            return $this->error(['product' => ['Product not found.']], 404);
        }

        $mapped = $this->mapProduct($product);
        $mapped['media'] = $this->mapProductEditorMedia(
            $this->images->listNonArchivedAssetsForProduct($id, $this->validator->productEditorImageAssetTypeValues())
        );

        return $this->success($mapped);
    }

    public function createProduct(array $payload, ?int $actorId = null): array
    {
        $startedAt = microtime(true);
        $validation = $this->validator->validateProductPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $mediaValidation = $this->validator->validateProductMediaPayload($payload['media_manifest'] ?? null, true);
        if (!empty($mediaValidation['errors'])) {
            return $this->error($mediaValidation['errors'], 422);
        }

        $data = $validation['data'];
        $mediaItems = $mediaValidation['data'];
        $categoryError = $this->validateProductCategoryReference((int) ($data['category_id'] ?? 0));
        if ($categoryError !== null) {
            return $categoryError;
        }
        if ($this->products->findBySlug($data['slug'])) {
            return $this->error(['slug' => ['Product slug already exists.']], 409);
        }
        if ($this->products->findBySku($data['sku'])) {
            return $this->error(['sku' => ['Product SKU already exists.']], 409);
        }

        $storedUploadPaths = [];

        try {
            $preparedMedia = $this->prepareProductMediaPayload(
                $mediaItems,
                (array) ($payload['_files'] ?? []),
                $storedUploadPaths,
                (string) ($payload['_app_base_path'] ?? '')
            );

            $productId = $this->transactional(function () use ($data, $preparedMedia): int {
                $productId = $this->products->create($data);
                $this->details->upsertForProduct($productId, $data['details']);
                $this->syncProductEditorMedia($productId, $preparedMedia);

                return $productId;
            });
        } catch (ProductImageUploadException $exception) {
            $this->cleanupStoredUploads($storedUploadPaths);

            return $this->error($exception->errors(), 422);
        } catch (Throwable $exception) {
            $this->cleanupStoredUploads($storedUploadPaths);
            $this->logger->error('Product creation failed', [
                'actor_id' => $actorId,
                'slug' => $data['slug'],
                'sku' => $data['sku'],
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to create product.']], 500);
        }

        $product = $this->loadProductWithMedia($productId);
        $this->logger->info('Product created', [
            'actor_id' => $actorId,
            'product_id' => $productId,
            'slug' => $data['slug'],
            'sku' => $data['sku'],
            'media_count' => count($mediaItems),
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success($product ?: [], 201);
    }

    public function updateProduct(int $id, array $payload, ?int $actorId = null): array
    {
        $startedAt = microtime(true);
        $existing = $this->products->findById($id, $this->lowStockThreshold());
        if (!$existing) {
            return $this->error(['product' => ['Product not found.']], 404);
        }

        $validation = $this->validator->validateProductPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $mediaValidation = $this->validator->validateProductMediaPayload($payload['media_manifest'] ?? null, true);
        if (!empty($mediaValidation['errors'])) {
            return $this->error($mediaValidation['errors'], 422);
        }

        $data = $validation['data'];
        $mediaItems = $mediaValidation['data'];
        $categoryError = $this->validateProductCategoryReference((int) ($data['category_id'] ?? 0));
        if ($categoryError !== null) {
            return $categoryError;
        }
        if ($this->products->findBySlug($data['slug'], $id)) {
            return $this->error(['slug' => ['Product slug already exists.']], 409);
        }
        if ($this->products->findBySku($data['sku'], $id)) {
            return $this->error(['sku' => ['Product SKU already exists.']], 409);
        }

        $storedUploadPaths = [];

        try {
            $preparedMedia = $this->prepareProductMediaPayload(
                $mediaItems,
                (array) ($payload['_files'] ?? []),
                $storedUploadPaths,
                (string) ($payload['_app_base_path'] ?? '')
            );

            $this->transactional(function () use ($id, $data, $preparedMedia): void {
                $this->products->update($id, $data);
                $this->details->upsertForProduct($id, $data['details']);
                $this->syncProductEditorMedia($id, $preparedMedia);
            });
        } catch (ProductImageUploadException $exception) {
            $this->cleanupStoredUploads($storedUploadPaths);

            return $this->error($exception->errors(), 422);
        } catch (Throwable $exception) {
            $this->cleanupStoredUploads($storedUploadPaths);
            $this->logger->error('Product update failed', [
                'actor_id' => $actorId,
                'product_id' => $id,
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to update product.']], 500);
        }

        $product = $this->loadProductWithMedia($id);
        $this->logger->info('Product updated', [
            'actor_id' => $actorId,
            'product_id' => $id,
            'status' => $data['status'],
            'visibility' => $data['visibility'],
            'media_count' => count($mediaItems),
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success($product ?: []);
    }

    public function archiveProduct(int $id, ?int $actorId = null): array
    {
        $startedAt = microtime(true);
        $existing = $this->products->findById($id, $this->lowStockThreshold());
        if (!$existing) {
            return $this->error(['product' => ['Product not found.']], 404);
        }

        try {
            $this->transactional(function () use ($id): void {
                $this->products->archive($id);
            });
        } catch (Throwable $exception) {
            $this->logger->error('Product archive failed', [
                'actor_id' => $actorId,
                'product_id' => $id,
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to archive product.']], 500);
        }

        $this->logger->info('Product archived', [
            'actor_id' => $actorId,
            'product_id' => $id,
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success([
            'id' => $id,
            'status' => 'archived',
            'visibility' => 'hidden',
        ]);
    }

    public function listImages(array $filters): array
    {
        $normalizedFilters = $this->validator->normalizeImageFilters($filters);
        $page = $this->images->paginate($normalizedFilters);

        return $this->success([
            'items' => array_map([$this, 'mapImage'], $page['items']),
            'meta' => $this->paginationMeta($page),
            'summary' => $this->buildImageSummary($this->images->summarize($normalizedFilters)),
        ]);
    }

    public function getImage(int $id): array
    {
        $image = $this->images->findById($id);
        if (!$image) {
            return $this->error(['image' => ['Product image not found.']], 404);
        }

        return $this->success($this->mapImage($image));
    }

    public function createImage(array $payload, ?int $actorId = null): array
    {
        $startedAt = microtime(true);
        $validation = $this->validator->validateImagePayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $data = $validation['data'];
        $ownerError = $this->validateImageOwner((int) ($data['product_id'] ?? 0), (string) ($data['status'] ?? 'draft'));
        if ($ownerError !== null) {
            return $ownerError;
        }

        $storedUploadPaths = [];

        try {
            $persistedImageUrl = $this->resolveImagePayloadPath(
                $data,
                (array) ($payload['_files'] ?? []),
                $storedUploadPaths,
                'image_file',
                (string) ($payload['_app_base_path'] ?? '')
            );

            $imageId = $this->transactional(function () use ($data, $persistedImageUrl): int {
                if ((int) $data['is_primary'] === 1) {
                    $this->images->clearPrimaryFlagForProduct((int) $data['product_id'], $data['asset_type']);
                }

                $data['image_url'] = $persistedImageUrl;

                return $this->images->create($data);
            });
        } catch (ProductImageUploadException $exception) {
            $this->cleanupStoredUploads($storedUploadPaths);

            return $this->error($exception->errors(), 422);
        } catch (Throwable $exception) {
            $this->cleanupStoredUploads($storedUploadPaths);
            $this->logger->error('Product image creation failed', [
                'actor_id' => $actorId,
                'product_id' => $data['product_id'] ?? null,
                'asset_type' => $data['asset_type'] ?? null,
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to create product image.']], 500);
        }

        $image = $this->images->findById($imageId);
        $this->logger->info('Product image created', [
            'actor_id' => $actorId,
            'image_id' => $imageId,
            'product_id' => $data['product_id'],
            'asset_type' => $data['asset_type'],
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success($this->mapImage($image ?: []), 201);
    }

    public function createImagesBatch(array $payload, ?int $actorId = null): array
    {
        $startedAt = microtime(true);
        $validation = $this->validator->validateImageBatchPayload($payload['items_manifest'] ?? null);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $items = $validation['data'];
        foreach ($items as $item) {
            $ownerError = $this->validateImageOwner((int) ($item['product_id'] ?? 0), (string) ($item['status'] ?? 'draft'));
            if ($ownerError !== null) {
                return $ownerError;
            }
        }

        $storedUploadPaths = [];

        try {
            $imageIds = $this->transactional(function () use ($items, $payload, &$storedUploadPaths): array {
                $createdIds = [];

                foreach ($items as $item) {
                    $persistedImageUrl = $this->resolveImagePayloadPath(
                        $item,
                        (array) ($payload['_files'] ?? []),
                        $storedUploadPaths,
                        'items_manifest',
                        (string) ($payload['_app_base_path'] ?? '')
                    );

                    if ((int) ($item['is_primary'] ?? 0) === 1) {
                        $this->images->clearPrimaryFlagForProduct((int) $item['product_id'], (string) $item['asset_type']);
                    }

                    $item['image_url'] = $persistedImageUrl;
                    $createdIds[] = $this->images->create($item);
                }

                return $createdIds;
            });
        } catch (ProductImageUploadException $exception) {
            $this->cleanupStoredUploads($storedUploadPaths);

            return $this->error($exception->errors(), 422);
        } catch (Throwable $exception) {
            $this->cleanupStoredUploads($storedUploadPaths);
            $this->logger->error('Product image batch creation failed', [
                'actor_id' => $actorId,
                'count' => count($items),
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to create product images.']], 500);
        }

        $createdItems = [];
        foreach ($imageIds as $imageId) {
            $image = $this->images->findById((int) $imageId);
            if ($image !== null) {
                $createdItems[] = $this->mapImage($image);
            }
        }

        $this->logger->info('Product image batch created', [
            'actor_id' => $actorId,
            'count' => count($createdItems),
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success([
            'items' => $createdItems,
            'count' => count($createdItems),
        ], 201);
    }

    public function updateImage(int $id, array $payload, ?int $actorId = null): array
    {
        $startedAt = microtime(true);
        $existing = $this->images->findById($id);
        if (!$existing) {
            return $this->error(['image' => ['Product image not found.']], 404);
        }

        $validation = $this->validator->validateImagePayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $data = $validation['data'];
        $ownerError = $this->validateImageOwner((int) ($data['product_id'] ?? 0), (string) ($data['status'] ?? 'draft'));
        if ($ownerError !== null) {
            return $ownerError;
        }

        $storedUploadPaths = [];

        try {
            $persistedImageUrl = $this->resolveImagePayloadPath(
                $data,
                (array) ($payload['_files'] ?? []),
                $storedUploadPaths,
                'image_file',
                (string) ($payload['_app_base_path'] ?? '')
            );

            $this->transactional(function () use ($id, $data, $persistedImageUrl): void {
                if ((int) $data['is_primary'] === 1) {
                    $this->images->clearPrimaryFlagForProduct((int) $data['product_id'], $data['asset_type'], $id);
                }

                $data['image_url'] = $persistedImageUrl;
                $this->images->update($id, $data);
            });
        } catch (ProductImageUploadException $exception) {
            $this->cleanupStoredUploads($storedUploadPaths);

            return $this->error($exception->errors(), 422);
        } catch (Throwable $exception) {
            $this->cleanupStoredUploads($storedUploadPaths);
            $this->logger->error('Product image update failed', [
                'actor_id' => $actorId,
                'image_id' => $id,
                'product_id' => $data['product_id'] ?? null,
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to update product image.']], 500);
        }

        $image = $this->images->findById($id);
        $this->logger->info('Product image updated', [
            'actor_id' => $actorId,
            'image_id' => $id,
            'product_id' => $data['product_id'],
            'asset_type' => $data['asset_type'],
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success($this->mapImage($image ?: []));
    }

    public function archiveImage(int $id, ?int $actorId = null): array
    {
        $startedAt = microtime(true);
        $existing = $this->images->findById($id);
        if (!$existing) {
            return $this->error(['image' => ['Product image not found.']], 404);
        }

        try {
            $this->transactional(function () use ($id): void {
                $this->images->archive($id);
            });
        } catch (Throwable $exception) {
            $this->logger->error('Product image archive failed', [
                'actor_id' => $actorId,
                'image_id' => $id,
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to archive product image.']], 500);
        }

        $this->logger->info('Product image archived', [
            'actor_id' => $actorId,
            'image_id' => $id,
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success([
            'id' => $id,
            'status' => 'archived',
            'is_primary' => 0,
        ]);
    }

    private function transactional(callable $callback)
    {
        $startedTransaction = !$this->db->inTransaction();
        if ($startedTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $result = $callback();
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }

            return $result;
        } catch (Throwable $exception) {
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
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
            'status' => $row['status'] ?? null,
            'product_count' => (int) ($row['product_count'] ?? 0),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function mapProduct(array $row): array
    {
        $primaryImageUrl = $this->normalizeAssetUrl($row['primary_image_url'] ?? null);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'category_id' => (int) ($row['category_id'] ?? 0),
            'category_name' => $row['category_name'] ?? null,
            'category_slug' => $row['category_slug'] ?? null,
            'slug' => $row['slug'] ?? null,
            'sku' => $row['sku'] ?? null,
            'name' => $row['name'] ?? null,
            'short_description' => $row['short_description'] ?? null,
            'description' => $row['description'] ?? null,
            'price' => round((float) ($row['price'] ?? 0), 2),
            'compare_at_price' => isset($row['compare_at_price']) && $row['compare_at_price'] !== null ? round((float) $row['compare_at_price'], 2) : null,
            'stock' => (int) ($row['stock'] ?? 0),
            'currency' => $row['currency'] ?? ($this->config['currency'] ?? 'VND'),
            'track_inventory' => (int) ($row['track_inventory'] ?? 1),
            'status' => $row['status'] ?? null,
            'visibility' => $row['visibility'] ?? null,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'stock_state' => $row['stock_state'] ?? null,
            'brand' => $row['brand'] ?? null,
            'weight' => $row['weight'] ?? null,
            'origin' => $row['origin'] ?? null,
            'detail_description' => $row['detail_description'] ?? null,
            'attributes' => $this->decodeAttributes($row['attributes_json'] ?? null),
            'primary_image_url' => $primaryImageUrl,
            'primary_image_alt' => $row['primary_image_alt'] ?? ($row['name'] ?? 'Product'),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function mapImage(array $row): array
    {
        $rawImageUrl = $row['image_url'] ?? null;

        return [
            'id' => (int) ($row['id'] ?? 0),
            'product_id' => (int) ($row['product_id'] ?? 0),
            'product_name' => $row['product_name'] ?? null,
            'product_slug' => $row['product_slug'] ?? null,
            'product_status' => $row['product_status'] ?? null,
            'asset_type' => $row['asset_type'] ?? null,
            'image_url' => $this->normalizeAssetUrl($rawImageUrl),
            'stored_image_url' => $rawImageUrl,
            'source_type' => $this->assetUrlResolver->isManagedUploadPath($rawImageUrl) ? 'upload' : 'url',
            'alt_text' => $row['alt_text'] ?? null,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'is_primary' => (int) ($row['is_primary'] ?? 0),
            'status' => $row['status'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function mapProductEditorMedia(array $rows): array
    {
        $thumbnail = null;
        $gallery = [];

        foreach ($rows as $row) {
            $mapped = [
                'id' => (int) ($row['id'] ?? 0),
                'asset_type' => $row['asset_type'] ?? 'gallery',
                'image_url' => $this->normalizeAssetUrl($row['image_url'] ?? null),
                'stored_image_url' => $row['image_url'] ?? null,
                'source_type' => $this->assetUrlResolver->isManagedUploadPath($row['image_url'] ?? null) ? 'upload' : 'url',
                'alt_text' => $row['alt_text'] ?? null,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'is_primary' => (int) ($row['is_primary'] ?? 0),
                'status' => $row['status'] ?? 'active',
            ];

            if (($row['asset_type'] ?? '') === 'thumbnail') {
                $thumbnail = $mapped;
                continue;
            }

            if (($row['asset_type'] ?? '') === 'gallery') {
                $gallery[] = $mapped;
            }
        }

        return [
            'thumbnail' => $thumbnail,
            'gallery' => $gallery,
        ];
    }

    private function prepareProductMediaPayload(array $mediaItems, array $files, array &$storedUploadPaths, string $appBasePath = ''): array
    {
        $prepared = [];

        foreach ($mediaItems as $item) {
            $errorField = ($item['asset_type'] ?? '') === 'thumbnail' ? 'media_thumbnail' : 'media_gallery';
            $persistedImageUrl = $this->resolveImagePayloadPath($item, $files, $storedUploadPaths, $errorField, $appBasePath);

            $prepared[] = [
                'id' => $item['id'] ?? null,
                'product_id' => 0,
                'asset_type' => $item['asset_type'],
                'image_url' => $persistedImageUrl,
                'alt_text' => $item['alt_text'] ?? null,
                'sort_order' => (int) ($item['sort_order'] ?? 0),
                'is_primary' => (int) ($item['is_primary'] ?? 0),
                'status' => 'active',
            ];
        }

        return $prepared;
    }

    private function resolveImagePayloadPath(
        array $data,
        array $files,
        array &$storedUploadPaths,
        string $errorField,
        string $appBasePath = ''
    ): string {
        $sourceType = (string) ($data['source_type'] ?? 'url');

        if ($sourceType === 'url') {
            return (string) ($data['image_url'] ?? '');
        }

        $uploadKey = $data['upload_key'] ?? null;
        if (is_string($uploadKey) && $uploadKey !== '') {
            if (!array_key_exists($uploadKey, $files)) {
                throw new ProductImageUploadException([
                    $errorField => ['Image upload file is missing from the request.'],
                ]);
            }

            $stored = $this->imageUploader->store($files[$uploadKey], [
                'error_field' => $errorField,
                'app_base_path' => $appBasePath,
            ]);

            $storedUploadPaths[] = $stored['stored_path'];

            return $stored['stored_path'];
        }

        $existingImageUrl = trim((string) ($data['existing_image_url'] ?? ''));
        if ($existingImageUrl === '') {
            throw new ProductImageUploadException([
                $errorField => ['Image upload file is required.'],
            ]);
        }

        if (!$this->imageUploader->isManagedStoredPath($existingImageUrl)) {
            $message = in_array($errorField, ['media_thumbnail', 'media_gallery'], true)
                ? 'Existing uploaded product media path is invalid.'
                : 'Existing uploaded image path is invalid.';

            throw new ProductImageUploadException([
                $errorField => [$message],
            ], $message);
        }

        return $existingImageUrl;
    }

    private function syncProductEditorMedia(int $productId, array $mediaItems): void
    {
        $existingAssets = $this->images->listNonArchivedAssetsForProduct($productId, $this->validator->productEditorImageAssetTypeValues());
        $existingById = [];
        foreach ($existingAssets as $asset) {
            $existingById[(int) ($asset['id'] ?? 0)] = $asset;
        }

        $retainedIds = [];

        foreach ($mediaItems as $item) {
            $payload = $item;
            $payload['product_id'] = $productId;

            $itemId = (int) ($item['id'] ?? 0);
            if ($itemId > 0 && isset($existingById[$itemId])) {
                if ((int) ($payload['is_primary'] ?? 0) === 1) {
                    $this->images->clearPrimaryFlagForProduct($productId, (string) $payload['asset_type'], $itemId);
                }

                $this->images->update($itemId, $payload);
                $retainedIds[] = $itemId;
                continue;
            }

            if ((int) ($payload['is_primary'] ?? 0) === 1) {
                $this->images->clearPrimaryFlagForProduct($productId, (string) $payload['asset_type']);
            }

            $retainedIds[] = $this->images->create($payload);
        }

        foreach ($existingAssets as $asset) {
            $assetId = (int) ($asset['id'] ?? 0);
            if (!in_array($assetId, $retainedIds, true)) {
                $this->images->archive($assetId);
            }
        }
    }

    private function loadProductWithMedia(int $productId): ?array
    {
        $product = $this->products->findById($productId, $this->lowStockThreshold());
        if ($product === null) {
            return null;
        }

        $mapped = $this->mapProduct($product);
        $mapped['media'] = $this->mapProductEditorMedia(
            $this->images->listNonArchivedAssetsForProduct($productId, $this->validator->productEditorImageAssetTypeValues())
        );

        return $mapped;
    }

    private function paginationMeta(array $page): array
    {
        $totalPages = (int) ceil(($page['total'] ?: 0) / max(1, $page['per_page']));

        return [
            'total' => (int) $page['total'],
            'page' => (int) $page['page'],
            'per_page' => (int) $page['per_page'],
            'total_pages' => max(1, $totalPages),
        ];
    }

    private function buildCategorySummary(array $summary): array
    {
        $normalized = [
            'total' => (int) ($summary['total'] ?? 0),
            'products_tagged' => (int) ($summary['products_tagged'] ?? 0),
        ];

        foreach (($this->config['categories']['visibility'] ?? []) as $visibility) {
            $normalized[$visibility] = (int) ($summary[$visibility] ?? 0);
        }

        foreach (($this->config['categories']['statuses'] ?? []) as $status) {
            $normalized[$status] = (int) ($summary[$status] ?? 0);
        }

        return $normalized;
    }

    private function buildProductSummary(array $summary): array
    {
        $normalized = [
            'total' => (int) ($summary['total'] ?? 0),
            'in_stock' => (int) ($summary['in_stock'] ?? 0),
            'low_stock' => (int) ($summary['low_stock'] ?? 0),
            'out_of_stock' => (int) ($summary['out_of_stock'] ?? 0),
        ];

        foreach (($this->config['products']['statuses'] ?? []) as $status) {
            $normalized[$status] = (int) ($summary[$status] ?? 0);
        }

        return $normalized;
    }

    private function buildImageSummary(array $summary): array
    {
        $normalized = [
            'total' => (int) ($summary['total'] ?? 0),
            'primary' => (int) ($summary['primary'] ?? 0),
        ];

        foreach (($this->config['products']['image_asset_types'] ?? []) as $type) {
            $normalized[$type] = (int) ($summary[$type] ?? 0);
        }

        foreach (($this->config['products']['image_statuses'] ?? []) as $status) {
            $normalized[$status] = (int) ($summary[$status] ?? 0);
        }

        return $normalized;
    }

    private function validateProductCategoryReference(int $categoryId): ?array
    {
        $category = $this->categories->findById($categoryId);
        if ($category === null) {
            return $this->error(['category_id' => ['Product category not found.']], 404);
        }

        if (($category['status'] ?? null) === 'archived') {
            return $this->error(['category_id' => ['Archived product categories cannot receive products.']], 409);
        }

        return null;
    }

    private function validateImageOwner(int $productId, string $imageStatus): ?array
    {
        $product = $this->products->findById($productId, $this->lowStockThreshold());
        if ($product === null) {
            return $this->error(['product_id' => ['Product not found.']], 404);
        }

        if (($product['status'] ?? null) === 'archived' && $imageStatus !== 'archived') {
            return $this->error(['product_id' => ['Archived products cannot receive non-archived images.']], 409);
        }

        return null;
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

    private function normalizeAssetUrl(?string $value, ?string $appBasePath = null): ?string
    {
        return $this->assetUrlResolver->resolve($value, $appBasePath);
    }

    private function lowStockThreshold(): int
    {
        return max(1, (int) ($this->config['products']['low_stock_threshold'] ?? 10));
    }

    private function durationMs(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 2);
    }

    private function cleanupStoredUploads(array $storedUploadPaths): void
    {
        foreach ($storedUploadPaths as $storedPath) {
            $this->imageUploader->deleteStoredFile(is_string($storedPath) ? $storedPath : null);
        }
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
