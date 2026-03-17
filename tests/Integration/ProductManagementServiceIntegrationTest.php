<?php

namespace Tests\Integration;

use App\Core\Logger;
use App\Repositories\ProductCategoryRepository;
use App\Services\ProductManagementService;
use App\Validators\ProductManagementValidator;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ProductManagementServiceIntegrationTest extends TestCase
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

    public function testListCategoriesReturnsSummaryAndProductCounts(): void
    {
        $this->db->exec("
            INSERT INTO products (
                id, category_id, slug, sku, name, price, stock, currency, track_inventory, visibility, status, sort_order, created_at, updated_at
            ) VALUES
                (11, 1, 'salt-popcorn', 'SKU-000011', 'Salt Popcorn', 59000, 20, 'VND', 1, 'featured', 'active', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (12, 1, 'caramel-popcorn', 'SKU-000012', 'Caramel Popcorn', 69000, 15, 'VND', 1, 'standard', 'draft', 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (13, 2, 'sparkling-water', 'SKU-000013', 'Sparkling Water', 35000, 18, 'VND', 1, 'standard', 'archived', 3, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $service = $this->makeService();
        $result = $service->listCategories([
            'page' => 1,
            'per_page' => 10,
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertSame(3, $result['data']['summary']['total']);
        $this->assertSame(1, $result['data']['summary']['featured']);
        $this->assertSame(1, $result['data']['summary']['standard']);
        $this->assertSame(1, $result['data']['summary']['hidden']);
        $this->assertSame(1, $result['data']['summary']['active']);
        $this->assertSame(1, $result['data']['summary']['inactive']);
        $this->assertSame(1, $result['data']['summary']['archived']);
        $this->assertSame(3, $result['data']['summary']['products_tagged']);
        $this->assertSame(2, $result['data']['items'][0]['product_count']);
        $this->assertSame('Snacks', $result['data']['items'][0]['name']);
    }

    public function testCreateCategoryPersistsNormalizedCategory(): void
    {
        $service = $this->makeService();

        $result = $service->createCategory([
            'name' => '  Limited Editions  ',
            'visibility' => 'featured',
            'status' => 'active',
            'display_order' => '5',
            'description' => 'Campaign-only drops',
        ], 15);

        $this->assertSame(201, $result['status']);

        $row = $this->db->query("SELECT name, slug, visibility, status, display_order FROM product_categories WHERE slug = 'limited-editions'")->fetch();

        $this->assertSame('Limited Editions', $row['name']);
        $this->assertSame('featured', $row['visibility']);
        $this->assertSame('active', $row['status']);
        $this->assertSame(5, (int) $row['display_order']);
    }

    public function testUpdateCategoryBlocksArchivingWhenProductsAreStillAssigned(): void
    {
        $this->db->exec("
            INSERT INTO products (
                id, category_id, slug, sku, name, price, stock, currency, track_inventory, visibility, status, sort_order, created_at, updated_at
            ) VALUES
                (21, 1, 'combo-set-a', 'SKU-000021', 'Combo Set A', 99000, 8, 'VND', 1, 'standard', 'active', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $service = $this->makeService();
        $result = $service->updateCategory(1, [
            'name' => 'Snacks',
            'slug' => 'snacks',
            'visibility' => 'featured',
            'status' => 'archived',
            'display_order' => 1,
        ], 12);

        $this->assertSame(409, $result['status']);
        $this->assertSame(
            ['Cannot archive product category while non-archived products are still assigned.'],
            $result['errors']['category']
        );

        $row = $this->db->query('SELECT visibility, status FROM product_categories WHERE id = 1')->fetch();
        $this->assertSame('featured', $row['visibility']);
        $this->assertSame('active', $row['status']);
    }

    public function testArchiveCategorySucceedsWhenOnlyArchivedProductsRemain(): void
    {
        $this->db->exec("
            INSERT INTO products (
                id, category_id, slug, sku, name, price, stock, currency, track_inventory, visibility, status, sort_order, created_at, updated_at
            ) VALUES
                (31, 2, 'old-coke', 'SKU-000031', 'Old Coke', 29000, 0, 'VND', 1, 'hidden', 'archived', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $service = $this->makeService();
        $result = $service->archiveCategory(2, 12);

        $this->assertSame(200, $result['status']);

        $row = $this->db->query('SELECT visibility, status FROM product_categories WHERE id = 2')->fetch();
        $this->assertSame('hidden', $row['visibility']);
        $this->assertSame('archived', $row['status']);
    }

    public function testCreateCategoryRollsBackWhenRepositoryThrowsAfterInsert(): void
    {
        $service = new ProductManagementService(
            $this->db,
            new ProductFailingCategoryRepository($this->db),
            new ProductManagementValidator(),
            new ProductManagementIntegrationFakeLogger()
        );

        $result = $service->createCategory([
            'name' => 'Rollback Category',
            'visibility' => 'standard',
            'status' => 'active',
            'display_order' => 8,
        ], 12);

        $this->assertSame(500, $result['status']);
        $count = (int) $this->db->query("SELECT COUNT(*) FROM product_categories WHERE slug = 'rollback-category'")->fetchColumn();

        $this->assertSame(0, $count);
    }

    private function makeService(?ProductCategoryRepository $categories = null): ProductManagementService
    {
        return new ProductManagementService(
            $this->db,
            $categories ?? new ProductCategoryRepository($this->db),
            new ProductManagementValidator(),
            new ProductManagementIntegrationFakeLogger()
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
                price REAL NOT NULL DEFAULT 0,
                stock INTEGER NOT NULL DEFAULT 0,
                currency TEXT NOT NULL DEFAULT "VND",
                track_inventory INTEGER NOT NULL DEFAULT 1,
                visibility TEXT NOT NULL DEFAULT "standard",
                status TEXT NOT NULL DEFAULT "draft",
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES product_categories(id)
            )
        ');
    }

    private function seedBaseData(): void
    {
        $this->db->exec("
            INSERT INTO product_categories (id, name, slug, description, display_order, visibility, status, created_at, updated_at) VALUES
                (1, 'Snacks', 'snacks', 'Popcorn and bites', 1, 'featured', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (2, 'Beverages', 'beverages', 'Drinks and coffee', 2, 'standard', 'inactive', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (3, 'Seasonal', 'seasonal', 'Campaign-only items', 3, 'hidden', 'archived', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
    }
}

class ProductFailingCategoryRepository extends ProductCategoryRepository
{
    public function create(array $data): int
    {
        $categoryId = parent::create($data);

        throw new RuntimeException('Category persistence failed after insert ' . $categoryId);
    }
}

class ProductManagementIntegrationFakeLogger extends Logger
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
