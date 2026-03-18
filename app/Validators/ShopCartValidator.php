<?php

namespace App\Validators;

use App\Core\Validator;

class ShopCartValidator
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? require dirname(__DIR__, 2) . '/config/shop.php';
    }

    public function validateAddItemPayload(array $input): array
    {
        $errors = Validator::required($input, ['product_id', 'quantity']);

        $productId = $this->toPositiveInt($input['product_id'] ?? null);
        $quantity = $this->toPositiveInt($input['quantity'] ?? null);

        if ($productId === null) {
            $errors['product_id'][] = 'Product ID must be a positive integer.';
        }
        if ($quantity === null) {
            $errors['quantity'][] = 'Quantity must be a positive integer.';
        }

        return [
            'data' => [
                'product_id' => $productId,
                'quantity' => $quantity ?? 1,
            ],
            'errors' => $errors,
        ];
    }

    public function validateUpdateItemPayload(array $input): array
    {
        $errors = Validator::required($input, ['quantity']);
        $quantity = $this->toPositiveInt($input['quantity'] ?? null);

        if ($quantity === null) {
            $errors['quantity'][] = 'Quantity must be a positive integer.';
        }

        return [
            'data' => [
                'quantity' => $quantity ?? 1,
            ],
            'errors' => $errors,
        ];
    }

    public function normalizeProductId($value): ?int
    {
        return $this->toPositiveInt($value);
    }

    public function maxCartItems(): int
    {
        return max(1, (int) ($this->config['cart']['max_items'] ?? 50));
    }

    public function maxQuantityPerItem(): int
    {
        return max(1, (int) ($this->config['cart']['max_quantity_per_item'] ?? 10));
    }

    public function cartTtlMinutes(): int
    {
        return max(1, (int) ($this->config['cart']['ttl_minutes'] ?? (60 * 24 * 7)));
    }

    public function cartCookieName(): string
    {
        $cookieName = trim((string) ($this->config['cart']['cookie_name'] ?? 'cinemax_cart'));

        return $cookieName !== '' ? $cookieName : 'cinemax_cart';
    }

    public function cartSessionTokenBytes(): int
    {
        return max(16, min(64, (int) ($this->config['cart']['session_token_bytes'] ?? 32)));
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
