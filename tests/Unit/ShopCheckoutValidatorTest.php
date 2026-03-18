<?php

namespace Tests\Unit;

use App\Validators\ShopCheckoutValidator;
use PHPUnit\Framework\TestCase;

class ShopCheckoutValidatorTest extends TestCase
{
    public function testValidateCreatePayloadAcceptsPickupCashCheckout(): void
    {
        $validator = new ShopCheckoutValidator();

        $result = $validator->validateCreatePayload([
            'contact_name' => 'Checkout User',
            'contact_email' => 'checkout@example.com',
            'contact_phone' => '0901234567',
            'fulfillment_method' => 'pickup',
            'payment_method' => 'cash',
        ], 'shop-checkout-001');

        $this->assertSame([], $result['errors']);
        $this->assertSame('pickup', $result['data']['fulfillment_method']);
        $this->assertSame('cash', $result['data']['payment_method']);
        $this->assertNull($result['data']['shipping_address_text']);
    }

    public function testValidateCreatePayloadRejectsDeliveryWithCash(): void
    {
        $validator = new ShopCheckoutValidator();

        $result = $validator->validateCreatePayload([
            'contact_name' => 'Checkout User',
            'contact_email' => 'checkout@example.com',
            'contact_phone' => '0901234567',
            'fulfillment_method' => 'delivery',
            'payment_method' => 'cash',
            'shipping_address_text' => '123 Test Street',
            'shipping_city' => 'Ho Chi Minh City',
            'shipping_district' => 'District 1',
        ], 'shop-checkout-002');

        $this->assertArrayHasKey('payment_method', $result['errors']);
        $this->assertContains(
            'Selected payment method is not available for this fulfillment method.',
            $result['errors']['payment_method']
        );
    }

    public function testValidateCreatePayloadRequiresIdempotencyKey(): void
    {
        $validator = new ShopCheckoutValidator();

        $result = $validator->validateCreatePayload([
            'contact_name' => 'Checkout User',
            'contact_email' => 'checkout@example.com',
            'contact_phone' => '0901234567',
            'fulfillment_method' => 'pickup',
            'payment_method' => 'vnpay',
        ], null);

        $this->assertArrayHasKey('idempotency_key', $result['errors']);
    }
}
