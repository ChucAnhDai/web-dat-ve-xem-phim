<?php

namespace Tests\Unit;

use App\Validators\ShopOrderAdminValidator;
use PHPUnit\Framework\TestCase;

class ShopOrderAdminValidatorTest extends TestCase
{
    public function testNormalizeOrderFiltersSanitizesInvalidValues(): void
    {
        $validator = new ShopOrderAdminValidator();

        $result = $validator->normalizeOrderFilters([
            'search' => str_repeat('x', 200),
            'status' => 'unknown',
            'payment_method' => 'cash',
            'fulfillment_method' => 'delivery',
            'page' => 0,
            'per_page' => 999,
        ]);

        $this->assertSame(120, mb_strlen($result['search']));
        $this->assertNull($result['status']);
        $this->assertSame('cash', $result['payment_method']);
        $this->assertSame('delivery', $result['fulfillment_method']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(100, $result['per_page']);
    }

    public function testValidateStatusUpdatePayloadRejectsUnsupportedStatus(): void
    {
        $validator = new ShopOrderAdminValidator();

        $result = $validator->validateStatusUpdatePayload([
            'status' => 'refunded',
        ]);

        $this->assertArrayHasKey('status', $result['errors']);
    }

    public function testValidateStatusUpdatePayloadAcceptsManagedStatus(): void
    {
        $validator = new ShopOrderAdminValidator();

        $result = $validator->validateStatusUpdatePayload([
            'status' => 'ready',
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame('ready', $result['data']['status']);
    }
}
