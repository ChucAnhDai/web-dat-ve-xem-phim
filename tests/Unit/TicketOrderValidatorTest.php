<?php

namespace Tests\Unit;

use App\Validators\TicketOrderValidator;
use PHPUnit\Framework\TestCase;

class TicketOrderValidatorTest extends TestCase
{
    public function testValidateCreatePayloadNormalizesValidData(): void
    {
        $validator = new TicketOrderValidator();

        $result = $validator->validateCreatePayload([
            'showtime_id' => '42',
            'seat_ids' => ['383', '384'],
            'contact_name' => 'Guest Ticket',
            'contact_email' => 'guest@example.com',
            'contact_phone' => '0901234567',
            'payment_method' => 'momo',
            'fulfillment_method' => 'e_ticket',
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame(42, $result['data']['showtime_id']);
        $this->assertSame([383, 384], $result['data']['seat_ids']);
        $this->assertSame('guest@example.com', $result['data']['contact_email']);
        $this->assertSame('momo', $result['data']['payment_method']);
        $this->assertSame('e_ticket', $result['data']['fulfillment_method']);
    }

    public function testValidateCreatePayloadRejectsInvalidFields(): void
    {
        $validator = new TicketOrderValidator();

        $result = $validator->validateCreatePayload([
            'showtime_id' => '0',
            'seat_ids' => ['abc'],
            'contact_name' => '',
            'contact_email' => 'invalid-email',
            'contact_phone' => '12',
            'payment_method' => 'stripe',
            'fulfillment_method' => 'mail',
        ]);

        $this->assertNotEmpty($result['errors']['showtime_id']);
        $this->assertNotEmpty($result['errors']['seat_ids']);
        $this->assertNotEmpty($result['errors']['contact_name']);
        $this->assertNotEmpty($result['errors']['contact_email']);
        $this->assertNotEmpty($result['errors']['contact_phone']);
        $this->assertNotEmpty($result['errors']['payment_method']);
        $this->assertNotEmpty($result['errors']['fulfillment_method']);
    }
}
