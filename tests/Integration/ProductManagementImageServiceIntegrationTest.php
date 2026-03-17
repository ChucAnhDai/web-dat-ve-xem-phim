<?php

namespace Tests\Integration;

use App\Core\Logger;
use App\Repositories\ProductCategoryRepository;
use App\Repositories\ProductDetailRepository;
use App\Repositories\ProductImageRepository;
use App\Repositories\ProductRepository;
use App\Services\ProductManagementService;
use App\Validators\ProductManagementValidator;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ProductManagementImageServiceIntegrationTest extends TestCase
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

    public function testListImagesReturnsSummaryCountsAndJoinedProductData(): void
    {
        $this->db->exec("
            INSERT INTO product_images (id, product_id, asset_type, image_url, alt_text, sort_order, is_primary, status, created_at, updated_at) VALUES
                (11, 1, 'thumbnail', 'https://example.com/thumb.jpg', 'Thumb', 1, 1, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (12, 1, 'banner', 'https://example.com/banner.jpg', 'Banner', 2, 0, 'draft', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (13, 2, 'lifestyle', 'https://example.com/life.jpg', 'Lifestyle', 3, 0, 'archived', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (14, 1, 'gallery', 'https://example.com/gallery.jpg', 'Gallery', 4, 0, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $service = $this->makeService();
        $result = $service->listImages([
            'page' => 1,
            'per_page' => 10,
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertSame(4, $result['data']['summary']['total']);
        $this->assertSame(1, $result['data']['summary']['thumbnail']);
        $this->assertSame(1, $result['data']['summary']['banner']);
        $this->assertSame(1, $result['data']['summary']['gallery']);
        $this->assertSame(1, $result['data']['summary']['lifestyle']);
        $this->assertSame(1, $result['data']['summary']['draft']);
        $this->assertSame(2, $result['data']['summary']['active']);
        $this->assertSame(1, $result['data']['summary']['archived']);
        $this->assertSame(1, $result['data']['summary']['primary']);
        $this->assertSame('Large Popcorn Combo', $result['data']['items'][0]['product_name']);
    }

    public function testCreateImagePromotesSinglePrimaryPerProductAndType(): void
    {
        $this->db->exec("
            INSERT INTO product_images (id, product_id, asset_type, image_url, alt_text, sort_order, is_primary, status, created_at, updated_at)
            VALUES (21, 1, 'thumbnail', 'https://example.com/thumb-old.jpg', 'Old thumb', 1, 1, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $service = $this->makeService();
        $result = $service->createImage([
            'product_id' => 1,
            'asset_type' => 'thumbnail',
            'source_type' => 'url',
            'image_url' => 'https://example.com/thumb-new.jpg',
            'alt_text' => 'New thumb',
            'sort_order' => 2,
            'is_primary' => 1,
            'status' => 'active',
        ], 10);

        $this->assertSame(201, $result['status']);

        $primaryCount = (int) $this->db->query("SELECT COUNT(*) FROM product_images WHERE product_id = 1 AND asset_type = 'thumbnail' AND is_primary = 1")->fetchColumn();
        $oldPrimary = (int) $this->db->query("SELECT is_primary FROM product_images WHERE id = 21")->fetchColumn();
        $newRow = $this->db->query("SELECT image_url, is_primary FROM product_images WHERE id <> 21 ORDER BY id DESC LIMIT 1")->fetch();

        $this->assertSame(1, $primaryCount);
        $this->assertSame(0, $oldPrimary);
        $this->assertSame('https://example.com/thumb-new.jpg', $newRow['image_url']);
        $this->assertSame(1, (int) $newRow['is_primary']);
    }

    public function testCreateImageRollsBackWhenImagePersistenceFails(): void
    {
        $this->db->exec("
            INSERT INTO product_images (id, product_id, asset_type, image_url, alt_text, sort_order, is_primary, status, created_at, updated_at)
            VALUES (31, 1, 'banner', 'https://example.com/banner-old.jpg', 'Old banner', 1, 1, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $service = new ProductManagementService(
            $this->db,
            new ProductCategoryRepository($this->db),
            new ProductManagementValidator(),
            new ProductManagementImageFakeLogger(),
            null,
            new ProductRepository($this->db),
            new ProductDetailRepository($this->db),
            new ProductFailingImageRepository($this->db)
        );

        $result = $service->createImage([
            'product_id' => 1,
            'asset_type' => 'banner',
            'source_type' => 'url',
            'image_url' => 'https://example.com/banner-new.jpg',
            'alt_text' => 'New banner',
            'sort_order' => 2,
            'is_primary' => 1,
            'status' => 'active',
        ], 10);

        $this->assertSame(500, $result['status']);

        $primaryCount = (int) $this->db->query("SELECT COUNT(*) FROM product_images WHERE product_id = 1 AND asset_type = 'banner' AND is_primary = 1")->fetchColumn();
        $bannerCount = (int) $this->db->query("SELECT COUNT(*) FROM product_images WHERE product_id = 1 AND asset_type = 'banner'")->fetchColumn();

        $this->assertSame(1, $primaryCount);
        $this->assertSame(1, $bannerCount);
    }

    public function testCreateImageRejectsNonArchivedAssetOnArchivedProduct(): void
    {
        $service = $this->makeService();
        $result = $service->createImage([
            'product_id' => 2,
            'asset_type' => 'gallery',
            'source_type' => 'url',
            'image_url' => 'https://example.com/gallery.jpg',
            'sort_order' => 1,
            'is_primary' => 0,
            'status' => 'draft',
        ], 10);

        $this->assertSame(409, $result['status']);
        $this->assertSame(['Archived products cannot receive non-archived images.'], $result['errors']['product_id']);
    }

    public function testArchiveImageClearsPrimaryFlag(): void
    {
        $this->db->exec("
            INSERT INTO product_images (id, product_id, asset_type, image_url, alt_text, sort_order, is_primary, status, created_at, updated_at)
            VALUES (41, 1, 'lifestyle', 'https://example.com/life.jpg', 'Life', 1, 1, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $service = $this->makeService();
        $result = $service->archiveImage(41, 10);

        $this->assertSame(200, $result['status']);

        $row = $this->db->query("SELECT status, is_primary FROM product_images WHERE id = 41")->fetch();
        $this->assertSame('archived', $row['status']);
        $this->assertSame(0, (int) $row['is_primary']);
    }

    public function testCreateImageAcceptsExistingUploadPath(): void
    {
        $service = $this->makeService();

        $result = $service->createImage([
            'product_id' => 1,
            'asset_type' => 'gallery',
            'source_type' => 'upload',
            'existing_image_url' => 'public/uploads/products/gallery-demo.jpg',
            'alt_text' => 'Uploaded gallery',
            'sort_order' => 5,
            'is_primary' => 0,
            'status' => 'active',
        ], 10);

        $this->assertSame(201, $result['status']);

        $row = $this->db->query("SELECT image_url, sort_order FROM product_images WHERE asset_type = 'gallery' ORDER BY id DESC LIMIT 1")->fetch();
        $this->assertSame('public/uploads/products/gallery-demo.jpg', $row['image_url']);
        $this->assertSame(5, (int) $row['sort_order']);
    }

    public function testCreateImageRejectsNonManagedExistingUploadPath(): void
    {
        $service = $this->makeService();

        $result = $service->createImage([
            'product_id' => 1,
            'asset_type' => 'gallery',
            'source_type' => 'upload',
            'existing_image_url' => '../unsafe/gallery-demo.jpg',
            'alt_text' => 'Uploaded gallery',
            'sort_order' => 5,
            'is_primary' => 0,
            'status' => 'active',
        ], 10);

        $this->assertSame(422, $result['status']);
        $this->assertSame(['Existing uploaded image path is invalid.'], $result['errors']['image_file']);

        $galleryCount = (int) $this->db->query("SELECT COUNT(*) FROM product_images WHERE asset_type = 'gallery'")->fetchColumn();
        $this->assertSame(0, $galleryCount);
    }

    public function testCreateImagesBatchPersistsAllImages(): void
    {
        $service = $this->makeService();

        $result = $service->createImagesBatch([
            'items_manifest' => [
                [
                    'product_id' => 1,
                    'asset_type' => 'gallery',
                    'source_type' => 'upload',
                    'existing_image_url' => 'public/uploads/products/gallery-1.jpg',
                    'alt_text' => 'Gallery 1',
                    'sort_order' => 3,
                    'is_primary' => 0,
                    'status' => 'active',
                ],
                [
                    'product_id' => 1,
                    'asset_type' => 'gallery',
                    'source_type' => 'upload',
                    'existing_image_url' => 'public/uploads/products/gallery-2.jpg',
                    'alt_text' => 'Gallery 2',
                    'sort_order' => 4,
                    'is_primary' => 0,
                    'status' => 'active',
                ],
            ],
        ], 10);

        $this->assertSame(201, $result['status']);
        $this->assertSame(2, $result['data']['count']);

        $rows = $this->db->query("SELECT image_url, alt_text, sort_order FROM product_images WHERE asset_type = 'gallery' ORDER BY sort_order ASC")->fetchAll();
        $this->assertCount(2, $rows);
        $this->assertSame('public/uploads/products/gallery-1.jpg', $rows[0]['image_url']);
        $this->assertSame('Gallery 2', $rows[1]['alt_text']);
        $this->assertSame(4, (int) $rows[1]['sort_order']);
    }

    private function makeService(): ProductManagementService
    {
        return new ProductManagementService(
            $this->db,
            new ProductCategoryRepository($this->db),
            new ProductManagementValidator(),
            new ProductManagementImageFakeLogger(),
            null,
            new ProductRepository($this->db),
            new ProductDetailRepository($this->db),
            new ProductImageRepository($this->db)
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
                (1, 'Snacks', 'snacks', 'Snacks', 1, 'featured', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $this->db->exec("
            INSERT INTO products (
                id, category_id, slug, sku, name, short_description, description, price, compare_at_price, stock, currency,
                track_inventory, status, visibility, sort_order, created_at, updated_at
            ) VALUES
                (1, 1, 'large-popcorn-combo', 'SKU-POP-001', 'Large Popcorn Combo', NULL, NULL, 85000, NULL, 25, 'VND', 1, 'active', 'featured', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (2, 1, 'seasonal-cap', 'SKU-CAP-001', 'Seasonal Cap', NULL, NULL, 120000, NULL, 0, 'VND', 1, 'archived', 'hidden', 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
    }
}

class ProductFailingImageRepository extends ProductImageRepository
{
    public function create(array $data): int
    {
        $imageId = parent::create($data);

        throw new RuntimeException('Image persistence failed after insert ' . $imageId);
    }
}

class ProductManagementImageFakeLogger extends Logger
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
