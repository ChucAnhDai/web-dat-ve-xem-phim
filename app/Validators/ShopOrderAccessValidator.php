<?php

namespace App\Validators;

use App\Core\Validator;

class ShopOrderAccessValidator
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? require dirname(__DIR__, 2) . '/config/shop.php';
    }

    public function normalizeOrderFilters(array $filters): array
    {
        return [
            'search' => $this->normalizeSearch($filters['search'] ?? ''),
            'status' => $this->normalizeEnum($filters['status'] ?? null, $this->orderStatuses()),
            'payment_method' => $this->normalizeEnum($filters['payment_method'] ?? null, $this->paymentMethods()),
            'page' => $this->normalizePage($filters['page'] ?? 1),
            'per_page' => $this->normalizePerPage($filters['per_page'] ?? 20, 100),
        ];
    }

    public function validateLookupPayload(array $payload): array
    {
        $errors = [];
        $orderCode = $this->normalizeOrderCode($payload['order_code'] ?? null);
        $contactEmail = $this->normalizeEmail($payload['contact_email'] ?? null);
        $contactPhone = $this->normalizePhone($payload['contact_phone'] ?? null);

        if ($orderCode === null) {
            $errors['order_code'][] = 'Order code is required.';
        }

        if ($contactEmail === null && $contactPhone === null) {
            $errors['lookup'][] = 'Provide the checkout email or phone number used for this order.';
        }

        if (($payload['contact_email'] ?? null) !== null && trim((string) $payload['contact_email']) !== '' && $contactEmail === null) {
            $errors['contact_email'][] = 'Contact email is invalid.';
        }

        if (($payload['contact_phone'] ?? null) !== null && trim((string) $payload['contact_phone']) !== '' && $contactPhone === null) {
            $errors['contact_phone'][] = 'Contact phone is invalid.';
        }

        return [
            'data' => [
                'order_code' => $orderCode,
                'contact_email' => $contactEmail,
                'contact_phone' => $contactPhone,
            ],
            'errors' => $errors,
        ];
    }

    public function orderStatuses(): array
    {
        $statuses = $this->config['orders']['statuses'] ?? [];

        return array_values(array_filter(array_map([$this, 'normalizeString'], $statuses)));
    }

    public function paymentMethods(): array
    {
        $methods = $this->config['orders']['supported_payment_methods'] ?? [];

        return array_values(array_filter(array_map([$this, 'normalizeString'], $methods)));
    }

    public function cartCookieName(): string
    {
        return (string) ($this->config['cart']['cookie_name'] ?? 'cinemax_cart');
    }

    private function normalizeOrderCode($value): ?string
    {
        $normalized = strtoupper(trim((string) ($value ?? '')));
        if ($normalized === '') {
            return null;
        }

        if (strlen($normalized) > 40) {
            return null;
        }

        return preg_match('/^[A-Z0-9._-]+$/', $normalized) === 1 ? $normalized : null;
    }

    private function normalizeEmail($value): ?string
    {
        $normalized = strtolower(trim((string) ($value ?? '')));
        if ($normalized === '') {
            return null;
        }

        return Validator::email($normalized) === null ? $normalized : null;
    }

    private function normalizePhone($value): ?string
    {
        $normalized = trim((string) ($value ?? ''));
        if ($normalized === '') {
            return null;
        }

        return Validator::phone($normalized) === null ? $normalized : null;
    }

    private function normalizeEnum($value, array $allowed): ?string
    {
        $normalized = $this->normalizeString($value);
        if ($normalized === '' || $normalized === 'all') {
            return null;
        }

        return in_array($normalized, $allowed, true) ? $normalized : null;
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

    private function normalizeString($value): string
    {
        return strtolower(trim((string) ($value ?? '')));
    }
}
