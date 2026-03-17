<?php

namespace Tests\Unit;

use App\Validators\ProductManagementValidator;
use PHPUnit\Framework\TestCase;

class ProductManagementValidatorTest extends TestCase
{
    public function testValidateCategoryPayloadRejectsInvalidFields(): void
    {
        $validator = new ProductManagementValidator();

        $result = $validator->validateCategoryPayload([
            'name' => '   ',
            'display_order' => '-1',
            'visibility' => 'priority',
            'status' => 'pending',
        ]);

        $this->assertArrayHasKey('name', $result['errors']);
        $this->assertArrayHasKey('slug', $result['errors']);
        $this->assertArrayHasKey('display_order', $result['errors']);
        $this->assertArrayHasKey('visibility', $result['errors']);
        $this->assertArrayHasKey('status', $result['errors']);
    }

    public function testValidateCategoryPayloadNormalizesSlugAndArchivedVisibility(): void
    {
        $validator = new ProductManagementValidator();

        $result = $validator->validateCategoryPayload([
            'name' => '  Limited Editions  ',
            'visibility' => 'featured',
            'status' => 'archived',
            'display_order' => '5',
            'description' => ' Campaign-only bundles ',
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame('limited-editions', $result['data']['slug']);
        $this->assertSame('hidden', $result['data']['visibility']);
        $this->assertSame('archived', $result['data']['status']);
        $this->assertSame(5, $result['data']['display_order']);
        $this->assertSame('Campaign-only bundles', $result['data']['description']);
    }

    public function testNormalizeCategoryFiltersNormalizesPaginationAndEnums(): void
    {
        $validator = new ProductManagementValidator();

        $result = $validator->normalizeCategoryFilters([
            'page' => '0',
            'per_page' => '250',
            'search' => '  combo  ',
            'visibility' => 'FEATURED',
            'status' => 'ACTIVE',
        ]);

        $this->assertSame(1, $result['page']);
        $this->assertSame(100, $result['per_page']);
        $this->assertSame('combo', $result['search']);
        $this->assertSame('featured', $result['visibility']);
        $this->assertSame('active', $result['status']);
    }

    public function testValidateProductPayloadRejectsInvalidFields(): void
    {
        $validator = new ProductManagementValidator();

        $result = $validator->validateProductPayload([
            'category_id' => 'abc',
            'name' => '   ',
            'sku' => '***',
            'price' => '-5',
            'compare_at_price' => '2',
            'stock' => '-1',
            'currency' => 'USD',
            'track_inventory' => 'maybe',
            'status' => 'pending',
            'visibility' => 'priority',
            'sort_order' => '-3',
            'attributes' => '{invalid-json}',
        ]);

        $this->assertArrayHasKey('category_id', $result['errors']);
        $this->assertArrayHasKey('name', $result['errors']);
        $this->assertArrayHasKey('sku', $result['errors']);
        $this->assertArrayHasKey('price', $result['errors']);
        $this->assertArrayHasKey('stock', $result['errors']);
        $this->assertArrayHasKey('currency', $result['errors']);
        $this->assertArrayHasKey('track_inventory', $result['errors']);
        $this->assertArrayHasKey('status', $result['errors']);
        $this->assertArrayHasKey('visibility', $result['errors']);
        $this->assertArrayHasKey('sort_order', $result['errors']);
        $this->assertArrayHasKey('attributes', $result['errors']);
    }

    public function testValidateProductPayloadNormalizesSkuAttributesAndArchivedVisibility(): void
    {
        $validator = new ProductManagementValidator();

        $result = $validator->validateProductPayload([
            'category_id' => '2',
            'name' => '  Large Popcorn Combo  ',
            'sku' => ' sku popcorn 001 ',
            'price' => '85000',
            'compare_at_price' => '99000',
            'stock' => '12',
            'track_inventory' => 'true',
            'status' => 'archived',
            'visibility' => 'featured',
            'sort_order' => '4',
            'brand' => ' CineShop ',
            'weight' => ' 380g ',
            'origin' => 'Vietnam',
            'detail_description' => 'Detailed bundle information',
            'attributes' => ['flavors' => ['salt', 'caramel']],
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame('large-popcorn-combo', $result['data']['slug']);
        $this->assertSame('SKU-POPCORN-001', $result['data']['sku']);
        $this->assertSame('hidden', $result['data']['visibility']);
        $this->assertSame('archived', $result['data']['status']);
        $this->assertSame(12, $result['data']['stock']);
        $this->assertSame(1, $result['data']['track_inventory']);
        $this->assertNotNull($result['data']['details']['attributes_json']);
    }

    public function testNormalizeProductFiltersNormalizesEnumsAndPagination(): void
    {
        $validator = new ProductManagementValidator();

        $result = $validator->normalizeProductFilters([
            'page' => '2',
            'per_page' => '250',
            'search' => '  mug  ',
            'category_id' => '3',
            'status' => 'ACTIVE',
            'visibility' => 'FEATURED',
            'stock_state' => 'LOW_STOCK',
        ]);

        $this->assertSame(2, $result['page']);
        $this->assertSame(100, $result['per_page']);
        $this->assertSame('mug', $result['search']);
        $this->assertSame(3, $result['category_id']);
        $this->assertSame('active', $result['status']);
        $this->assertSame('featured', $result['visibility']);
        $this->assertSame('low_stock', $result['stock_state']);
    }

    public function testValidateProductMediaPayloadRequiresSingleThumbnail(): void
    {
        $validator = new ProductManagementValidator();

        $result = $validator->validateProductMediaPayload([
            [
                'asset_type' => 'gallery',
                'source_type' => 'url',
                'image_url' => 'https://example.com/gallery.jpg',
                'sort_order' => 1,
            ],
        ], true);

        $this->assertArrayHasKey('media_thumbnail', $result['errors']);
    }

    public function testValidateProductMediaPayloadAcceptsThumbnailAndUploadGallery(): void
    {
        $validator = new ProductManagementValidator();

        $result = $validator->validateProductMediaPayload([
            [
                'asset_type' => 'thumbnail',
                'source_type' => 'url',
                'image_url' => 'https://example.com/thumb.jpg',
                'sort_order' => 0,
            ],
            [
                'asset_type' => 'gallery',
                'source_type' => 'upload',
                'existing_image_url' => 'public/uploads/products/demo.jpg',
                'sort_order' => 1,
            ],
        ], true);

        $this->assertSame([], $result['errors']);
        $this->assertSame('thumbnail', $result['data'][0]['asset_type']);
        $this->assertSame('upload', $result['data'][1]['source_type']);
        $this->assertSame('public/uploads/products/demo.jpg', $result['data'][1]['existing_image_url']);
    }

    public function testValidateProductMediaPayloadRejectsNonManagedExistingUploadPath(): void
    {
        $validator = new ProductManagementValidator();

        $result = $validator->validateProductMediaPayload([
            [
                'asset_type' => 'thumbnail',
                'source_type' => 'upload',
                'existing_image_url' => '../unsafe/thumb.jpg',
                'sort_order' => 0,
            ],
        ], true);

        $this->assertArrayHasKey('media_thumbnail', $result['errors']);
        $this->assertContains('Existing uploaded product media path is invalid.', $result['errors']['media_thumbnail']);
    }

    public function testValidateImagePayloadRejectsInvalidFields(): void
    {
        $validator = new ProductManagementValidator();

        $result = $validator->validateImagePayload([
            'product_id' => 'abc',
            'asset_type' => 'poster',
            'image_url' => 'not-a-url',
            'sort_order' => '-1',
            'is_primary' => 'maybe',
            'status' => 'published',
        ]);

        $this->assertArrayHasKey('product_id', $result['errors']);
        $this->assertArrayHasKey('asset_type', $result['errors']);
        $this->assertArrayHasKey('image_url', $result['errors']);
        $this->assertArrayHasKey('sort_order', $result['errors']);
        $this->assertArrayHasKey('is_primary', $result['errors']);
        $this->assertArrayHasKey('status', $result['errors']);
    }

    public function testValidateImagePayloadForcesArchivedImagesToBeNonPrimary(): void
    {
        $validator = new ProductManagementValidator();

        $result = $validator->validateImagePayload([
            'product_id' => '4',
            'asset_type' => 'banner',
            'image_url' => 'https://example.com/banner.jpg',
            'sort_order' => '2',
            'is_primary' => '1',
            'status' => 'archived',
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame('banner', $result['data']['asset_type']);
        $this->assertSame(0, $result['data']['is_primary']);
        $this->assertSame('archived', $result['data']['status']);
    }

    public function testNormalizeImageFiltersNormalizesEnumsAndPagination(): void
    {
        $validator = new ProductManagementValidator();

        $result = $validator->normalizeImageFilters([
            'page' => '3',
            'per_page' => '250',
            'search' => '  banner  ',
            'product_id' => '7',
            'asset_type' => 'LIFESTYLE',
            'status' => 'ACTIVE',
        ]);

        $this->assertSame(3, $result['page']);
        $this->assertSame(100, $result['per_page']);
        $this->assertSame('banner', $result['search']);
        $this->assertSame(7, $result['product_id']);
        $this->assertSame('lifestyle', $result['asset_type']);
        $this->assertSame('active', $result['status']);
    }

    public function testValidateImagePayloadAcceptsExistingUploadReference(): void
    {
        $validator = new ProductManagementValidator();

        $result = $validator->validateImagePayload([
            'product_id' => '9',
            'asset_type' => 'thumbnail',
            'source_type' => 'upload',
            'existing_image_url' => 'public/uploads/products/thumb.jpg',
            'sort_order' => '0',
            'is_primary' => '1',
            'status' => 'active',
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame('upload', $result['data']['source_type']);
        $this->assertSame('public/uploads/products/thumb.jpg', $result['data']['existing_image_url']);
    }

    public function testValidateImagePayloadRejectsNonManagedExistingUploadPath(): void
    {
        $validator = new ProductManagementValidator();

        $result = $validator->validateImagePayload([
            'product_id' => '9',
            'asset_type' => 'thumbnail',
            'source_type' => 'upload',
            'existing_image_url' => '../unsafe/thumb.jpg',
            'sort_order' => '0',
            'is_primary' => '1',
            'status' => 'active',
        ]);

        $this->assertArrayHasKey('image_file', $result['errors']);
        $this->assertContains('Existing uploaded image path is invalid.', $result['errors']['image_file']);
    }

    public function testValidateImageBatchPayloadAcceptsStructuredItems(): void
    {
        $validator = new ProductManagementValidator();

        $result = $validator->validateImageBatchPayload([
            [
                'product_id' => '9',
                'asset_type' => 'gallery',
                'source_type' => 'upload',
                'existing_image_url' => 'public/uploads/products/gallery-1.jpg',
                'sort_order' => '1',
                'is_primary' => '0',
                'status' => 'active',
            ],
            [
                'product_id' => '9',
                'asset_type' => 'gallery',
                'source_type' => 'upload',
                'existing_image_url' => 'public/uploads/products/gallery-2.jpg',
                'sort_order' => '2',
                'is_primary' => '0',
                'status' => 'active',
            ],
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertCount(2, $result['data']);
        $this->assertSame('gallery', $result['data'][0]['asset_type']);
    }

    public function testValidateImageBatchPayloadRejectsInvalidItems(): void
    {
        $validator = new ProductManagementValidator();

        $result = $validator->validateImageBatchPayload([
            [
                'product_id' => 'abc',
                'asset_type' => 'gallery',
                'source_type' => 'upload',
                'status' => 'active',
            ],
        ]);

        $this->assertArrayHasKey('items_manifest', $result['errors']);
        $this->assertStringContainsString('Image #1:', $result['errors']['items_manifest'][0]);
    }
}
