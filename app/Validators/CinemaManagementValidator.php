<?php

namespace App\Validators;

use App\Core\Validator;
use App\Support\Slugger;

class CinemaManagementValidator
{
    public const FILTER_SCOPES = ['active', 'archived', 'all'];
    public const CINEMA_STATUSES = ['active', 'renovation', 'closed', 'archived'];
    public const ROOM_STATUSES = ['active', 'maintenance', 'closed', 'archived'];
    public const ROOM_TYPES = ['standard_2d', 'premium_3d', 'vip_recliner', 'imax', '4dx', 'screenx', 'dolby_atmos'];
    public const PROJECTION_TYPES = ['digital_4k', 'laser', 'imax_dual', 'motion_rig'];
    public const SOUND_PROFILES = ['stereo', 'dolby_7_1', 'dolby_atmos', 'immersive_360'];
    public const SEAT_TYPES = ['normal', 'vip', 'couple'];
    public const SEAT_STATUSES = ['available', 'maintenance', 'disabled', 'archived'];

    public function validateCinemaPayload(array $input): array
    {
        $errors = Validator::required($input, ['name', 'city', 'address', 'status']);

        $name = $this->cleanString($input['name'] ?? null);
        $slug = Slugger::slugify($input['slug'] ?? $name);
        $city = $this->cleanString($input['city'] ?? null);
        $address = $this->cleanString($input['address'] ?? null);
        $managerName = $this->nullableString($input['manager_name'] ?? null);
        $supportPhone = $this->nullableString($input['support_phone'] ?? null);
        $status = strtolower(trim((string) ($input['status'] ?? '')));
        $openingTime = $this->nullableTime($input['opening_time'] ?? null);
        $closingTime = $this->nullableTime($input['closing_time'] ?? null);
        $latitude = $this->toNullableFloat($input['latitude'] ?? null, 7);
        $longitude = $this->toNullableFloat($input['longitude'] ?? null, 7);
        $description = $this->nullableString($input['description'] ?? null);

        if ($name === '') {
            $errors['name'][] = 'Field is required.';
        }
        if ($slug === '') {
            $errors['slug'][] = 'Slug is required.';
        }
        if ($city === '') {
            $errors['city'][] = 'Field is required.';
        }
        if ($address === '') {
            $errors['address'][] = 'Field is required.';
        }
        if (!in_array($status, self::CINEMA_STATUSES, true)) {
            $errors['status'][] = 'Cinema status is invalid.';
        }
        if ($supportPhone !== null) {
            $phoneError = Validator::phone($supportPhone);
            if ($phoneError !== null) {
                $errors['support_phone'][] = $phoneError;
            }
        }
        if ($openingTime === false) {
            $errors['opening_time'][] = 'Opening time must be a valid HH:MM or HH:MM:SS value.';
        }
        if ($closingTime === false) {
            $errors['closing_time'][] = 'Closing time must be a valid HH:MM or HH:MM:SS value.';
        }
        if (($input['latitude'] ?? null) !== null && ($input['latitude'] ?? '') !== '' && $latitude === null) {
            $errors['latitude'][] = 'Latitude must be numeric.';
        } elseif ($latitude !== null && ($latitude < -90 || $latitude > 90)) {
            $errors['latitude'][] = 'Latitude must be between -90 and 90.';
        }
        if (($input['longitude'] ?? null) !== null && ($input['longitude'] ?? '') !== '' && $longitude === null) {
            $errors['longitude'][] = 'Longitude must be numeric.';
        } elseif ($longitude !== null && ($longitude < -180 || $longitude > 180)) {
            $errors['longitude'][] = 'Longitude must be between -180 and 180.';
        }

        return [
            'data' => [
                'slug' => $slug,
                'name' => $name,
                'city' => $city,
                'address' => $address,
                'manager_name' => $managerName,
                'support_phone' => $supportPhone,
                'status' => $status,
                'opening_time' => $openingTime === false ? null : $openingTime,
                'closing_time' => $closingTime === false ? null : $closingTime,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'description' => $description,
            ],
            'errors' => $errors,
        ];
    }

    public function normalizeCinemaFilters(array $input): array
    {
        $status = $this->normalizeOptionalEnum($input['status'] ?? null, self::CINEMA_STATUSES);
        $scope = $this->normalizeArchiveScope($input['scope'] ?? null, $status);

        return [
            'page' => $this->toPage($input['page'] ?? 1),
            'per_page' => $this->toPerPage($input['per_page'] ?? 20),
            'search' => $this->nullableString($input['search'] ?? null),
            'city' => $this->nullableString($input['city'] ?? null),
            'status' => $this->normalizeScopedStatus($status, $scope),
            'scope' => $scope,
        ];
    }

