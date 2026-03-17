<?php

namespace Tests\Unit;

use App\Validators\ShopCatalogValidator;
use PHPUnit\Framework\TestCase;

class ShopCatalogValidatorTest extends TestCase
{
    public function testNormalizeCategoryFiltersNormalizesSearchAndBooleanFlag(): void
    {
        $validator = new ShopCatalogValidator();

        $result = $validator->normalizeCategoryFilters([
            'search' => '  snacks  ',
            'featured_only' => 'true',
        ]);

        $this->assertSame('snacks', $result['search']);
        $this->assertSame(1, $result['featured_only']);
    }

    public function testNormalizeProductFiltersClampsPaginationAndSwapsPriceRange(): void
    {
        $validator = new ShopCatalogValidator();

        $result = $validator->normalizeProductFilters([
            'page' => '0',
            'per_page' => '999',
            'search' => '  popcorn  ',
            'category_slug' => 'Combo Deals',
            'sort' => 'PRICE_DESC',
            'featured_only' => 'yes',
            'min_price' => '200000',
            'max_price' => '100000',
            'stock_state' => 'LOW_STOCK',
        ]);

        $this->assertSame(1, $result['page']);
        $this->assertSame(48, $result['per_page']);
        $this->assertSame('popcorn', $result['search']);
        $this->assertSame('combo-deals', $result['category_slug']);
        $this->assertSame('price_desc', $result['sort']);
        $this->assertSame(1, $result['featured_only']);
        $this->assertSame(100000.0, $result['min_price']);
        $this->assertSame(200000.0, $result['max_price']);
        $this->assertSame('low_stock', $result['stock_state']);
    }

    public function testNormalizeProductFiltersFallsBackToFeaturedSort(): void
    {
        $validator = new ShopCatalogValidator();

        $result = $validator->normalizeProductFilters([
            'sort' => 'unsupported',
            'stock_state' => 'unknown',
        ]);

        $this->assertSame('featured', $result['sort']);
        $this->assertNull($result['stock_state']);
    }
}
