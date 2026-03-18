<?php

namespace App\Validators;

use App\Core\Validator;

class ShopCheckoutValidator
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? require dirname(__DIR__, 2) . '/config/shop.php';
    }

    public function validateCreatePayload(array $payload, ?string $idempotencyKey = null): array
    {
        $errors = [];
        $contactName = trim((string) ($payload['contact_name'] ?? ''));
        $contactEmail = strtolower(trim((string) ($payload['contact_email'] ?? '')));
        $contactPhone = trim((string) ($payload['contact_phone'] ?? ''));
        $fulfillmentMethod = $this->normalizeEnum($payload['fulfillment_method'] ?? null, $this->fulfillmentMethods());
        $paymentMethod = $this->normalizeEnum($payload['payment_method'] ?? null, $this->supportedPaymentMethods());
        $shippingAddressText = trim((string) ($payload['shipping_address_text'] ?? ''));
        $shippingCity = trim((string) ($payload['shipping_city'] ?? ''));
        $shippingDistrict = trim((string) ($payload['shipping_district'] ?? ''));
        $normalizedIdempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey ?? ($payload['idempotency_key'] ?? null));

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

        if ($fulfillmentMethod === null) {
            $errors['fulfillment_method'][] = 'Fulfillment method is invalid.';
        }

        if ($paymentMethod === null) {
            $errors['payment_method'][] = 'Payment method is invalid.';
        }

        if ($normalizedIdempotencyKey === null) {
            $errors['idempotency_key'][] = 'Idempotency key is required.';
        }

        if ($fulfillmentMethod === 'delivery') {
            if ($shippingAddressText === '') {
                $errors['shipping_address_text'][] = 'Delivery address is required for delivery orders.';
            } elseif (mb_strlen($shippingAddressText) > 500) {
                $errors['shipping_address_text'][] = 'Delivery address must be 500 characters or fewer.';
            }

            if ($shippingCity === '') {
                $errors['shipping_city'][] = 'City is required for delivery orders.';
            } elseif (mb_strlen($shippingCity) > 120) {
                $errors['shipping_city'][] = 'City must be 120 characters or fewer.';
            }

            if ($shippingDistrict === '') {
                $errors['shipping_district'][] = 'District is required for delivery orders.';
            } elseif (mb_strlen($shippingDistrict) > 120) {
                $errors['shipping_district'][] = 'District must be 120 characters or fewer.';
            }
        } else {
            $shippingAddressText = '';
            $shippingCity = '';
            $shippingDistrict = '';
        }

        if ($paymentMethod !== null && $fulfillmentMethod !== null) {
            $allowedMethods = $this->paymentMethodsForFulfillment($fulfillmentMethod);
            if (!in_array($paymentMethod, $allowedMethods, true)) {
                $errors['payment_method'][] = 'Selected payment method is not available for this fulfillment method.';
            }
        }

        return [
            'data' => [
                'contact_name' => $contactName,
                'contact_email' => $contactEmail,
                'contact_phone' => $contactPhone,
                'fulfillment_method' => $fulfillmentMethod,
                'payment_method' => $paymentMethod,
                'shipping_address_text' => $shippingAddressText !== '' ? $shippingAddressText : null,
                'shipping_city' => $shippingCity !== '' ? $shippingCity : null,
                'shipping_district' => $shippingDistrict !== '' ? $shippingDistrict : null,
                'idempotency_key' => $normalizedIdempotencyKey,
            ],
            'errors' => $errors,
        ];
    }

    public function fulfillmentMethods(): array
    {
        $methods = $this->config['orders']['fulfillment_methods'] ?? ['pickup', 'delivery'];

        return array_values(array_filter(array_map([$this, 'normalizeString'], $methods)));
    }

    public function supportedPaymentMethods(): array
    {
        $methods = $this->config['orders']['supported_payment_methods'] ?? ['cash', 'vnpay'];

        return array_values(array_filter(array_map([$this, 'normalizeString'], $methods)));
    }

    public function paymentMethodsForFulfillment(string $fulfillmentMethod): array
    {
        $normalizedFulfillment = $this->normalizeString($fulfillmentMethod);
        $key = $normalizedFulfillment . '_payment_methods';
        $configured = $this->config['orders'][$key] ?? $this->supportedPaymentMethods();
        $supported = $this->supportedPaymentMethods();

        return array_values(array_filter(array_map([$this, 'normalizeString'], $configured), static function (string $code) use ($supported): bool {
            return in_array($code, $supported, true);
        }));
    }

    public function paymentMethodAllowedFulfillmentMap(): array
    {
        $map = [];
        foreach ($this->supportedPaymentMethods() as $paymentMethod) {
            $map[$paymentMethod] = [];
            foreach ($this->fulfillmentMethods() as $fulfillmentMethod) {
                if (in_array($paymentMethod, $this->paymentMethodsForFulfillment($fulfillmentMethod), true)) {
                    $map[$paymentMethod][] = $fulfillmentMethod;
                }
            }
        }

        return $map;
    }

    public function pendingPaymentTtlMinutes(): int
    {
        return max(1, (int) ($this->config['orders']['pending_payment_ttl_minutes'] ?? 5));
    }

    public function defaultShippingAmount(): float
    {
        return round((float) ($this->config['orders']['default_shipping_amount'] ?? 0.0), 2);
    }

    private function normalizeIdempotencyKey($value): ?string
    {
        $normalized = trim((string) ($value ?? ''));
        if ($normalized === '') {
            return null;
        }

        if (strlen($normalized) < 8 || strlen($normalized) > 120) {
            return null;
        }

        return preg_match('/^[A-Za-z0-9._:-]+$/', $normalized) === 1 ? $normalized : null;
    }

    private function normalizeEnum($value, array $allowed): ?string
    {
        $normalized = $this->normalizeString($value);
        if ($normalized === '') {
            return null;
        }

        return in_array($normalized, $allowed, true) ? $normalized : null;
    }

    private function normalizeString($value): string
    {
        return strtolower(trim((string) ($value ?? '')));
    }
}