    public function validateRoomPayload(array $input): array
    {
        $errors = Validator::required($input, [
            'cinema_id',
            'room_name',
            'room_type',
            'screen_label',
            'projection_type',
            'sound_profile',
            'cleaning_buffer_minutes',
            'status',
        ]);

        $cinemaId = $this->toPositiveInt($input['cinema_id'] ?? null);
        $roomName = $this->cleanString($input['room_name'] ?? null);
        $roomType = strtolower(trim((string) ($input['room_type'] ?? '')));
        $screenLabel = $this->cleanString($input['screen_label'] ?? null);
        $projectionType = strtolower(trim((string) ($input['projection_type'] ?? '')));
        $soundProfile = strtolower(trim((string) ($input['sound_profile'] ?? '')));
        $cleaningBufferMinutes = $this->toNonNegativeInt($input['cleaning_buffer_minutes'] ?? 15);
        $status = strtolower(trim((string) ($input['status'] ?? '')));

        if ($cinemaId === null) {
            $errors['cinema_id'][] = 'Cinema ID must be a positive integer.';
        }
        if ($roomName === '') {
            $errors['room_name'][] = 'Field is required.';
        }
        if (!in_array($roomType, self::ROOM_TYPES, true)) {
            $errors['room_type'][] = 'Room type is invalid.';
        }
        if ($screenLabel === '') {
            $errors['screen_label'][] = 'Field is required.';
        }
        if (!in_array($projectionType, self::PROJECTION_TYPES, true)) {
            $errors['projection_type'][] = 'Projection type is invalid.';
        }
        if (!in_array($soundProfile, self::SOUND_PROFILES, true)) {
            $errors['sound_profile'][] = 'Sound profile is invalid.';
        }
        if ($cleaningBufferMinutes === null || $cleaningBufferMinutes > 180) {
            $errors['cleaning_buffer_minutes'][] = 'Cleaning buffer must be between 0 and 180 minutes.';
        }
        if (!in_array($status, self::ROOM_STATUSES, true)) {
            $errors['status'][] = 'Room status is invalid.';
        }

        return [
            'data' => [
                'cinema_id' => $cinemaId,
                'room_name' => $roomName,
                'room_type' => $roomType,
                'screen_label' => $screenLabel,
                'projection_type' => $projectionType,
                'sound_profile' => $soundProfile,
                'cleaning_buffer_minutes' => $cleaningBufferMinutes ?? 15,
                'status' => $status,
            ],
            'errors' => $errors,
        ];
    }

    public function normalizeRoomFilters(array $input): array
    {
        $status = $this->normalizeOptionalEnum($input['status'] ?? null, self::ROOM_STATUSES);
        $scope = $this->normalizeArchiveScope($input['scope'] ?? null, $status);

        return [
            'page' => $this->toPage($input['page'] ?? 1),
            'per_page' => $this->toPerPage($input['per_page'] ?? 20),
            'search' => $this->nullableString($input['search'] ?? null),
            'cinema_id' => $this->toPositiveInt($input['cinema_id'] ?? null),
            'room_type' => $this->normalizeOptionalEnum($input['room_type'] ?? null, self::ROOM_TYPES),
            'status' => $this->normalizeScopedStatus($status, $scope),
            'scope' => $scope,
        ];
    }

    public function validateSeatLayoutPayload(array $input): array
    {
        $seats = $input['seats'] ?? null;
        $errors = [];

        if (!is_array($seats)) {
            $errors['seats'][] = 'Seat layout payload must be an array.';

            return [
                'data' => ['seats' => []],
                'errors' => $errors,
            ];
        }

        $normalizedSeats = [];
        $seenPositions = [];

        foreach ($seats as $index => $seat) {
            if (!is_array($seat)) {
                $errors["seats.{$index}"][] = 'Seat row payload is invalid.';
                continue;
            }

            $seatRow = strtoupper(trim((string) ($seat['seat_row'] ?? '')));
            $seatNumber = $this->toPositiveInt($seat['seat_number'] ?? null);
            $seatType = strtolower(trim((string) ($seat['seat_type'] ?? 'normal')));
            $status = strtolower(trim((string) ($seat['status'] ?? 'available')));

            if ($seatRow === '' || preg_match('/^[A-Z0-9]{1,5}$/', $seatRow) !== 1) {
                $errors["seats.{$index}.seat_row"][] = 'Seat row must contain 1 to 5 uppercase letters or digits.';
            }
            if ($seatNumber === null || $seatNumber > 999) {
                $errors["seats.{$index}.seat_number"][] = 'Seat number must be between 1 and 999.';
            }
            if (!in_array($seatType, self::SEAT_TYPES, true)) {
                $errors["seats.{$index}.seat_type"][] = 'Seat type is invalid.';
            }
            if (!in_array($status, self::SEAT_STATUSES, true)) {
                $errors["seats.{$index}.status"][] = 'Seat status is invalid.';
            }

            if ($seatRow !== '' && $seatNumber !== null) {
                $positionKey = $seatRow . ':' . $seatNumber;
                if (isset($seenPositions[$positionKey])) {
                    $errors["seats.{$index}"][] = 'Duplicate seat position detected in payload.';
                }
                $seenPositions[$positionKey] = true;
            }

            $normalizedSeats[] = [
                'seat_row' => $seatRow,
                'seat_number' => $seatNumber,
                'seat_type' => $seatType,
                'status' => $status,
            ];
        }

        usort($normalizedSeats, static function (array $left, array $right): int {
            return [$left['seat_row'], $left['seat_number']] <=> [$right['seat_row'], $right['seat_number']];
        });

        return [
            'data' => [
                'seats' => $normalizedSeats,
            ],
            'errors' => $errors,
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
