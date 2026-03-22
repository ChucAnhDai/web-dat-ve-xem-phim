<?php

namespace Tests\Unit;

use App\Validators\UnifiedCheckoutValidator;
use PHPUnit\Framework\TestCase;

class UnifiedCheckoutValidatorTest extends TestCase
{
    public function testValidateCreatePayloadRejectsCashForMixedCheckout(): void
    {
        $validator = new UnifiedCheckoutValidator();

        $result = $validator->validateCreatePayload([
            'contact_name' => 'Mixed Checkout',
            'contact_email' => 'mixed@example.com',
            'contact_phone' => '0901234567',
            'fulfillment_method' => 'pickup',
            'payment_method' => 'cash',
        ], 'mixed-checkout-001', [
            'contains_products' => true,
            'contains_tickets' => true,
        ]);

        $this->assertArrayHasKey('payment_method', $result['errors']);
        $this->assertContains(
            'Selected payment method is not available for the current cart.',
            $result['errors']['payment_method']
        );
    }

    public function testValidateCreatePayloadAcceptsTicketOnlyCashCheckout(): void
    {
        $validator = new UnifiedCheckoutValidator();

        $result = $validator->validateCreatePayload([
            'contact_name' => 'Ticket Checkout',
            'contact_email' => 'ticket@example.com',
            'contact_phone' => '0901234567',
            'fulfillment_method' => 'e_ticket',
            'payment_method' => 'cash',
        ], 'ticket-checkout-001', [
            'contains_products' => false,
            'contains_tickets' => true,
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame('e_ticket', $result['data']['fulfillment_method']);
        $this->assertSame('cash', $result['data']['payment_method']);
    }
}
