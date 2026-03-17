<?php

namespace Tests\Integration;

use App\Core\Logger;
use App\Repositories\ProductCategoryRepository;
use App\Repositories\ProductDetailRepository;
use App\Repositories\ProductRepository;
use App\Services\ProductManagementService;
use App\Validators\ProductManagementValidator;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ProductManagementProductServiceIntegrationTest extends TestCase
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

    public function testListProductsReturnsSummaryCountsAndJoinedDetails(): void
    {
        $this->db->exec("
            INSERT INTO products (
                id, category_id, slug, sku, name, short_description, description, price, compare_at_price, stock, currency,
                track_inventory, status, visibility, sort_order, created_at, updated_at
            ) VALUES
                (11, 1, 'large-popcorn-combo', 'SKU-POP-001', 'Large Popcorn Combo', 'Combo', 'Combo desc', 85000, 99000, 25, 'VND', 1, 'active', 'featured', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (12, 1, 'nacho-dip-set', 'SKU-NACHO-001', 'Nacho Dip Set', NULL, NULL, 65000, NULL, 4, 'VND', 1, 'inactive', 'standard', 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (13, 2, 'movie-mug', 'SKU-MUG-001', 'Movie Mug', NULL, NULL, 120000, NULL, 0, 'VND', 1, 'archived', 'hidden', 3, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (14, 1, 'gift-card', 'SKU-GIFT-001', 'Gift Card', NULL, NULL, 200000, NULL, 0, 'VND', 0, 'draft', 'standard', 4, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $this->db->exec("
            INSERT INTO product_details (product_id, brand, weight, origin, description, attributes_json, created_at, updated_at) VALUES
                (11, 'CineShop', '380g', 'Vietnam', 'Detailed combo info', '{\"bundle\":true}', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (12, 'SnackLab', '250g', 'Vietnam', NULL, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $service = $this->makeService();
        $result = $service->listProducts([
            'page' => 1,
            'per_page' => 10,
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertSame(4, $result['data']['summary']['total']);
        $this->assertSame(2, $result['data']['summary']['in_stock']);
        $this->assertSame(1, $result['data']['summary']['low_stock']);
        $this->assertSame(0, $result['data']['summary']['out_of_stock']);
        $this->assertSame(1, $result['data']['summary']['draft']);
        $this->assertSame(1, $result['data']['summary']['active']);
        $this->assertSame(1, $result['data']['summary']['inactive']);
        $this->assertSame(1, $result['data']['summary']['archived']);
        $this->assertSame('Large Popcorn Combo', $result['data']['items'][0]['name']);
        $this->assertSame('Snacks', $result['data']['items'][0]['category_name']);
        $this->assertSame('CineShop', $result['data']['items'][0]['brand']);
        $this->assertSame('in_stock', $result['data']['items'][0]['stock_state']);
    }

    public function testCreateProductPersistsProductAndDetails(): void
    {
        $service = $this->makeService();

        $result = $service->createProduct([
            'category_id' => 1,
            'name' => 'Large Popcorn Combo',
            'sku' => 'sku popcorn 001',
            'price' => 85000,
            'compare_at_price' => 99000,
            'stock' => 30,
            'status' => 'active',
            'visibility' => 'featured',
            'sort_order' => 2,
            'short_description' => 'Best-selling combo',
            'description' => 'Served hot and fresh',
            'brand' => 'CineShop',
            'weight' => '380g',
            'origin' => 'Vietnam',
            'detail_description' => 'Detailed bundle information',
            'attributes' => ['bundle' => true, 'cups' => 2],
            'media_manifest' => [
                [
                    'asset_type' => 'thumbnail',
                    'source_type' => 'url',
                    'image_url' => 'https://example.com/popcorn-thumb.jpg',
                    'sort_order' => 0,
                ],
                [
                    'asset_type' => 'gallery',
                    'source_type' => 'url',
                    'image_url' => 'https://example.com/popcorn-gallery.jpg',
                    'sort_order' => 1,
                ],
            ],
        ], 12);

        $this->assertSame(201, $result['status']);

        $productRow = $this->db->query("SELECT slug, sku, status, visibility, price FROM products WHERE sku = 'SKU-POPCORN-001'")->fetch();
        $detailRow = $this->db->query("
            SELECT brand, weight, origin, description, attributes_json
            FROM product_details
            WHERE product_id = (SELECT id FROM products WHERE sku = 'SKU-POPCORN-001' LIMIT 1)
        ")->fetch();
        $imageCount = (int) $this->db->query("
            SELECT COUNT(*)
            FROM product_images
            WHERE product_id = (SELECT id FROM products WHERE sku = 'SKU-POPCORN-001' LIMIT 1)
              AND status = 'active'
        ")->fetchColumn();

        $this->assertSame('large-popcorn-combo', $productRow['slug']);
        $this->assertSame('active', $productRow['status']);
        $this->assertSame('featured', $productRow['visibility']);
        $this->assertSame(85000.0, (float) $productRow['price']);
        $this->assertSame('CineShop', $detailRow['brand']);
        $this->assertSame('380g', $detailRow['weight']);
        $this->assertSame('Vietnam', $detailRow['origin']);
        $this->assertNotNull($detailRow['attributes_json']);
        $this->assertSame(2, $imageCount);
    }

    public function testCreateProductRollsBackWhenDetailPersistenceFails(): void
    {
        $service = new ProductManagementService(
            $this->db,
            new ProductCategoryRepository($this->db),
            new ProductManagementValidator(),
            new ProductManagementProductFakeLogger(),
            null,
            new ProductRepository($this->db),
            new ProductFailingDetailRepository($this->db)
        );

        $result = $service->createProduct([
            'category_id' => 1,
            'name' => 'Rollback Product',
            'sku' => 'RBK-001',
            'price' => 55000,
            'stock' => 5,
            'status' => 'draft',
            'media_manifest' => [
                [
                    'asset_type' => 'thumbnail',
                    'source_type' => 'url',
                    'image_url' => 'https://example.com/rollback-thumb.jpg',
                    'sort_order' => 0,
                ],
            ],
        ], 10);

        $this->assertSame(500, $result['status']);
        $count = (int) $this->db->query("SELECT COUNT(*) FROM products WHERE sku = 'RBK-001'")->fetchColumn();

        $this->assertSame(0, $count);
    }

    public function testUpdateProductRejectsArchivedCategoryReference(): void
    {
        $this->db->exec("
            INSERT INTO products (
                id, category_id, slug, sku, name, short_description, description, price, compare_at_price, stock, currency,
                track_inventory, status, visibility, sort_order, created_at, updated_at
            ) VALUES
                (21, 1, 'movie-mug', 'SKU-MUG-001', 'Movie Mug', NULL, NULL, 120000, NULL, 12, 'VND', 1, 'active', 'featured', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $service = $this->makeService();
        $result = $service->updateProduct(21, [
            'category_id' => 2,
            'name' => 'Movie Mug',
            'sku' => 'SKU-MUG-001',
            'price' => 120000,
            'stock' => 12,
            'status' => 'active',
            'media_manifest' => [
                [
                    'asset_type' => 'thumbnail',
                    'source_type' => 'url',
                    'image_url' => 'https://example.com/mug-thumb.jpg',
                    'sort_order' => 0,
                ],
            ],
        ], 10);

        $this->assertSame(409, $result['status']);
        $this->assertSame(['Archived product categories cannot receive products.'], $result['errors']['category_id']);
    }

    public function testArchiveProductHidesVisibility(): void
    {
        $this->db->exec("
            INSERT INTO products (
                id, category_id, slug, sku, name, short_description, description, price, compare_at_price, stock, currency,
                track_inventory, status, visibility, sort_order, created_at, updated_at
            ) VALUES
                (31, 1, 'cine-cap', 'SKU-CAP-001', 'Cine Cap', NULL, NULL, 90000, NULL, 8, 'VND', 1, 'inactive', 'featured', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $service = $this->makeService();
        $result = $service->archiveProduct(31, 10);

        $this->assertSame(200, $result['status']);

        $row = $this->db->query("SELECT visibility, status FROM products WHERE id = 31")->fetch();
        $this->assertSame('hidden', $row['visibility']);
        $this->assertSame('archived', $row['status']);
    }

    private function makeService(): ProductManagementService
    {
        return new ProductManagementService(
            $this->db,
            new ProductCategoryRepository($this->db),
            new ProductManagementValidator(),
            new ProductManagementProductFakeLogger(),
            null,
            new ProductRepository($this->db),
            new ProductDetailRepository($this->db)
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
                (1, 'Snacks', 'snacks', 'Snacks', 1, 'featured', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (2, 'Seasonal', 'seasonal', 'Seasonal', 2, 'hidden', 'archived', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
    }
}

class ProductFailingDetailRepository extends ProductDetailRepository
{
    public function upsertForProduct(int $productId, array $data): void
    {
        throw new RuntimeException('Detail persistence failed for product ' . $productId);
    }
}

class ProductManagementProductFakeLogger extends Logger
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
