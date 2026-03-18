<?php

namespace Tests\Unit;

use App\Validators\ShopCartValidator;
use PHPUnit\Framework\TestCase;

class ShopCartValidatorTest extends TestCase
{
    public function testValidateAddItemPayloadRejectsInvalidFields(): void
    {
        $validator = new ShopCartValidator();

        $result = $validator->validateAddItemPayload([
            'product_id' => 'abc',
            'quantity' => '0',
        ]);

        $this->assertArrayHasKey('product_id', $result['errors']);
        $this->assertArrayHasKey('quantity', $result['errors']);
    }

    public function testValidateAddItemPayloadAcceptsLargePositiveQuantityForStockCheckedProducts(): void
    {
        $validator = new ShopCartValidator();

        $result = $validator->validateAddItemPayload([
            'product_id' => '9',
            'quantity' => '99',
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame(99, $result['data']['quantity']);
    }

    public function testValidateUpdateItemPayloadAcceptsPositiveQuantity(): void
    {
        $validator = new ShopCartValidator();

        $result = $validator->validateUpdateItemPayload([
            'quantity' => '4',
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame(4, $result['data']['quantity']);
    }
}
