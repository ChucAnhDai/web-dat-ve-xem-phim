<?php

namespace App\Validators;

use App\Core\Validator;

class TicketOrderValidator
{
    private const PAYMENT_METHODS = ['momo', 'vnpay', 'paypal', 'cash'];
    private const FULFILLMENT_METHODS = ['e_ticket', 'counter_pickup'];
    private const ORDER_STATUSES = ['pending', 'paid', 'cancelled', 'expired', 'refunded'];
    private const TICKET_STATUSES = ['pending', 'paid', 'cancelled', 'expired', 'refunded', 'used'];

    public function validatePreviewPayload(array $payload): array
    {
        $errors = [];
        $showtimeId = $this->normalizePositiveInt($payload['showtime_id'] ?? null);
        $seatIds = $this->normalizeSeatIds($payload['seat_ids'] ?? []);
        $paymentMethodInput = trim((string) ($payload['payment_method'] ?? ''));
        $fulfillmentMethodInput = trim((string) ($payload['fulfillment_method'] ?? ''));
        $paymentMethod = $paymentMethodInput === ''
            ? 'momo'
            : $this->normalizeEnum($paymentMethodInput, self::PAYMENT_METHODS);
        $fulfillmentMethod = $fulfillmentMethodInput === ''
            ? 'e_ticket'
            : $this->normalizeEnum($fulfillmentMethodInput, self::FULFILLMENT_METHODS);

        if ($showtimeId <= 0) {
            $errors['showtime_id'][] = 'Showtime is required.';
        }
        if ($seatIds === null) {
            $errors['seat_ids'][] = 'Seat selection is invalid.';
        }
        if ($paymentMethod === null) {
            $errors['payment_method'][] = 'Payment method is invalid.';
        }
        if ($fulfillmentMethod === null) {
            $errors['fulfillment_method'][] = 'Fulfillment method is invalid.';
        }

        return [
            'data' => [
                'showtime_id' => $showtimeId,
                'seat_ids' => $seatIds ?? [],
                'payment_method' => $paymentMethod,
                'fulfillment_method' => $fulfillmentMethod,
            ],
            'errors' => $errors,
        ];
    }

    public function validateCreatePayload(array $payload): array
    {
        $preview = $this->validatePreviewPayload($payload);
        $errors = $preview['errors'];

        $contactName = trim((string) ($payload['contact_name'] ?? ''));
        $contactEmail = strtolower(trim((string) ($payload['contact_email'] ?? '')));
        $contactPhone = trim((string) ($payload['contact_phone'] ?? ''));
        $paymentMethod = $preview['data']['payment_method'];
        $fulfillmentMethod = $preview['data']['fulfillment_method'];

        if ($contactName === '') {
            $errors['contact_name'][] = 'Contact name is required.';
        } elseif (mb_strlen($contactName) > 120) {
            $errors['contact_name'][] = 'Contact name must be 120 characters or fewer.';
        }

        $emailError = Validator::email($contactEmail);
        if ($contactEmail === '') {
            $errors['contact_email'][] = 'Contact email is required.';
        } elseif ($emailError !== null) {
            $errors['contact_email'][] = $emailError;
        }

        $phoneError = Validator::phone($contactPhone);
        if ($contactPhone === '') {
            $errors['contact_phone'][] = 'Contact phone is required.';
        } elseif ($phoneError !== null) {
            $errors['contact_phone'][] = $phoneError;
        }

        if ($paymentMethod === null) {
            $errors['payment_method'][] = 'Payment method is invalid.';
        }
        if ($fulfillmentMethod === null) {
            $errors['fulfillment_method'][] = 'Fulfillment method is invalid.';
        }

        return [
            'data' => [
                'showtime_id' => $preview['data']['showtime_id'],
                'seat_ids' => $preview['data']['seat_ids'],
                'contact_name' => $contactName,
                'contact_email' => $contactEmail,
                'contact_phone' => $contactPhone,
                'payment_method' => $paymentMethod,
                'fulfillment_method' => $fulfillmentMethod,
            ],
            'errors' => $errors,
        ];
    }

    public function normalizeUserTicketFilters(array $filters): array
    {
        return [
            'search' => $this->normalizeSearch($filters['search'] ?? ''),
            'status' => $this->normalizeEnum($filters['status'] ?? null, self::TICKET_STATUSES),
            'page' => $this->normalizePage($filters['page'] ?? 1),
            'per_page' => $this->normalizePerPage($filters['per_page'] ?? 20, 50),
        ];
    }

    public function normalizeUserOrderFilters(array $filters): array
    {
        return [
            'search' => $this->normalizeSearch($filters['search'] ?? ''),
            'status' => $this->normalizeEnum($filters['status'] ?? null, self::ORDER_STATUSES),
            'payment_method' => $this->normalizeEnum($filters['payment_method'] ?? null, self::PAYMENT_METHODS),
            'page' => $this->normalizePage($filters['page'] ?? 1),
            'per_page' => $this->normalizePerPage($filters['per_page'] ?? 20, 50),
        ];
    }

    public function normalizeAdminOrderFilters(array $filters): array
    {
        return [
            'search' => $this->normalizeSearch($filters['search'] ?? ''),
            'status' => $this->normalizeEnum($filters['status'] ?? null, self::ORDER_STATUSES),
            'payment_method' => $this->normalizeEnum($filters['payment_method'] ?? null, self::PAYMENT_METHODS),
            'page' => $this->normalizePage($filters['page'] ?? 1),
            'per_page' => $this->normalizePerPage($filters['per_page'] ?? 20, 100),
        ];
    }

    public function normalizeAdminTicketFilters(array $filters): array
    {
        return [
            'search' => $this->normalizeSearch($filters['search'] ?? ''),
            'status' => $this->normalizeEnum($filters['status'] ?? null, self::TICKET_STATUSES),
            'page' => $this->normalizePage($filters['page'] ?? 1),
            'per_page' => $this->normalizePerPage($filters['per_page'] ?? 20, 100),
        ];
    }

    public function normalizeHoldFilters(array $filters): array
    {
        return [
            'limit' => max(1, min(100, (int) ($filters['limit'] ?? 20))),
        ];
    }

    private function normalizeSeatIds($value): ?array
    {
        if (is_string($value)) {
            $value = array_filter(array_map('trim', explode(',', $value)), static function ($item): bool {
                return $item !== '';
            });
        }

        if (!is_array($value)) {
            return null;
        }

        $seatIds = [];
        foreach ($value as $item) {
            $seatId = $this->normalizePositiveInt($item);
            if ($seatId <= 0) {
                return null;
            }
            $seatIds[$seatId] = $seatId;
        }

        return array_values($seatIds);
    }

    private function normalizeEnum($value, array $allowed): ?string
    {
        $normalized = strtolower(trim((string) ($value ?? '')));
        if ($normalized === '' || $normalized === 'all') {
            return null;
        }

        return in_array($normalized, $allowed, true) ? $normalized : null;
    }

    private function normalizePositiveInt($value): int
    {
        $normalized = filter_var($value, FILTER_VALIDATE_INT);

        return $normalized !== false && $normalized > 0 ? (int) $normalized : 0;
    }

    private function normalizeSearch($value): string
    {
        $search = trim((string) ($value ?? ''));
        if ($search === '') {
            return '';
        }

        return mb_substr($search, 0, 120);
    }

    private function normalizePage($value): int
    {
        return max(1, (int) ($value ?: 1));
    }

    private function normalizePerPage($value, int $max): int
    {
        $perPage = (int) ($value ?: 20);

        return max(1, min($max, $perPage));
    }
}
