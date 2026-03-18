<?php

namespace Tests\Unit;

use App\Validators\ShopOrderAccessValidator;
use PHPUnit\Framework\TestCase;

class ShopOrderAccessValidatorTest extends TestCase
{
    public function testNormalizeOrderFiltersCapsPagingAndEnums(): void
    {
        $validator = new ShopOrderAccessValidator();

        $result = $validator->normalizeOrderFilters([
            'search' => '  SHP-2026  ',
            'status' => 'pending',
            'payment_method' => 'cash',
            'page' => '0',
            'per_page' => '999',
        ]);

        $this->assertSame('SHP-2026', $result['search']);
        $this->assertSame('pending', $result['status']);
        $this->assertSame('cash', $result['payment_method']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(100, $result['per_page']);
    }

    public function testValidateLookupPayloadAcceptsOrderCodeAndMatchingContactFields(): void
    {
        $validator = new ShopOrderAccessValidator();

        $result = $validator->validateLookupPayload([
            'order_code' => ' shp-live-001 ',
            'contact_email' => 'Guest@Example.com',
            'contact_phone' => '0901 234 567',
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame('SHP-LIVE-001', $result['data']['order_code']);
        $this->assertSame('guest@example.com', $result['data']['contact_email']);
        $this->assertSame('0901 234 567', $result['data']['contact_phone']);
    }

    public function testValidateLookupPayloadRequiresOrderCodeEmailAndPhone(): void
    {
        $validator = new ShopOrderAccessValidator();

        $result = $validator->validateLookupPayload([
            'order_code' => '',
            'contact_email' => '',
            'contact_phone' => '',
        ]);

        $this->assertArrayHasKey('order_code', $result['errors']);
        $this->assertArrayHasKey('contact_email', $result['errors']);
        $this->assertArrayHasKey('contact_phone', $result['errors']);
    }

    public function testValidateLookupPayloadRejectsInvalidEmailAndPhoneFormats(): void
    {
        $validator = new ShopOrderAccessValidator();

        $result = $validator->validateLookupPayload([
            'order_code' => 'SHP-LIVE-001',
            'contact_email' => 'not-an-email',
            'contact_phone' => '12',
        ]);

        $this->assertSame(['Contact email is invalid.'], $result['errors']['contact_email'] ?? []);
        $this->assertSame(['Contact phone is invalid.'], $result['errors']['contact_phone'] ?? []);
    }
}
