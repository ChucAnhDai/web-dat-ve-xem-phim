<?php

namespace App\Validators;

use App\Core\Validator;
use App\Support\Slugger;

class MovieManagementValidator
{
    public const MOVIE_STATUSES = ['draft', 'coming_soon', 'now_showing', 'ended', 'archived'];
    public const ASSET_TYPES = ['poster', 'banner', 'gallery'];
    public const ASSET_STATUSES = ['draft', 'active', 'archived'];
    public const REVIEW_STATUSES = ['pending', 'approved', 'rejected'];

    public function validateMoviePayload(array $input): array
    {
        $errors = Validator::required($input, ['title', 'primary_category_id', 'duration_minutes', 'status']);

        $title = $this->cleanString($input['title'] ?? null);
        $slug = Slugger::slugify($input['slug'] ?? $title);
        $summary = $this->nullableString($input['summary'] ?? null);
        $durationMinutes = $this->toPositiveInt($input['duration_minutes'] ?? null);
        $releaseDate = $this->nullableDate($input['release_date'] ?? null);
        $posterUrl = $this->nullableString($input['poster_url'] ?? null);
        $trailerUrl = $this->nullableString($input['trailer_url'] ?? null);
        $ageRating = $this->nullableString($input['age_rating'] ?? null);
        $language = $this->nullableString($input['language'] ?? null);
        $director = $this->nullableString($input['director'] ?? null);
        $writer = $this->nullableString($input['writer'] ?? null);
        $castText = $this->nullableString($input['cast_text'] ?? null);
        $studio = $this->nullableString($input['studio'] ?? null);
        $averageRatingInput = $input['average_rating'] ?? null;
        $averageRating = $this->toNullableFloat($averageRatingInput, 2);
        $status = strtolower(trim((string) ($input['status'] ?? '')));
        $primaryCategoryId = $this->toPositiveInt($input['primary_category_id'] ?? null);
        $categoryIds = $this->normalizeIds($input['category_ids'] ?? [$primaryCategoryId]);

        if ($title === '') {
            $errors['title'][] = 'Field is required.';
        }
        if ($slug === '') {
            $errors['slug'][] = 'Slug is required.';
        }
        if ($primaryCategoryId === null) {
            $errors['primary_category_id'][] = 'Primary category must be a positive integer.';
        }
        if ($durationMinutes === null || $durationMinutes < 1 || $durationMinutes > 500) {
            $errors['duration_minutes'][] = 'Duration must be between 1 and 500 minutes.';
        }
        if ($releaseDate === false) {
            $errors['release_date'][] = 'Release date must be a valid YYYY-MM-DD date.';
        }
        if ($posterUrl !== null && !$this->isValidUrl($posterUrl)) {
            $errors['poster_url'][] = 'Poster URL must be a valid URL.';
        }
        if ($trailerUrl !== null && !$this->isValidUrl($trailerUrl)) {
            $errors['trailer_url'][] = 'Trailer URL must be a valid URL.';
        }
        if (($averageRatingInput !== null && $averageRatingInput !== '') && $averageRating === null) {
            $errors['average_rating'][] = 'Average rating must be numeric.';
        } elseif ($averageRating !== null && ($averageRating < 0 || $averageRating > 5)) {
            $errors['average_rating'][] = 'Average rating must be between 0 and 5.';
        }
        if (!in_array($status, self::MOVIE_STATUSES, true)) {
            $errors['status'][] = 'Movie status is invalid.';
        }
        if (empty($categoryIds)) {
            $errors['category_ids'][] = 'At least one category must be assigned.';
        }
        if ($primaryCategoryId !== null && !in_array($primaryCategoryId, $categoryIds, true)) {
            $categoryIds[] = $primaryCategoryId;
        }

        return [
            'data' => [
                'title' => $title,
                'slug' => $slug,
                'summary' => $summary,
                'primary_category_id' => $primaryCategoryId,
                'duration_minutes' => $durationMinutes,
                'release_date' => $releaseDate === false ? null : $releaseDate,
                'poster_url' => $posterUrl,
                'trailer_url' => $trailerUrl,
                'age_rating' => $ageRating,
                'language' => $language,
                'director' => $director,
                'writer' => $writer,
                'cast_text' => $castText,
                'studio' => $studio,
                'average_rating' => $averageRating ?? 0.0,
                'status' => $status,
                'category_ids' => array_values(array_unique($categoryIds)),
            ],
            'errors' => $errors,
        ];
    }

    public function normalizeMovieFilters(array $input): array
    {
        return [
            'page' => $this->toPage($input['page'] ?? 1),
            'per_page' => $this->toPerPage($input['per_page'] ?? 20),
            'search' => $this->nullableString($input['search'] ?? null),
            'status' => $this->normalizeOptionalEnum($input['status'] ?? null, self::MOVIE_STATUSES),
            'primary_category_id' => $this->toPositiveInt($input['primary_category_id'] ?? null),
        ];
    }

    public function validateCategoryPayload(array $input): array
    {
        $errors = Validator::required($input, ['name']);

        $name = $this->cleanString($input['name'] ?? null);
        $slug = Slugger::slugify($input['slug'] ?? $name);
        $description = $this->nullableString($input['description'] ?? null);
        $displayOrder = $this->toNonNegativeInt($input['display_order'] ?? 0);
        $isActive = $this->normalizeActiveFlag($input);

        if ($name === '') {
            $errors['name'][] = 'Field is required.';
        }
        if ($slug === '') {
            $errors['slug'][] = 'Slug is required.';
        }
        if ($displayOrder === null) {
            $errors['display_order'][] = 'Display order must be a non-negative integer.';
        }
        if ($isActive === null) {
            $errors['is_active'][] = 'Category active flag is invalid.';
        }

        return [
            'data' => [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'display_order' => $displayOrder ?? 0,
                'is_active' => $isActive ?? 0,
            ],
            'errors' => $errors,
        ];
    }

