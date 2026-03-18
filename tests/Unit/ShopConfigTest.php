<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ShopConfigTest extends TestCase
{
    public function testShopConfigExposesStableFoundationEnums(): void
    {
        $config = require __DIR__ . '/../../config/shop.php';

        $this->assertSame('VND', $config['currency']);
        $this->assertSame(['featured', 'standard', 'hidden'], $config['categories']['visibility']);
        $this->assertSame(['active', 'inactive', 'archived'], $config['categories']['statuses']);
        $this->assertSame(['draft', 'active', 'inactive', 'archived'], $config['products']['statuses']);
        $this->assertSame(['thumbnail', 'gallery', 'banner', 'lifestyle'], $config['products']['image_asset_types']);
        $this->assertSame(10, $config['products']['low_stock_threshold']);
        $this->assertSame(['pickup', 'delivery'], $config['orders']['fulfillment_methods']);
        $this->assertSame(['cash', 'vnpay'], $config['orders']['supported_payment_methods']);
        $this->assertSame(['cash', 'vnpay'], $config['orders']['pickup_payment_methods']);
        $this->assertSame(['vnpay'], $config['orders']['delivery_payment_methods']);
        $this->assertContains('pending', $config['orders']['statuses']);
        $this->assertContains('completed', $config['orders']['statuses']);
        $this->assertContains('refunded', $config['orders']['statuses']);
        $this->assertSame(['percent', 'fixed'], $config['promotions']['discount_types']);
        $this->assertSame(['active', 'archived'], $config['promotions']['assignment_statuses']);
    }

    public function testShopConfigUsesSafeCartLimits(): void
    {
        $config = require __DIR__ . '/../../config/shop.php';

        $this->assertGreaterThan(0, $config['cart']['ttl_minutes']);
        $this->assertGreaterThan(0, $config['cart']['max_items']);
        $this->assertGreaterThan(0, $config['cart']['max_quantity_per_item']);
        $this->assertSame('cinemax_cart', $config['cart']['cookie_name']);
        $this->assertGreaterThanOrEqual(16, $config['cart']['session_token_bytes']);
        $this->assertGreaterThanOrEqual($config['cart']['max_items'] / 10, $config['cart']['max_quantity_per_item']);
        $this->assertGreaterThanOrEqual(0, $config['orders']['default_shipping_amount']);
        $this->assertSame(5, $config['orders']['pending_payment_ttl_minutes']);
    }
}
