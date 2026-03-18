<?php

namespace App\Validators;

class ShopOrderAdminValidator
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
            'fulfillment_method' => $this->normalizeEnum($filters['fulfillment_method'] ?? null, $this->fulfillmentMethods()),
            'page' => $this->normalizePage($filters['page'] ?? 1),
            'per_page' => $this->normalizePerPage($filters['per_page'] ?? 20, 100),
        ];
    }

    public function normalizeOrderDetailFilters(array $filters): array
    {
        return [
            'search' => $this->normalizeSearch($filters['search'] ?? ''),
            'status' => $this->normalizeEnum($filters['status'] ?? null, $this->orderStatuses()),
            'payment_method' => $this->normalizeEnum($filters['payment_method'] ?? null, $this->paymentMethods()),
            'fulfillment_method' => $this->normalizeEnum($filters['fulfillment_method'] ?? null, $this->fulfillmentMethods()),
            'page' => $this->normalizePage($filters['page'] ?? 1),
            'per_page' => $this->normalizePerPage($filters['per_page'] ?? 20, 100),
            'queue_limit' => max(1, min(20, (int) ($filters['queue_limit'] ?? 8))),
        ];
    }

    public function validateStatusUpdatePayload(array $payload): array
    {
        $errors = [];
        $status = $this->normalizeEnum($payload['status'] ?? null, $this->manageableStatuses());

        if ($status === null) {
            $errors['status'][] = 'Order status is invalid.';
        }

        return [
            'data' => [
                'status' => $status,
            ],
            'errors' => $errors,
        ];
    }

    public function orderStatuses(): array
    {
        $statuses = $this->config['orders']['statuses'] ?? [];

        return array_values(array_filter(array_map([$this, 'normalizeString'], $statuses)));
    }

    public function manageableStatuses(): array
    {
        return ['confirmed', 'preparing', 'ready', 'shipping', 'completed', 'cancelled'];
    }

    public function paymentMethods(): array
    {
        $methods = $this->config['orders']['supported_payment_methods'] ?? [];

        return array_values(array_filter(array_map([$this, 'normalizeString'], $methods)));
    }

    public function fulfillmentMethods(): array
    {
        $methods = $this->config['orders']['fulfillment_methods'] ?? [];

        return array_values(array_filter(array_map([$this, 'normalizeString'], $methods)));
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
