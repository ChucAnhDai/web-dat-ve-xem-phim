<?php

namespace App\Validators;

use App\Support\Slugger;

class ShopCatalogValidator
{
    public const PRODUCT_SORTS = [
        'featured',
        'newest',
        'oldest',
        'price_asc',
        'price_desc',
        'name_asc',
        'name_desc',
    ];

    public const PRODUCT_STOCK_STATES = ['in_stock', 'low_stock', 'out_of_stock'];

    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? require dirname(__DIR__, 2) . '/config/shop.php';
    }

    public function normalizeCategoryFilters(array $input): array
    {
        return [
            'search' => $this->nullableString($input['search'] ?? null),
            'featured_only' => $this->toBoolInt($input['featured_only'] ?? 0) ?? 0,
        ];
    }

    public function normalizeProductFilters(array $input): array
    {
        $minPrice = $this->toNullableNonNegativeFloat($input['min_price'] ?? null);
        $maxPrice = $this->toNullableNonNegativeFloat($input['max_price'] ?? null);

        if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
            [$minPrice, $maxPrice] = [$maxPrice, $minPrice];
        }

        return [
            'page' => $this->toPage($input['page'] ?? 1),
            'per_page' => $this->toPerPage($input['per_page'] ?? 12),
            'search' => $this->nullableString($input['search'] ?? null),
            'category_slug' => $this->normalizeSlug($input['category_slug'] ?? null),
            'sort' => $this->normalizeSort($input['sort'] ?? null),
            'featured_only' => $this->toBoolInt($input['featured_only'] ?? 0) ?? 0,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'stock_state' => $this->normalizeOptionalEnum($input['stock_state'] ?? null, self::PRODUCT_STOCK_STATES),
        ];
    }

    public function normalizeProductSlug(string $slug): string
    {
        return trim($slug);
    }

    private function normalizeSlug($value): ?string
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            return null;
        }

        $slug = Slugger::slugify($value);

        return $slug !== '' ? $slug : null;
    }

    private function normalizeSort($value): string
    {
        $value = strtolower(trim((string) ($value ?? 'featured')));

        return in_array($value, self::PRODUCT_SORTS, true) ? $value : 'featured';
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

    private function nullableString($value): ?string
    {
        $cleaned = trim((string) ($value ?? ''));

        return $cleaned === '' ? null : $cleaned;
    }

    private function toNullableNonNegativeFloat($value): ?float
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

    private function toBoolInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return 1;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return 0;
        }

        return null;
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

    private function toPage($value): int
    {
        $page = $this->toPositiveInt($value);

        return $page ?? 1;
    }

    private function toPerPage($value): int
    {
        $perPage = $this->toPositiveInt($value) ?? 12;

        return max(1, min($perPage, 48));
    }
}
