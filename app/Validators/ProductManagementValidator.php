<?php

namespace App\Validators;

use App\Core\Validator;
use App\Support\Slugger;

class ProductManagementValidator
{
    public const PRODUCT_STOCK_STATES = ['in_stock', 'low_stock', 'out_of_stock'];
    public const IMAGE_SOURCE_TYPES = ['url', 'upload'];

    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? require dirname(__DIR__, 2) . '/config/shop.php';
    }

    public function validateCategoryPayload(array $input): array
    {
        $errors = Validator::required($input, ['name']);

        $name = $this->cleanString($input['name'] ?? null);
        $slug = Slugger::slugify($input['slug'] ?? $name);
        $description = $this->nullableString($input['description'] ?? null);
        $displayOrder = $this->toNonNegativeInt($input['display_order'] ?? 0);
        $visibility = strtolower(trim((string) ($input['visibility'] ?? 'standard')));
        $status = strtolower(trim((string) ($input['status'] ?? 'active')));

        if ($name === '') {
            $errors['name'][] = 'Field is required.';
        }
        if ($slug === '') {
            $errors['slug'][] = 'Slug is required.';
        }
        if ($displayOrder === null) {
            $errors['display_order'][] = 'Display order must be a non-negative integer.';
        }
        if (!in_array($visibility, $this->categoryVisibilityValues(), true)) {
            $errors['visibility'][] = 'Category visibility is invalid.';
        }
        if (!in_array($status, $this->categoryStatusValues(), true)) {
            $errors['status'][] = 'Category status is invalid.';
        }

        if ($status === 'archived') {
            $visibility = 'hidden';
        }

        return [
            'data' => [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'display_order' => $displayOrder ?? 0,
                'visibility' => $visibility,
                'status' => $status,
            ],
            'errors' => $errors,
        ];
    }

    public function normalizeCategoryFilters(array $input): array
    {
        return [
            'page' => $this->toPage($input['page'] ?? 1),
            'per_page' => $this->toPerPage($input['per_page'] ?? 20),
            'search' => $this->nullableString($input['search'] ?? null),
            'visibility' => $this->normalizeOptionalEnum($input['visibility'] ?? null, $this->categoryVisibilityValues()),
            'status' => $this->normalizeOptionalEnum($input['status'] ?? null, $this->categoryStatusValues()),
        ];
    }

    public function categoryVisibilityValues(): array
    {
        return array_values($this->config['categories']['visibility'] ?? []);
    }

    public function categoryStatusValues(): array
    {
        return array_values($this->config['categories']['statuses'] ?? []);
    }

    public function validateProductPayload(array $input): array
    {
        $errors = Validator::required($input, ['category_id', 'name', 'sku', 'price', 'stock', 'status']);

        $categoryId = $this->toPositiveInt($input['category_id'] ?? null);
        $name = $this->cleanString($input['name'] ?? null);
        $slug = Slugger::slugify($input['slug'] ?? $name);
        $sku = $this->normalizeSku($input['sku'] ?? null);
        $shortDescription = $this->nullableString($input['short_description'] ?? null);
        $description = $this->nullableString($input['description'] ?? null);
        $price = $this->toNonNegativeFloat($input['price'] ?? null);
        $compareAtPriceInput = $input['compare_at_price'] ?? null;
        $compareAtPrice = $this->toNullableNonNegativeFloat($compareAtPriceInput);
        $stock = $this->toNonNegativeInt($input['stock'] ?? null);
        $currency = strtoupper(trim((string) ($input['currency'] ?? ($this->config['currency'] ?? 'VND'))));
        $trackInventory = $this->toBoolInt($input['track_inventory'] ?? 1);
        $status = strtolower(trim((string) ($input['status'] ?? 'draft')));
        $visibility = strtolower(trim((string) ($input['visibility'] ?? 'standard')));
        $sortOrder = $this->toNonNegativeInt($input['sort_order'] ?? 0);

        $brand = $this->nullableString($input['brand'] ?? null);
        $weight = $this->nullableString($input['weight'] ?? null);
        $origin = $this->nullableString($input['origin'] ?? null);
        $detailDescription = $this->nullableString($input['detail_description'] ?? null);
        $normalizedAttributes = $this->normalizeAttributes($input['attributes'] ?? null);

        if ($categoryId === null) {
            $errors['category_id'][] = 'Category ID must be a positive integer.';
        }
        if ($name === '') {
            $errors['name'][] = 'Field is required.';
        }
        if ($slug === '') {
            $errors['slug'][] = 'Slug is required.';
        }
        if ($sku === '') {
            $errors['sku'][] = 'SKU is required.';
        }
        if ($price === null) {
            $errors['price'][] = 'Price must be a non-negative number.';
        }
        if (($compareAtPriceInput !== null && $compareAtPriceInput !== '') && $compareAtPrice === null) {
            $errors['compare_at_price'][] = 'Compare-at price must be a non-negative number.';
        } elseif ($price !== null && $compareAtPrice !== null && $compareAtPrice < $price) {
            $errors['compare_at_price'][] = 'Compare-at price must be greater than or equal to the selling price.';
        }
        if ($stock === null) {
            $errors['stock'][] = 'Stock must be a non-negative integer.';
        }
        if ($currency !== strtoupper((string) ($this->config['currency'] ?? 'VND'))) {
            $errors['currency'][] = 'Currency is invalid.';
        }
        if ($trackInventory === null) {
            $errors['track_inventory'][] = 'Track inventory flag is invalid.';
        }
        if (!in_array($status, $this->productStatusValues(), true)) {
            $errors['status'][] = 'Product status is invalid.';
        }
        if (!in_array($visibility, $this->productVisibilityValues(), true)) {
            $errors['visibility'][] = 'Product visibility is invalid.';
        }
        if ($sortOrder === null) {
            $errors['sort_order'][] = 'Sort order must be a non-negative integer.';
        }
        if ($normalizedAttributes === false) {
            $errors['attributes'][] = 'Attributes must be a valid JSON object/array or a structured payload.';
        }

        if ($status === 'archived') {
            $visibility = 'hidden';
        }

        return [
            'data' => [
                'category_id' => $categoryId,
                'slug' => $slug,
                'sku' => $sku,
                'name' => $name,
                'short_description' => $shortDescription,
                'description' => $description,
                'price' => $price ?? 0.0,
                'compare_at_price' => $compareAtPrice,
                'stock' => $stock ?? 0,
                'currency' => $currency,
                'track_inventory' => $trackInventory ?? 1,
                'status' => $status,
                'visibility' => $visibility,
                'sort_order' => $sortOrder ?? 0,
                'details' => [
                    'brand' => $brand,
                    'weight' => $weight,
                    'origin' => $origin,
                    'description' => $detailDescription,
                    'attributes_json' => is_array($normalizedAttributes) ? json_encode($normalizedAttributes, JSON_UNESCAPED_UNICODE) : null,
                ],
            ],
            'errors' => $errors,
        ];
    }

    public function validateProductMediaPayload($input, bool $requireThumbnail = true): array
    {
        $errors = [];
        $items = $this->normalizeStructuredPayload($input);
        if ($items === null) {
            $items = [];
        }

        if ($items === false || !$this->isSequentialArray($items)) {
            return [
                'data' => [],
                'errors' => [
                    'media_gallery' => ['Product media payload is invalid.'],
                ],
            ];
        }

        $normalized = [];
        $thumbnailCount = 0;
        $galleryCount = 0;
        $allowedAssetTypes = $this->productEditorImageAssetTypeValues();

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                $errors['media_gallery'][] = 'Product media payload is invalid.';
                continue;
            }

            $assetType = strtolower(trim((string) ($item['asset_type'] ?? '')));
            $sourceType = strtolower(trim((string) ($item['source_type'] ?? 'url')));
            $clientKey = $this->nullableString($item['client_key'] ?? null);
            $imageUrl = $this->nullableString($item['image_url'] ?? null);
            $existingImageUrl = $this->nullableString($item['existing_image_url'] ?? null);
            $uploadKey = $this->nullableString($item['upload_key'] ?? null);
            $altText = $this->nullableString($item['alt_text'] ?? null);
            $sortOrder = $this->toNonNegativeInt($item['sort_order'] ?? $index);
            $id = $this->toPositiveInt($item['id'] ?? null);

            $errorField = $assetType === 'thumbnail' ? 'media_thumbnail' : 'media_gallery';

            if (!in_array($assetType, $allowedAssetTypes, true)) {
                $errors[$errorField][] = 'Product media asset type is invalid.';
                continue;
            }

            if (!in_array($sourceType, self::IMAGE_SOURCE_TYPES, true)) {
                $errors[$errorField][] = 'Product media source type is invalid.';
            }

            if ($sourceType === 'url') {
                if ($imageUrl === null || !$this->isValidUrl($imageUrl)) {
                    $errors[$errorField][] = 'Product media URL must be a valid URL.';
                }
            }

            if ($sourceType === 'upload') {
                if ($uploadKey === null && $existingImageUrl === null) {
                    $errors[$errorField][] = 'Product media upload file is required.';
                }

                if ($existingImageUrl !== null && !$this->isManagedUploadPath($existingImageUrl)) {
                    $errors[$errorField][] = 'Existing uploaded product media path is invalid.';
                }
            }

            if ($sortOrder === null) {
                $errors[$errorField][] = 'Product media sort order must be a non-negative integer.';
            }

            if ($assetType === 'thumbnail') {
                $thumbnailCount += 1;
            }
            if ($assetType === 'gallery') {
                $galleryCount += 1;
            }

            $normalized[] = [
                'id' => $id,
                'client_key' => $clientKey,
                'asset_type' => $assetType,
                'source_type' => $sourceType,
                'image_url' => $imageUrl,
                'existing_image_url' => $existingImageUrl,
                'upload_key' => $uploadKey,
                'alt_text' => $altText,
                'sort_order' => $sortOrder ?? $index,
                'is_primary' => $assetType === 'thumbnail' ? 1 : 0,
                'status' => 'active',
            ];
        }

        if ($thumbnailCount > 1) {
            $errors['media_thumbnail'][] = 'Only one primary product image is allowed.';
        }
        if ($requireThumbnail && $thumbnailCount === 0) {
            $errors['media_thumbnail'][] = 'Primary product image is required.';
        }
        if ($galleryCount > $this->maxGalleryItems()) {
            $errors['media_gallery'][] = 'Gallery image count exceeds the allowed limit.';
        }

        return [
            'data' => $normalized,
            'errors' => $errors,
        ];
    }

    public function normalizeProductFilters(array $input): array
    {
        return [
            'page' => $this->toPage($input['page'] ?? 1),
            'per_page' => $this->toPerPage($input['per_page'] ?? 20),
            'search' => $this->nullableString($input['search'] ?? null),
            'category_id' => $this->toPositiveInt($input['category_id'] ?? null),
            'status' => $this->normalizeOptionalEnum($input['status'] ?? null, $this->productStatusValues()),
            'visibility' => $this->normalizeOptionalEnum($input['visibility'] ?? null, $this->productVisibilityValues()),
            'stock_state' => $this->normalizeOptionalEnum($input['stock_state'] ?? null, self::PRODUCT_STOCK_STATES),
        ];
    }

    public function productStatusValues(): array
    {
        return array_values($this->config['products']['statuses'] ?? []);
    }

    public function productVisibilityValues(): array
    {
        return array_values($this->config['products']['visibility'] ?? []);
    }

    public function validateImagePayload(array $input): array
    {
        $errors = Validator::required($input, ['product_id', 'asset_type', 'status']);

        $productId = $this->toPositiveInt($input['product_id'] ?? null);
        $assetType = strtolower(trim((string) ($input['asset_type'] ?? '')));
        $sourceType = strtolower(trim((string) ($input['source_type'] ?? 'url')));
        $imageUrl = $this->nullableString($input['image_url'] ?? null);
        $existingImageUrl = $this->nullableString($input['existing_image_url'] ?? null);
        $uploadKey = $this->nullableString($input['upload_key'] ?? null);
        $altText = $this->nullableString($input['alt_text'] ?? null);
        $sortOrder = $this->toNonNegativeInt($input['sort_order'] ?? 0);
        $isPrimary = $this->toBoolInt($input['is_primary'] ?? 0);
        $status = strtolower(trim((string) ($input['status'] ?? 'draft')));

        if ($productId === null) {
            $errors['product_id'][] = 'Product ID must be a positive integer.';
        }
        if (!in_array($assetType, $this->imageAssetTypeValues(), true)) {
            $errors['asset_type'][] = 'Image asset type is invalid.';
        }
        if (!in_array($sourceType, self::IMAGE_SOURCE_TYPES, true)) {
            $errors['source_type'][] = 'Image source type is invalid.';
        }
        if ($sourceType === 'url' && ($imageUrl === null || !$this->isValidUrl($imageUrl))) {
            $errors['image_url'][] = 'Image URL must be a valid URL.';
        }
        if ($sourceType === 'upload') {
            if ($uploadKey === null && $existingImageUrl === null) {
                $errors['image_file'][] = 'Image upload file is required.';
            }

            if ($existingImageUrl !== null && !$this->isManagedUploadPath($existingImageUrl)) {
                $errors['image_file'][] = 'Existing uploaded image path is invalid.';
            }
        }
        if ($sortOrder === null) {
            $errors['sort_order'][] = 'Sort order must be a non-negative integer.';
        }
        if ($isPrimary === null) {
            $errors['is_primary'][] = 'Primary flag is invalid.';
        }
        if (!in_array($status, $this->imageStatusValues(), true)) {
            $errors['status'][] = 'Image status is invalid.';
        }

        if ($status === 'archived') {
            $isPrimary = 0;
        }

        return [
            'data' => [
                'product_id' => $productId,
                'asset_type' => $assetType,
                'source_type' => $sourceType,
                'image_url' => $imageUrl,
                'existing_image_url' => $existingImageUrl,
                'upload_key' => $uploadKey,
                'alt_text' => $altText,
                'sort_order' => $sortOrder ?? 0,
                'is_primary' => $isPrimary ?? 0,
                'status' => $status,
            ],
            'errors' => $errors,
        ];
    }

    public function validateImageBatchPayload($input): array
    {
        $items = $this->normalizeStructuredPayload($input);
        if ($items === null) {
            $items = [];
        }

        if ($items === false || !$this->isSequentialArray($items)) {
            return [
                'data' => [],
                'errors' => [
                    'items_manifest' => ['Product image batch payload is invalid.'],
                ],
            ];
        }

        if ($items === []) {
            return [
                'data' => [],
                'errors' => [
                    'items_manifest' => ['At least one product image is required.'],
                ],
            ];
        }

        $errors = [];
        $normalized = [];

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                $errors['items_manifest'][] = sprintf('Image #%d payload is invalid.', $index + 1);
                continue;
            }

            $validation = $this->validateImagePayload($item);
            if (!empty($validation['errors'])) {
                foreach ($validation['errors'] as $messages) {
                    if (!is_array($messages) || $messages === []) {
                        continue;
                    }

                    $errors['items_manifest'][] = sprintf('Image #%d: %s', $index + 1, (string) $messages[0]);
                }
                continue;
            }

            $normalized[] = $validation['data'];
        }

        return [
            'data' => $normalized,
            'errors' => $errors,
        ];
    }

    public function normalizeImageFilters(array $input): array
    {
        return [
            'page' => $this->toPage($input['page'] ?? 1),
            'per_page' => $this->toPerPage($input['per_page'] ?? 20),
            'search' => $this->nullableString($input['search'] ?? null),
            'product_id' => $this->toPositiveInt($input['product_id'] ?? null),
            'asset_type' => $this->normalizeOptionalEnum($input['asset_type'] ?? null, $this->imageAssetTypeValues()),
            'status' => $this->normalizeOptionalEnum($input['status'] ?? null, $this->imageStatusValues()),
        ];
    }

    public function imageAssetTypeValues(): array
    {
        return array_values($this->config['products']['image_asset_types'] ?? []);
    }

    public function productEditorImageAssetTypeValues(): array
    {
        return array_values($this->config['products']['editor_image_asset_types'] ?? ['thumbnail', 'gallery']);
    }

    public function imageStatusValues(): array
    {
        return array_values($this->config['products']['image_statuses'] ?? []);
    }

    private function cleanString($value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function nullableString($value): ?string
    {
        $cleaned = trim((string) ($value ?? ''));

        return $cleaned === '' ? null : $cleaned;
    }

    private function toNonNegativeInt($value): ?int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return null;
        }

        $intValue = (int) $value;

        return $intValue >= 0 ? $intValue : null;
    }

    private function toNonNegativeFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }

        $floatValue = round((float) $value, 2);

        return $floatValue >= 0 ? $floatValue : null;
    }

    private function toNullableNonNegativeFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->toNonNegativeFloat($value);
    }

    private function normalizeOptionalEnum($value, array $allowed): ?string
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            return null;
        }

        $value = strtolower($value);

        return in_array($value, $allowed, true) ? $value : null;
    }

    private function isManagedUploadPath(?string $value): bool
    {
        $normalized = trim(str_replace('\\', '/', (string) ($value ?? '')), '/');
        if ($normalized === '') {
            return false;
        }

        $prefix = trim(str_replace('\\', '/', (string) ($this->config['products']['uploads']['public_directory'] ?? 'public/uploads/products')), '/');
        if ($prefix === '') {
            return false;
        }

        return $normalized === $prefix || str_starts_with($normalized, $prefix . '/');
    }

    private function toPositiveInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return null;
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : null;
    }

    private function toBoolInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $normalized = strtolower(trim((string) $value));
        $truthy = ['1', 'true', 'yes', 'on'];
        $falsy = ['0', 'false', 'no', 'off'];

        if (in_array($normalized, $truthy, true)) {
            return 1;
        }
        if (in_array($normalized, $falsy, true)) {
            return 0;
        }

        return null;
    }

    private function normalizeSku($value): string
    {
        $value = strtoupper(trim((string) ($value ?? '')));
        $value = preg_replace('/[^A-Z0-9_-]+/', '-', $value) ?? '';

        return trim($value, '-');
    }

    private function normalizeAttributes($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return json_decode(json_encode($value, JSON_UNESCAPED_UNICODE), true);
        }

        if (!is_string($value)) {
            return false;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : false;
    }

    private function normalizeStructuredPayload($value)
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return json_decode(json_encode($value, JSON_UNESCAPED_UNICODE), true);
        }

        if (!is_string($value)) {
            return false;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : false;
    }

    private function isValidUrl(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    private function toPage($value): int
    {
        $page = $this->toPositiveInt($value);

        return $page ?? 1;
    }

    private function toPerPage($value): int
    {
        $perPage = $this->toPositiveInt($value) ?? 20;

        return max(1, min($perPage, 100));
    }

    private function isSequentialArray(array $value): bool
    {
        return array_values($value) === $value;
    }

    private function maxGalleryItems(): int
    {
        return max(1, (int) ($this->config['products']['max_gallery_items'] ?? 12));
    }
}
