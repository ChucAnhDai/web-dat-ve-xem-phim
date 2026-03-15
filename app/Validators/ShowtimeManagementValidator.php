<?php

namespace App\Validators;

use App\Core\Validator;

class ShowtimeManagementValidator
{
    public const FILTER_SCOPES = ['active', 'archived', 'all'];
    public const SHOWTIME_STATUSES = ['draft', 'published', 'cancelled', 'completed', 'archived'];
    public const PRESENTATION_TYPES = ['2d', '3d', 'imax', '4dx', 'screenx', 'dolby_atmos'];
    public const LANGUAGE_VERSIONS = ['original', 'subtitled', 'dubbed'];

    public function validatePayload(array $input): array
    {
        $errors = Validator::required($input, ['movie_id', 'room_id', 'show_date', 'start_time', 'price', 'status']);

        $movieId = $this->toPositiveInt($input['movie_id'] ?? null);
        $roomId = $this->toPositiveInt($input['room_id'] ?? null);
        $showDate = $this->nullableDate($input['show_date'] ?? null);
        $startTime = $this->nullableTime($input['start_time'] ?? null);
        $price = $this->toNullableFloat($input['price'] ?? null, 2);
        $status = strtolower(trim((string) ($input['status'] ?? '')));
        $presentationType = $this->normalizeOptionalEnum($input['presentation_type'] ?? '2d', self::PRESENTATION_TYPES) ?? '2d';
        $languageVersion = $this->normalizeOptionalEnum($input['language_version'] ?? 'original', self::LANGUAGE_VERSIONS) ?? 'original';

        if ($movieId === null) {
            $errors['movie_id'][] = 'Movie ID must be a positive integer.';
        }
        if ($roomId === null) {
            $errors['room_id'][] = 'Room ID must be a positive integer.';
        }
        if ($showDate === false) {
            $errors['show_date'][] = 'Show date must be a valid YYYY-MM-DD date.';
        }
        if ($startTime === false) {
            $errors['start_time'][] = 'Start time must be a valid HH:MM or HH:MM:SS value.';
        }
        if (($input['price'] ?? null) !== null && ($input['price'] ?? '') !== '' && $price === null) {
            $errors['price'][] = 'Price must be numeric.';
        } elseif ($price === null || $price <= 0 || $price > 1000000) {
            $errors['price'][] = 'Price must be greater than 0 and less than or equal to 1000000.';
        }
        if (!in_array($status, self::SHOWTIME_STATUSES, true)) {
            $errors['status'][] = 'Showtime status is invalid.';
        }

        return [
            'data' => [
                'movie_id' => $movieId,
                'room_id' => $roomId,
                'show_date' => $showDate === false ? null : $showDate,
                'start_time' => $startTime === false ? null : $startTime,
                'price' => $price,
                'status' => $status,
                'presentation_type' => $presentationType,
                'language_version' => $languageVersion,
            ],
            'errors' => $errors,
        ];
    }

    public function normalizeAdminFilters(array $input): array
    {
        $status = $this->normalizeOptionalEnum($input['status'] ?? null, self::SHOWTIME_STATUSES);
        $scope = $this->normalizeArchiveScope($input['scope'] ?? null, $status);

        return [
            'page' => $this->toPage($input['page'] ?? 1),
            'per_page' => $this->toPerPage($input['per_page'] ?? 20),
            'search' => $this->nullableString($input['search'] ?? null),
            'movie_id' => $this->toPositiveInt($input['movie_id'] ?? null),
            'cinema_id' => $this->toPositiveInt($input['cinema_id'] ?? null),
            'room_id' => $this->toPositiveInt($input['room_id'] ?? null),
            'status' => $this->normalizeScopedStatus($status, $scope),
            'scope' => $scope,
            'show_date' => $this->nullableDate($input['show_date'] ?? null),
        ];
    }

    public function normalizePublicFilters(array $input): array
    {
        return [
            'page' => $this->toPage($input['page'] ?? 1),
            'per_page' => $this->toPerPage($input['per_page'] ?? 20),
            'search' => $this->nullableString($input['search'] ?? null),
            'movie_id' => $this->toPositiveInt($input['movie_id'] ?? null),
            'cinema_id' => $this->toPositiveInt($input['cinema_id'] ?? null),
            'city' => $this->nullableString($input['city'] ?? null),
            'show_date' => $this->nullableDate($input['show_date'] ?? null),
        ];
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

    private function nullableTime($value)
    {
        $time = $this->nullableString($value);
        if ($time === null) {
            return null;
        }

        foreach (['H:i', 'H:i:s'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat($format, $time);
            if ($parsed && $parsed->format($format) === $time) {
                return $format === 'H:i' ? $time . ':00' : $time;
            }
        }

        return false;
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

    private function normalizeOptionalEnum($value, array $allowed): ?string
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            return null;
        }

        $value = strtolower($value);

        return in_array($value, $allowed, true) ? $value : null;
    }

    private function normalizeArchiveScope($value, ?string $status): string
    {
        $scope = $this->normalizeOptionalEnum($value, self::FILTER_SCOPES);
        if ($scope !== null) {
            return $scope;
        }

        return $status === 'archived' ? 'archived' : 'active';
    }

    private function normalizeScopedStatus(?string $status, string $scope): ?string
    {
        if ($status === null) {
            return null;
        }

        if ($scope === 'archived') {
            return $status === 'archived' ? 'archived' : null;
        }

        if ($scope === 'active' && $status === 'archived') {
            return null;
        }

        return $status;
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
