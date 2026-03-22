<?php

namespace App\Validators;

use App\Core\Validator;

class UnifiedCheckoutValidator
{
    private array $shopConfig;
    private array $ticketConfig;

    public function __construct(?array $shopConfig = null, ?array $ticketConfig = null)
    {
        $this->shopConfig = $shopConfig ?? require dirname(__DIR__, 2) . '/config/shop.php';
        $this->ticketConfig = $ticketConfig ?? require dirname(__DIR__, 2) . '/config/tickets.php';
    }

    public function validateCreatePayload(array $payload, ?string $idempotencyKey, array $context): array
    {
        $errors = [];
        $containsProducts = !empty($context['contains_products']);
        $containsTickets = !empty($context['contains_tickets']);
        $contactName = trim((string) ($payload['contact_name'] ?? ''));
        $contactEmail = strtolower(trim((string) ($payload['contact_email'] ?? '')));
        $contactPhone = trim((string) ($payload['contact_phone'] ?? ''));
        $fulfillmentMethod = $this->normalizeEnum(
            $payload['fulfillment_method'] ?? null,
            $this->fulfillmentMethods($containsProducts)
        );
        $paymentMethod = $this->normalizeEnum(
            $payload['payment_method'] ?? null,
            $this->supportedPaymentMethods()
        );
        $shippingAddressText = trim((string) ($payload['shipping_address_text'] ?? ''));
        $shippingCity = trim((string) ($payload['shipping_city'] ?? ''));
        $shippingDistrict = trim((string) ($payload['shipping_district'] ?? ''));
        $normalizedIdempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey ?? ($payload['idempotency_key'] ?? null));

        if (!$containsProducts && !$containsTickets) {
            $errors['checkout'][] = 'Your cart is empty.';
        }

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

        if ($containsProducts && $fulfillmentMethod === 'delivery') {
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
            $allowedMethods = $this->paymentMethodsForContext($containsProducts, $containsTickets, $fulfillmentMethod);
            if (!in_array($paymentMethod, $allowedMethods, true)) {
                $errors['payment_method'][] = 'Selected payment method is not available for the current cart.';
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

    public function fulfillmentMethods(bool $containsProducts): array
    {
        if (!$containsProducts) {
            return ['e_ticket'];
        }

        $methods = $this->shopConfig['orders']['fulfillment_methods'] ?? ['pickup', 'delivery'];

        return array_values(array_filter(array_map([$this, 'normalizeString'], $methods)));
    }

    public function supportedPaymentMethods(): array
    {
        $methods = $this->shopConfig['orders']['supported_payment_methods'] ?? ['cash', 'vnpay'];

        return array_values(array_filter(array_map([$this, 'normalizeString'], $methods)));
    }

    public function paymentMethodsForContext(
        bool $containsProducts,
        bool $containsTickets,
        ?string $fulfillmentMethod
    ): array {
        $normalizedFulfillment = $this->normalizeString($fulfillmentMethod);

        if ($containsProducts && $containsTickets) {
            return in_array('vnpay', $this->supportedPaymentMethods(), true) ? ['vnpay'] : [];
        }

        if ($containsProducts) {
            return $this->paymentMethodsForShopFulfillment($normalizedFulfillment);
        }

        if ($containsTickets) {
            return array_values(array_filter($this->supportedPaymentMethods(), static function (string $code): bool {
                return in_array($code, ['cash', 'vnpay'], true);
            }));
        }

        return [];
    }

    public function paymentMethodAllowedFulfillmentMap(bool $containsProducts, bool $containsTickets): array
    {
        $map = [];
        foreach ($this->supportedPaymentMethods() as $paymentMethod) {
            $map[$paymentMethod] = [];
        }

        if ($containsProducts && $containsTickets) {
            if (isset($map['vnpay'])) {
                $map['vnpay'] = $this->fulfillmentMethods(true);
            }

            return $map;
        }

        if ($containsProducts) {
            foreach ($this->fulfillmentMethods(true) as $fulfillmentMethod) {
                foreach ($this->paymentMethodsForShopFulfillment($fulfillmentMethod) as $paymentMethod) {
                    $map[$paymentMethod][] = $fulfillmentMethod;
                }
            }

            return $map;
        }

        if ($containsTickets) {
            foreach (['cash', 'vnpay'] as $paymentMethod) {
                if (!isset($map[$paymentMethod])) {
                    continue;
                }
                $map[$paymentMethod][] = 'e_ticket';
            }
        }

        return $map;
    }

    public function pendingPaymentTtlMinutes(): int
    {
        $shopTtl = max(1, (int) ($this->shopConfig['orders']['pending_payment_ttl_minutes'] ?? 5));
        $ticketTtl = max(1, (int) ($this->ticketConfig['pending_payment_ttl_minutes'] ?? 5));

        return max($shopTtl, $ticketTtl);
    }

    public function defaultShippingAmount(): float
    {
        return round((float) ($this->shopConfig['orders']['default_shipping_amount'] ?? 0.0), 2);
    }

    private function paymentMethodsForShopFulfillment(string $fulfillmentMethod): array
    {
        $key = $fulfillmentMethod . '_payment_methods';
        $configured = $this->shopConfig['orders'][$key] ?? $this->supportedPaymentMethods();
        $supported = $this->supportedPaymentMethods();

        return array_values(array_filter(array_map([$this, 'normalizeString'], $configured), static function (string $code) use ($supported): bool {
            return in_array($code, $supported, true);
        }));
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
