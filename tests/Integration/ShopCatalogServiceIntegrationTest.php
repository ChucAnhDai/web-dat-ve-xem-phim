<?php

namespace Tests\Integration;

use App\Core\Logger;
use App\Repositories\ProductCategoryRepository;
use App\Repositories\ProductImageRepository;
use App\Repositories\ProductRepository;
use App\Services\ShopCatalogService;
use App\Validators\ShopCatalogValidator;
use PDO;
use PHPUnit\Framework\TestCase;

class ShopCatalogServiceIntegrationTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->db->exec('PRAGMA foreign_keys = ON');

        $this->createSchema();
        $this->seedBaseData();
    }

    public function testListCategoriesReturnsOnlyPublicCategoriesWithCounts(): void
    {
        $service = $this->makeService();
        $result = $service->listCategories([]);

        $this->assertSame(200, $result['status']);
        $this->assertCount(2, $result['data']['items']);
        $this->assertSame('snacks', $result['data']['items'][0]['slug']);
        $this->assertSame(2, $result['data']['items'][0]['product_count']);
        $this->assertSame(2, $result['data']['meta']['total']);
    }

    public function testListProductsReturnsFilteredPublicCatalogSummaryAndImages(): void
    {
        $service = $this->makeService();
        $result = $service->listProducts([
            'category_slug' => 'snacks',
            'sort' => 'featured',
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertSame(2, $result['data']['summary']['total']);
        $this->assertSame(1, $result['data']['summary']['featured']);
        $this->assertSame(1, $result['data']['summary']['in_stock']);
        $this->assertSame(1, $result['data']['summary']['low_stock']);
        $this->assertSame('Large Popcorn Combo', $result['data']['items'][0]['name']);
        $this->assertSame('https://example.com/popcorn-thumb.jpg', $result['data']['items'][0]['primary_image_url']);
    }

    public function testGetProductDetailReturnsGalleryHighlightsAndRelatedProducts(): void
    {
        $service = $this->makeService();
        $result = $service->getProductDetail('large-popcorn-combo');

        $this->assertSame(200, $result['status']);
        $this->assertSame('Large Popcorn Combo', $result['data']['product']['name']);
        $this->assertSame('CineShop', $result['data']['product']['brand']);
        $this->assertSame(25, $result['data']['product']['stock']);
        $this->assertSame(25, $result['data']['product']['max_quantity_available']);
        $this->assertCount(2, $result['data']['gallery']);
        $this->assertNotEmpty($result['data']['product']['highlights']);
        $this->assertSame('Nacho Dip Set', $result['data']['related_products'][0]['name']);
    }

    public function testGetProductDetailResolvesLocalUploadPaths(): void
    {
        $this->db->exec("
            UPDATE product_images
            SET image_url = 'public/uploads/products/popcorn-thumb.jpg'
            WHERE product_id = 1
              AND asset_type = 'thumbnail'
        ");

        $service = $this->makeService();
        $result = $service->getProductDetail('large-popcorn-combo');

        $this->assertSame(200, $result['status']);
        $this->assertStringContainsString('/public/uploads/products/popcorn-thumb.jpg', $result['data']['product']['primary_image_url']);
        $this->assertStringContainsString('/public/uploads/products/popcorn-thumb.jpg', $result['data']['gallery'][0]['image_url']);
    }

    public function testGetProductDetailReturnsNotFoundForHiddenProduct(): void
    {
        $service = $this->makeService();
        $result = $service->getProductDetail('private-bundle');

        $this->assertSame(404, $result['status']);
        $this->assertSame(['Product not found.'], $result['errors']['product']);
    }

    private function makeService(): ShopCatalogService
    {
        return new ShopCatalogService(
            new ProductRepository($this->db),
            new ProductCategoryRepository($this->db),
            new ProductImageRepository($this->db),
            new ShopCatalogValidator(),
            new ShopCatalogFakeLogger()
        );
    }

    private function createSchema(): void
    {
        $this->db->exec('
            CREATE TABLE product_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                description TEXT,
                display_order INTEGER NOT NULL DEFAULT 0,
                visibility TEXT NOT NULL DEFAULT "standard",
                status TEXT NOT NULL DEFAULT "active",
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->db->exec('
            CREATE TABLE products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER,
                slug TEXT NOT NULL UNIQUE,
                sku TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                short_description TEXT,
                description TEXT,
                price REAL NOT NULL DEFAULT 0,
                compare_at_price REAL NULL,
                stock INTEGER NOT NULL DEFAULT 0,
                currency TEXT NOT NULL DEFAULT "VND",
                track_inventory INTEGER NOT NULL DEFAULT 1,
                status TEXT NOT NULL DEFAULT "draft",
                visibility TEXT NOT NULL DEFAULT "standard",
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES product_categories(id)
            )
        ');

        $this->db->exec('
            CREATE TABLE product_details (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL UNIQUE,
                brand TEXT,
                weight TEXT,
                origin TEXT,
                description TEXT,
                attributes_json TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id)
            )
        ');

        $this->db->exec('
            CREATE TABLE product_images (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                asset_type TEXT NOT NULL DEFAULT "gallery",
                image_url TEXT NOT NULL,
                alt_text TEXT,
                sort_order INTEGER NOT NULL DEFAULT 0,
                is_primary INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT "draft",
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id)
            )
        ');
    }

    private function seedBaseData(): void
    {
        $this->db->exec("
            INSERT INTO product_categories (id, name, slug, description, display_order, visibility, status, created_at, updated_at) VALUES
                (1, 'Snacks', 'snacks', 'Fresh cinema snacks', 1, 'featured', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (2, 'Merchandise', 'merchandise', 'Branded merch', 2, 'standard', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (3, 'Seasonal', 'seasonal', 'Private items', 3, 'hidden', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $this->db->exec("
            INSERT INTO products (
                id, category_id, slug, sku, name, short_description, description, price, compare_at_price, stock, currency,
                track_inventory, status, visibility, sort_order, created_at, updated_at
            ) VALUES
                (1, 1, 'large-popcorn-combo', 'SKU-POP-001', 'Large Popcorn Combo', 'Best-selling combo', 'Served hot and fresh', 85000, 99000, 25, 'VND', 1, 'active', 'featured', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (2, 1, 'nacho-dip-set', 'SKU-NACHO-001', 'Nacho Dip Set', 'Crunchy and shareable', 'Loaded with cheese dip', 65000, NULL, 5, 'VND', 1, 'active', 'standard', 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (3, 1, 'private-bundle', 'SKU-PRIVATE-001', 'Private Bundle', 'Hidden item', 'Should not appear publicly', 120000, NULL, 8, 'VND', 1, 'active', 'hidden', 3, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (4, 2, 'cinemax-mug', 'SKU-MUG-001', 'CinemaX Mug', 'Collectible mug', 'Premium ceramic mug', 120000, NULL, 0, 'VND', 1, 'active', 'featured', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (5, 3, 'seasonal-bucket', 'SKU-SEASON-001', 'Seasonal Bucket', 'Hidden category item', 'Should not appear publicly', 99000, NULL, 10, 'VND', 1, 'active', 'featured', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $this->db->exec("
            INSERT INTO product_details (product_id, brand, weight, origin, description, attributes_json, created_at, updated_at) VALUES
                (1, 'CineShop', '380g', 'Vietnam', 'Detailed bundle information', '{\"bundle\":true,\"cups\":2}', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (2, 'SnackLab', '250g', 'Vietnam', 'Cheesy nacho description', '{\"spice\":\"mild\"}', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (4, 'CinemaX', '400g', 'Vietnam', 'Premium ceramic finish', '{\"material\":\"ceramic\"}', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $this->db->exec("
            INSERT INTO product_images (product_id, asset_type, image_url, alt_text, sort_order, is_primary, status, created_at, updated_at) VALUES
                (1, 'thumbnail', 'https://example.com/popcorn-thumb.jpg', 'Popcorn combo thumbnail', 1, 1, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (1, 'gallery', 'https://example.com/popcorn-gallery.jpg', 'Popcorn combo gallery', 2, 0, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (2, 'banner', 'https://example.com/nacho-banner.jpg', 'Nacho banner', 1, 1, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (4, 'thumbnail', 'https://example.com/mug-thumb.jpg', 'Mug thumbnail', 1, 1, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (3, 'thumbnail', 'https://example.com/private-thumb.jpg', 'Private thumb', 1, 1, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
    }
}

class ShopCatalogFakeLogger extends Logger
{
    public function __construct()
    {
    }

    public function info(string $message, array $context = []): void
    {
    }

    public function error(string $message, array $context = []): void
    {
    }
}
