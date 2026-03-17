<?php

namespace Tests\Integration;

use App\Core\Logger;
use App\Repositories\CartItemRepository;
use App\Repositories\CartRepository;
use App\Repositories\ProductRepository;
use App\Services\ShopCartService;
use App\Validators\ShopCartValidator;
use PDO;
use PHPUnit\Framework\TestCase;

class ShopCartServiceIntegrationTest extends TestCase
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

    public function testAddItemCreatesGuestCartAndReturnsSummary(): void
    {
        $service = $this->makeService();

        $result = $service->addItem([
            'product_id' => 1,
            'quantity' => 2,
        ], null, str_repeat('a', 64));

        $this->assertSame(201, $result['status']);
        $this->assertSame(2, $result['data']['cart']['item_count']);
        $this->assertSame(170000.0, $result['data']['cart']['subtotal_price']);
        $this->assertCount(1, $result['data']['cart']['items']);

        $cartRow = $this->db->query("SELECT user_id, session_token, status FROM carts ORDER BY id DESC LIMIT 1")->fetch();
        $this->assertNull($cartRow['user_id']);
        $this->assertSame(str_repeat('a', 64), $cartRow['session_token']);
        $this->assertSame('active', $cartRow['status']);
    }

    public function testGetCartMergesGuestCartIntoAuthenticatedUserCart(): void
    {
        $guestToken = str_repeat('b', 64);

        $this->db->exec("
            INSERT INTO carts (id, user_id, session_token, currency, status, expires_at, created_at, updated_at)
            VALUES (1, NULL, '{$guestToken}', 'VND', 'active', '2099-01-01 00:00:00', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $this->db->exec("
            INSERT INTO cart_items (cart_id, product_id, quantity, price, created_at, updated_at)
            VALUES (1, 1, 1, 85000, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $this->db->exec("
            INSERT INTO carts (id, user_id, session_token, currency, status, expires_at, created_at, updated_at)
            VALUES (2, 9, NULL, 'VND', 'active', '2099-01-01 00:00:00', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $this->db->exec("
            INSERT INTO cart_items (cart_id, product_id, quantity, price, created_at, updated_at)
            VALUES (2, 2, 2, 65000, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $service = $this->makeService();
        $result = $service->getCart(9, $guestToken);

        $this->assertSame(200, $result['status']);
        $this->assertSame(1, $result['data']['sync']['merged_guest_cart']);
        $this->assertSame(3, $result['data']['cart']['item_count']);
        $this->assertCount(2, $result['data']['cart']['items']);

        $guestStatus = $this->db->query("SELECT status FROM carts WHERE id = 1")->fetchColumn();
        $userItemCount = (int) $this->db->query("SELECT COUNT(*) FROM cart_items WHERE cart_id = 2")->fetchColumn();

        $this->assertSame('abandoned', $guestStatus);
        $this->assertSame(2, $userItemCount);
    }

    public function testGetCartReconcilesItemQuantityWhenInventoryDrops(): void
    {
        $token = str_repeat('c', 64);

        $this->db->exec("
            INSERT INTO carts (id, user_id, session_token, currency, status, expires_at, created_at, updated_at)
            VALUES (3, NULL, '{$token}', 'VND', 'active', '2099-01-01 00:00:00', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $this->db->exec("
            INSERT INTO cart_items (cart_id, product_id, quantity, price, created_at, updated_at)
            VALUES (3, 1, 7, 85000, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $this->db->exec("UPDATE products SET stock = 3 WHERE id = 1");

        $service = $this->makeService();
        $result = $service->getCart(null, $token);

        $this->assertSame(200, $result['status']);
        $this->assertSame(1, $result['data']['sync']['adjusted_items']);
        $this->assertSame(3, $result['data']['cart']['items'][0]['quantity']);

        $storedQuantity = (int) $this->db->query("SELECT quantity FROM cart_items WHERE cart_id = 3 AND product_id = 1")->fetchColumn();
        $this->assertSame(3, $storedQuantity);
    }

    public function testClearCartDeletesAllItems(): void
    {
        $token = str_repeat('d', 64);

        $this->db->exec("
            INSERT INTO carts (id, user_id, session_token, currency, status, expires_at, created_at, updated_at)
            VALUES (4, NULL, '{$token}', 'VND', 'active', '2099-01-01 00:00:00', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $this->db->exec("
            INSERT INTO cart_items (cart_id, product_id, quantity, price, created_at, updated_at)
            VALUES (4, 1, 1, 85000, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $service = $this->makeService();
        $result = $service->clearCart(null, $token);

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['data']['cart']['is_empty']);
        $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) FROM cart_items WHERE cart_id = 4")->fetchColumn());
    }

    private function makeService(): ShopCartService
    {
        return new ShopCartService(
            $this->db,
            new CartRepository($this->db),
            new CartItemRepository($this->db),
            new ProductRepository($this->db),
            new ShopCartValidator(),
            new ShopCartFakeLogger()
        );
    }

    private function createSchema(): void
    {
        $this->db->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                password TEXT NOT NULL,
                phone TEXT,
                role TEXT NOT NULL DEFAULT "user",
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');

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

        $this->db->exec('
            CREATE TABLE carts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                session_token TEXT NULL,
                currency TEXT NOT NULL DEFAULT "VND",
                status TEXT NOT NULL DEFAULT "active",
                expires_at TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ');

        $this->db->exec('
            CREATE TABLE cart_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cart_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL DEFAULT 1,
                price REAL NOT NULL DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (cart_id) REFERENCES carts(id),
                FOREIGN KEY (product_id) REFERENCES products(id)
            )
        ');
    }

    private function seedBaseData(): void
    {
        $this->db->exec("
            INSERT INTO users (id, name, email, password, phone, role, created_at)
            VALUES (9, 'User Nine', 'user9@example.com', 'hashed', '0900000000', 'user', CURRENT_TIMESTAMP)
        ");

        $this->db->exec("
            INSERT INTO product_categories (id, name, slug, description, display_order, visibility, status, created_at, updated_at) VALUES
                (1, 'Snacks', 'snacks', 'Fresh cinema snacks', 1, 'featured', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $this->db->exec("
            INSERT INTO products (
                id, category_id, slug, sku, name, short_description, description, price, compare_at_price, stock, currency,
                track_inventory, status, visibility, sort_order, created_at, updated_at
            ) VALUES
                (1, 1, 'large-popcorn-combo', 'SKU-POP-001', 'Large Popcorn Combo', 'Best-selling combo', 'Served hot and fresh', 85000, 99000, 25, 'VND', 1, 'active', 'featured', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (2, 1, 'nacho-dip-set', 'SKU-NACHO-001', 'Nacho Dip Set', 'Crunchy and shareable', 'Loaded with cheese dip', 65000, NULL, 12, 'VND', 1, 'active', 'standard', 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $this->db->exec("
            INSERT INTO product_details (product_id, brand, weight, origin, description, attributes_json, created_at, updated_at) VALUES
                (1, 'CineShop', '380g', 'Vietnam', 'Detailed bundle information', '{\"bundle\":true}', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (2, 'SnackLab', '250g', 'Vietnam', 'Cheesy nacho description', '{\"spice\":\"mild\"}', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $this->db->exec("
            INSERT INTO product_images (product_id, asset_type, image_url, alt_text, sort_order, is_primary, status, created_at, updated_at) VALUES
                (1, 'thumbnail', 'https://example.com/popcorn-thumb.jpg', 'Popcorn combo thumbnail', 1, 1, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (2, 'thumbnail', 'https://example.com/nacho-thumb.jpg', 'Nacho thumbnail', 1, 1, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
    }
}

class ShopCartFakeLogger extends Logger
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
