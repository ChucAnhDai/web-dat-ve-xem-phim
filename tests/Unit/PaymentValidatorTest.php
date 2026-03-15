<?php

namespace Tests\Unit;

use App\Validators\PaymentValidator;
use PHPUnit\Framework\TestCase;

class PaymentValidatorTest extends TestCase
{
    public function testValidateTicketIntentPayloadAcceptsVnpayCheckoutData(): void
    {
        $validator = new PaymentValidator();

        $result = $validator->validateTicketIntentPayload([
            'showtime_id' => '42',
            'seat_ids' => ['10', '11'],
            'contact_name' => 'Nguyen Van A',
            'contact_email' => 'guest@example.com',
            'contact_phone' => '0901234567',
            'payment_method' => 'vnpay',
            'fulfillment_method' => 'e_ticket',
            'bank_code' => 'NCB',
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame('vnpay', $result['data']['payment_method']);
        $this->assertSame('NCB', $result['data']['bank_code']);
    }

    public function testValidateVnpayCallbackPayloadRejectsMissingSignature(): void
    {
        $validator = new PaymentValidator();

        $result = $validator->validateVnpayCallbackPayload([
            'vnp_TxnRef' => 'TKT-001',
            'vnp_Amount' => '17000000',
            'vnp_ResponseCode' => '00',
        ]);

        $this->assertNotEmpty($result['errors']['vnp_SecureHash']);
    }
}
