<?php

namespace App\Validators;

class MovieCatalogValidator
{
    private const PUBLIC_STATUSES = ['now_showing', 'coming_soon'];
    private const SORT_OPTIONS = ['popular', 'newest', 'rating'];
    private const RATING_OPTIONS = [3.5, 4.0, 4.5];

    public function normalizeListFilters(array $input): array
    {
        return [
            'page' => $this->toPage($input['page'] ?? 1),
            'per_page' => $this->toPerPage($input['per_page'] ?? 12),
            'search' => $this->nullableString($input['search'] ?? null),
            'category_id' => $this->nullableSlug($input['category_id'] ?? null),
            'min_rating' => $this->toAllowedRating($input['min_rating'] ?? null),
            'sort' => $this->normalizeSort($input['sort'] ?? null),
            'status' => $this->normalizeStatus($input['status'] ?? 'now_showing'),
        ];
    }

    private function nullableString($value): ?string
    {
        $cleaned = trim((string) ($value ?? ''));
        if ($cleaned === '') {
            return null;
        }

        $length = function_exists('mb_strlen') ? mb_strlen($cleaned) : strlen($cleaned);

        return $length >= 2 ? $cleaned : null;
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

    private function nullableSlug($value): ?string
    {
        $cleaned = strtolower(trim((string) ($value ?? '')));
        if ($cleaned === '') {
            return null;
        }
        if (!preg_match('/^[a-z0-9,-]+$/', $cleaned)) {
            return null;
        }

        return $cleaned;
    }

    private function toAllowedRating($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }

        $normalized = round((float) $value, 1);

        return in_array($normalized, self::RATING_OPTIONS, true) ? $normalized : null;
    }

    private function normalizeSort($value): string
    {
        $normalized = strtolower(trim((string) ($value ?? 'popular')));

        return in_array($normalized, self::SORT_OPTIONS, true) ? $normalized : 'popular';
    }

    private function normalizeStatus($value): ?string
    {
        $normalized = strtolower(trim((string) ($value ?? '')));

        return in_array($normalized, self::PUBLIC_STATUSES, true) ? $normalized : 'now_showing';
    }

    private function toPage($value): int
    {
        $page = $this->toPositiveInt($value);

        return $page ?? 1;
    }

    private function toPerPage($value): int
    {
        $perPage = $this->toPositiveInt($value) ?? 12;

        return max(1, min($perPage, 24));
    }
}
