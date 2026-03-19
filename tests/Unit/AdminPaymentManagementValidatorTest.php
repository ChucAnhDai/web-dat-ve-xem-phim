<?php

namespace Tests\Unit;

use App\Validators\AdminPaymentManagementValidator;
use PHPUnit\Framework\TestCase;

class AdminPaymentManagementValidatorTest extends TestCase
{
    public function testNormalizePaymentFiltersFallsBackToSafeDefaults(): void
    {
        $validator = new AdminPaymentManagementValidator();

        $result = $validator->normalizePaymentFilters([
            'search' => str_repeat('A', 150),
            'payment_method' => 'VNPay',
            'payment_status' => 'UNKNOWN',
            'scope' => 'ticket',
            'page' => '0',
            'per_page' => '500',
        ]);

        $this->assertSame(str_repeat('A', 120), $result['search']);
        $this->assertSame('vnpay', $result['payment_method']);
        $this->assertNull($result['payment_status']);
        $this->assertSame('ticket', $result['scope']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(100, $result['per_page']);
    }

    public function testValidatePaymentMethodPayloadAcceptsValidMetadata(): void
    {
        $validator = new AdminPaymentManagementValidator();

        $result = $validator->validatePaymentMethodPayload([
            'code' => 'stripe',
            'name' => 'Stripe Gateway',
            'provider' => 'stripe',
            'channel_type' => 'gateway',
            'status' => 'active',
            'fee_rate_percent' => '2.9',
            'fixed_fee_amount' => '1000',
            'settlement_cycle' => 'T+2',
            'supports_refund' => '1',
            'supports_webhook' => 'true',
            'supports_redirect' => 'yes',
            'display_order' => '5',
            'description' => 'Card processor',
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame('stripe', $result['data']['code']);
        $this->assertSame('Stripe Gateway', $result['data']['name']);
        $this->assertSame(2.9, $result['data']['fee_rate_percent']);
        $this->assertSame(1000.0, $result['data']['fixed_fee_amount']);
        $this->assertTrue($result['data']['supports_refund']);
        $this->assertTrue($result['data']['supports_webhook']);
        $this->assertTrue($result['data']['supports_redirect']);
        $this->assertSame(5, $result['data']['display_order']);
    }

    public function testValidatePaymentMethodPayloadRejectsInvalidFields(): void
    {
        $validator = new AdminPaymentManagementValidator();

        $result = $validator->validatePaymentMethodPayload([
            'code' => 'Stripe Card',
            'name' => '',
            'provider' => 'Stripe Provider',
            'channel_type' => 'bank',
            'status' => 'draft',
            'fee_rate_percent' => '150',
            'fixed_fee_amount' => '-1',
            'settlement_cycle' => '',
            'display_order' => 'abc',
        ]);

        $this->assertArrayHasKey('code', $result['errors']);
        $this->assertArrayHasKey('name', $result['errors']);
        $this->assertArrayHasKey('provider', $result['errors']);
        $this->assertArrayHasKey('channel_type', $result['errors']);
        $this->assertArrayHasKey('status', $result['errors']);
        $this->assertArrayHasKey('fee_rate_percent', $result['errors']);
        $this->assertArrayHasKey('fixed_fee_amount', $result['errors']);
        $this->assertArrayHasKey('settlement_cycle', $result['errors']);
        $this->assertArrayHasKey('display_order', $result['errors']);
    }
}