    public function normalizeCategoryFilters(array $input): array
    {
        $isActive = null;
        if (array_key_exists('is_active', $input)) {
            $isActive = $this->toBoolInt($input['is_active']);
        } elseif (array_key_exists('status', $input)) {
            $isActive = $this->normalizeActiveFlag($input);
        }

        return [
            'page' => $this->toPage($input['page'] ?? 1),
            'per_page' => $this->toPerPage($input['per_page'] ?? 20),
            'search' => $this->nullableString($input['search'] ?? null),
            'is_active' => $isActive,
        ];
    }

    public function validateAssetPayload(array $input): array
    {
        $errors = Validator::required($input, ['movie_id', 'asset_type', 'image_url', 'status']);

        $movieId = $this->toPositiveInt($input['movie_id'] ?? null);
        $assetType = strtolower(trim((string) ($input['asset_type'] ?? '')));
        $imageUrl = $this->nullableString($input['image_url'] ?? null);
        $altText = $this->nullableString($input['alt_text'] ?? null);
        $sortOrder = $this->toNonNegativeInt($input['sort_order'] ?? 0);
        $isPrimary = $this->toBoolInt($input['is_primary'] ?? 0);
        $status = strtolower(trim((string) ($input['status'] ?? '')));

        if ($movieId === null) {
            $errors['movie_id'][] = 'Movie ID must be a positive integer.';
        }
        if (!in_array($assetType, self::ASSET_TYPES, true)) {
            $errors['asset_type'][] = 'Asset type is invalid.';
        }
        if ($imageUrl === null || !$this->isValidUrl($imageUrl)) {
            $errors['image_url'][] = 'Image URL must be a valid URL.';
        }
        if ($sortOrder === null) {
            $errors['sort_order'][] = 'Sort order must be a non-negative integer.';
        }
        if ($isPrimary === null) {
            $errors['is_primary'][] = 'Primary flag is invalid.';
        }
        if (!in_array($status, self::ASSET_STATUSES, true)) {
            $errors['status'][] = 'Asset status is invalid.';
        }

        if ($status === 'archived') {
            $isPrimary = 0;
        }

        return [
            'data' => [
                'movie_id' => $movieId,
                'asset_type' => $assetType,
                'image_url' => $imageUrl,
                'alt_text' => $altText,
                'sort_order' => $sortOrder ?? 0,
                'is_primary' => $isPrimary ?? 0,
                'status' => $status,
            ],
            'errors' => $errors,
        ];
    }

    public function normalizeAssetFilters(array $input): array
    {
        return [
            'page' => $this->toPage($input['page'] ?? 1),
            'per_page' => $this->toPerPage($input['per_page'] ?? 20),
            'search' => $this->nullableString($input['search'] ?? null),
            'movie_id' => $this->toPositiveInt($input['movie_id'] ?? null),
            'asset_type' => $this->normalizeOptionalEnum($input['asset_type'] ?? null, self::ASSET_TYPES),
            'status' => $this->normalizeOptionalEnum($input['status'] ?? null, self::ASSET_STATUSES),
        ];
    }

    public function validateReviewModerationPayload(array $input): array
    {
        $errors = Validator::required($input, ['status', 'is_visible']);

        $status = strtolower(trim((string) ($input['status'] ?? '')));
        $isVisible = $this->toBoolInt($input['is_visible'] ?? null);
        $moderationNote = $this->nullableString($input['moderation_note'] ?? null);

        if (!in_array($status, self::REVIEW_STATUSES, true)) {
            $errors['status'][] = 'Review status is invalid.';
        }
        if ($isVisible === null) {
            $errors['is_visible'][] = 'Visibility flag is invalid.';
        }

        if ($status !== 'approved') {
            $isVisible = 0;
        }

        return [
            'data' => [
                'status' => $status,
                'is_visible' => $isVisible ?? 0,
                'moderation_note' => $moderationNote,
            ],
            'errors' => $errors,
        ];
    }

    public function normalizeReviewFilters(array $input): array
    {
        return [
            'page' => $this->toPage($input['page'] ?? 1),
            'per_page' => $this->toPerPage($input['per_page'] ?? 20),
            'search' => $this->nullableString($input['search'] ?? null),
            'movie_id' => $this->toPositiveInt($input['movie_id'] ?? null),
            'status' => $this->normalizeOptionalEnum($input['status'] ?? null, self::REVIEW_STATUSES),
            'is_visible' => array_key_exists('is_visible', $input) ? $this->toBoolInt($input['is_visible']) : null,
        ];
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

    private function nullableDate($value)
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            return false;
        }

        return $value;
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

    private function toNullableFloat($value, int $precision = 2): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }

        return round((float) $value, $precision);
    }

    private function normalizeIds($value): array
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        $ids = [];
        foreach ($value as $item) {
            $id = $this->toPositiveInt($item);
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
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

    private function normalizeActiveFlag(array $input): ?int
    {
        if (array_key_exists('is_active', $input)) {
            return $this->toBoolInt($input['is_active']);
        }

        if (!array_key_exists('status', $input)) {
            return 1;
        }

        $status = strtolower(trim((string) $input['status']));
        if ($status === 'active') {
            return 1;
        }
        if ($status === 'inactive') {
            return 0;
        }

        return null;
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
}
