<?php

namespace App\Validators;

class AdminPaymentManagementValidator
{
    private const PAYMENT_STATUSES = [
        'pending',
        'processing',
        'success',
        'failed',
        'cancelled',
        'expired',
        'refunded',
    ];

    private const PAYMENT_METHOD_STATUSES = [
        'active',
        'maintenance',
        'disabled',
    ];

    private const CHANNEL_TYPES = [
        'e_wallet',
        'gateway',
        'international',
        'counter',
    ];

    public function normalizePaymentFilters(array $filters): array
    {
        return [
            'search' => $this->normalizeSearch($filters['search'] ?? ''),
            'payment_method' => $this->normalizeCode($filters['payment_method'] ?? null),
            'payment_status' => $this->normalizeEnum($filters['payment_status'] ?? null, self::PAYMENT_STATUSES),
            'scope' => $this->normalizeEnum($filters['scope'] ?? null, ['ticket', 'shop']),
            'page' => $this->normalizePage($filters['page'] ?? 1),
            'per_page' => $this->normalizePerPage($filters['per_page'] ?? 20, 100),
        ];
    }

    public function normalizePaymentMethodFilters(array $filters): array
    {
        return [
            'search' => $this->normalizeSearch($filters['search'] ?? ''),
            'status' => $this->normalizeEnum($filters['status'] ?? null, self::PAYMENT_METHOD_STATUSES),
            'channel_type' => $this->normalizeEnum($filters['channel_type'] ?? null, self::CHANNEL_TYPES),
            'page' => $this->normalizePage($filters['page'] ?? 1),
            'per_page' => $this->normalizePerPage($filters['per_page'] ?? 20, 100),
        ];
    }

    public function validatePaymentMethodPayload(array $payload, bool $requireCode = true): array
    {
        $errors = [];
        $code = $this->normalizeCode($payload['code'] ?? null);
        $name = $this->normalizeLabel($payload['name'] ?? null, 100);
        $provider = $this->normalizeProvider($payload['provider'] ?? null);
        $channelType = $this->normalizeEnum($payload['channel_type'] ?? null, self::CHANNEL_TYPES);
        $status = $this->normalizeEnum($payload['status'] ?? null, self::PAYMENT_METHOD_STATUSES);
        $feeRatePercent = $this->normalizeDecimal($payload['fee_rate_percent'] ?? null, 0, 100);
        $fixedFeeAmount = $this->normalizeDecimal($payload['fixed_fee_amount'] ?? null, 0, 99999999);
        $settlementCycle = $this->normalizeLabel($payload['settlement_cycle'] ?? null, 20);
        $displayOrder = $this->normalizeOptionalInt($payload['display_order'] ?? null, 0, 9999);
        $description = $this->normalizeNullableText($payload['description'] ?? null, 255);

        if ($requireCode && $code === null) {
            $errors['code'][] = 'Payment method code is invalid.';
        }

        if ($name === null) {
            $errors['name'][] = 'Payment method name is required.';
        }

        if ($provider === null) {
            $errors['provider'][] = 'Payment provider is invalid.';
        }

        if ($channelType === null) {
            $errors['channel_type'][] = 'Payment channel type is invalid.';
        }

        if ($status === null) {
            $errors['status'][] = 'Payment method status is invalid.';
        }

        if ($feeRatePercent === null) {
            $errors['fee_rate_percent'][] = 'Fee rate percent must be between 0 and 100.';
        }

        if ($fixedFeeAmount === null) {
            $errors['fixed_fee_amount'][] = 'Fixed fee amount must be zero or greater.';
        }

        if ($settlementCycle === null) {
            $errors['settlement_cycle'][] = 'Settlement cycle is required.';
        }

        if ($displayOrder === null && trim((string) ($payload['display_order'] ?? '')) !== '') {
            $errors['display_order'][] = 'Display order is invalid.';
        }

        return [
            'data' => [
                'code' => $code,
                'name' => $name,
                'provider' => $provider,
                'channel_type' => $channelType,
                'status' => $status,
                'fee_rate_percent' => $feeRatePercent,
                'fixed_fee_amount' => $fixedFeeAmount,
                'settlement_cycle' => $settlementCycle,
                'supports_refund' => $this->normalizeBoolean($payload['supports_refund'] ?? null),
                'supports_webhook' => $this->normalizeBoolean($payload['supports_webhook'] ?? null),
                'supports_redirect' => $this->normalizeBoolean($payload['supports_redirect'] ?? null),
                'display_order' => $displayOrder,
                'description' => $description,
            ],
            'errors' => $errors,
        ];
    }

    private function normalizeCode($value): ?string
    {
        $normalized = strtolower(trim((string) ($value ?? '')));
        if ($normalized === '') {
            return null;
        }

        if (strlen($normalized) > 30) {
            return null;
        }

        return preg_match('/^[a-z0-9_-]+$/', $normalized) === 1 ? $normalized : null;
    }

    private function normalizeProvider($value): ?string
    {
        $normalized = strtolower(trim((string) ($value ?? '')));
        if ($normalized === '') {
            return null;
        }

        if (strlen($normalized) > 50) {
            return null;
        }

        return preg_match('/^[a-z0-9._-]+$/', $normalized) === 1 ? $normalized : null;
    }

    private function normalizeLabel($value, int $maxLength): ?string
    {
        $normalized = trim((string) ($value ?? ''));
        if ($normalized === '') {
            return null;
        }

        return mb_substr($normalized, 0, $maxLength);
    }

    private function normalizeNullableText($value, int $maxLength): ?string
    {
        $normalized = trim((string) ($value ?? ''));
        if ($normalized === '') {
            return null;
        }

        return mb_substr($normalized, 0, $maxLength);
    }

    private function normalizeEnum($value, array $allowed): ?string
    {
        $normalized = strtolower(trim((string) ($value ?? '')));
        if ($normalized === '' || $normalized === 'all') {
            return null;
        }

        return in_array($normalized, $allowed, true) ? $normalized : null;
    }

    private function normalizeDecimal($value, float $min, float $max): ?float
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return 0.0;
        }

        if (!is_numeric($raw)) {
            return null;
        }

        $normalized = round((float) $raw, 2);
        if ($normalized < $min || $normalized > $max) {
            return null;
        }

        return $normalized;
    }

    private function normalizeOptionalInt($value, int $min, int $max): ?int
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        if (!preg_match('/^-?\d+$/', $raw)) {
            return null;
        }

        $normalized = (int) $raw;
        if ($normalized < $min || $normalized > $max) {
            return null;
        }

        return $normalized;
    }

    private function normalizeBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) ($value ?? '')));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
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
