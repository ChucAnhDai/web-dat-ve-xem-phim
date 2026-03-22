<?php

namespace App\Validators;

use App\Core\Validator;

class CustomerOrderAccessValidator
{
    public function normalizeFilters(array $filters): array
    {
        $status = strtolower(trim((string) ($filters['status'] ?? 'all')));
        if (!in_array($status, ['all', 'pending', 'active', 'completed', 'issue'], true)) {
            $status = 'all';
        }

        return [
            'search' => mb_substr(trim((string) ($filters['search'] ?? '')), 0, 120),
            'status' => $status,
            'page' => max(1, (int) ($filters['page'] ?? 1)),
            'per_page' => max(1, min(100, (int) ($filters['per_page'] ?? 20))),
        ];
    }

    public function validateLookupPayload(array $payload): array
    {
        $errors = [];
        $orderCode = $this->normalizeOrderCode($payload['order_code'] ?? null);
        $contactEmail = $this->normalizeEmail($payload['contact_email'] ?? null);
        $contactPhone = $this->normalizePhone($payload['contact_phone'] ?? null);
        $rawEmail = trim((string) ($payload['contact_email'] ?? ''));
        $rawPhone = trim((string) ($payload['contact_phone'] ?? ''));

        if ($orderCode === null) {
            $errors['order_code'][] = 'Order code is required.';
        }

        if ($rawEmail === '') {
            $errors['contact_email'][] = 'Checkout email is required.';
        } elseif ($contactEmail === null) {
            $errors['contact_email'][] = 'Contact email is invalid.';
        }

        if ($rawPhone === '') {
            $errors['contact_phone'][] = 'Checkout phone is required.';
        } elseif ($contactPhone === null) {
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
}
