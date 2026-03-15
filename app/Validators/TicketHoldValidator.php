<?php

namespace App\Validators;

use App\Core\Validator;

class TicketHoldValidator
{
    private const MAX_SEATS_PER_HOLD = 10;

    public function validateCreatePayload(array $input): array
    {
        $errors = Validator::required($input, ['showtime_id']);
        if (!array_key_exists('seat_ids', $input)) {
            $errors['seat_ids'][] = 'Field is required.';
        }

        $showtimeId = $this->toPositiveInt($input['showtime_id'] ?? null);
        $seatIds = $this->normalizeSeatIds($input['seat_ids'] ?? null);

        if ($showtimeId === null) {
            $errors['showtime_id'][] = 'Showtime ID must be a positive integer.';
        }
        if ($seatIds === null) {
            $errors['seat_ids'][] = 'Seat IDs must be a non-empty array of positive integers.';
        } elseif (count($seatIds) > self::MAX_SEATS_PER_HOLD) {
            $errors['seat_ids'][] = 'A single hold can reserve up to 10 seats.';
        }

        return [
            'data' => [
                'showtime_id' => $showtimeId,
                'seat_ids' => $seatIds ?? [],
            ],
            'errors' => $errors,
        ];
    }

    public function validateReleaseShowtimeId($value): array
    {
        $showtimeId = $this->toPositiveInt($value);
        if ($showtimeId === null) {
            return [
                'data' => ['showtime_id' => null],
                'errors' => ['showtime' => ['Showtime not found.']],
            ];
        }

        return [
            'data' => ['showtime_id' => $showtimeId],
            'errors' => [],
        ];
    }

    private function normalizeSeatIds($value): ?array
    {
        if (!is_array($value) || $value === []) {
            return null;
        }

        $normalized = [];
        foreach ($value as $seatId) {
            $intValue = $this->toPositiveInt($seatId);
            if ($intValue === null) {
                return null;
            }

            $normalized[$intValue] = $intValue;
        }

        return array_values($normalized);
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
}
