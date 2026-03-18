<?php

namespace Tests\Integration;

use App\Core\Logger;
use App\Repositories\OrderDetailRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ShopOrderRepository;
use App\Services\AdminShopOrderManagementService;
use App\Services\ShopOrderLifecycleService;
use App\Support\AssetUrlResolver;
use App\Validators\ShopOrderAdminValidator;
use PDO;
use PHPUnit\Framework\TestCase;

class AdminShopOrderManagementServiceIntegrationTest extends TestCase
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

    public function testListShopOrdersReturnsLiveOrderSummary(): void
    {
        $this->seedShopOrder([
            'id' => 1,
            'order_code' => 'SHP-LIVE-001',
            'status' => 'pending',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'quantity' => 2,
            'reserved_stock' => 8,
        ]);

        $result = $this->makeService()->listShopOrders([
            'search' => 'SHP-LIVE',
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertSame(1, (int) $result['data']['summary']['total_orders']);
        $this->assertSame('SHP-LIVE-001', $result['data']['items'][0]['order_code']);
        $this->assertSame(['confirmed', 'cancelled'], $result['data']['items'][0]['allowed_next_statuses']);
    }

    public function testUpdateShopOrderStatusConfirmsPendingCashOrderAndSettlesPayment(): void
    {
        $this->seedShopOrder([
            'id' => 2,
            'order_code' => 'SHP-CASH-001',
            'status' => 'pending',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'quantity' => 1,
            'reserved_stock' => 9,
        ]);

        $result = $this->makeService()->updateShopOrderStatus(2, [
            'status' => 'confirmed',
        ], 99);

        $this->assertSame(200, $result['status']);
        $this->assertSame('confirmed', $result['data']['status']);
        $this->assertSame('success', $result['data']['payment_status']);
        $this->assertSame('confirmed', $this->db->query("SELECT status FROM shop_orders WHERE id = 2")->fetchColumn());
        $this->assertSame('success', $this->db->query("SELECT payment_status FROM payments WHERE shop_order_id = 2")->fetchColumn());
        $this->assertNull($this->db->query("SELECT payment_due_at FROM shop_orders WHERE id = 2")->fetchColumn());
    }

    public function testUpdateShopOrderStatusCancelsPendingOrderRestoresInventoryAndPaymentState(): void
    {
        $this->seedShopOrder([
            'id' => 3,
            'order_code' => 'SHP-CANCEL-001',
            'status' => 'pending',
            'payment_method' => 'vnpay',
            'payment_status' => 'pending',
            'quantity' => 2,
            'reserved_stock' => 8,
        ]);

        $result = $this->makeService()->updateShopOrderStatus(3, [
            'status' => 'cancelled',
        ], 99);

        $this->assertSame(200, $result['status']);
        $this->assertSame('cancelled', $result['data']['status']);
        $this->assertSame('cancelled', $result['data']['payment_status']);
        $this->assertSame('cancelled', $this->db->query("SELECT status FROM shop_orders WHERE id = 3")->fetchColumn());
        $this->assertSame('cancelled', $this->db->query("SELECT payment_status FROM payments WHERE shop_order_id = 3")->fetchColumn());
        $this->assertSame(10, (int) $this->db->query("SELECT stock FROM products WHERE id = 1")->fetchColumn());
    }

    private function makeService(): AdminShopOrderManagementService
    {
        $logger = new AdminShopOrderIntegrationLogger();
        $orders = new ShopOrderRepository($this->db);
        $details = new OrderDetailRepository($this->db);
        $payments = new PaymentRepository($this->db);

        return new AdminShopOrderManagementService(
            $this->db,
            $orders,
            $details,
            $payments,
            new ShopOrderAdminValidator(),
            new ShopOrderLifecycleService($this->db, $orders, $details, new ProductRepository($this->db), $payments, $logger),
            $logger,
            new AssetUrlResolver('http://localhost/web-dat-ve-xem-phim')
        );
    }

    private function createSchema(): void
    {
        $this->db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, phone TEXT)');
        $this->db->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, category_id INTEGER NULL, slug TEXT, sku TEXT, name TEXT, short_description TEXT, description TEXT, price REAL, compare_at_price REAL, stock INTEGER, currency TEXT, track_inventory INTEGER, status TEXT, visibility TEXT, sort_order INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->db->exec('CREATE TABLE product_images (id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER, asset_type TEXT, image_url TEXT, alt_text TEXT, sort_order INTEGER, is_primary INTEGER, status TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->db->exec('CREATE TABLE shop_orders (id INTEGER PRIMARY KEY AUTOINCREMENT, order_code TEXT UNIQUE, user_id INTEGER NULL, session_token TEXT NULL, address_id INTEGER NULL, contact_name TEXT, contact_email TEXT, contact_phone TEXT, fulfillment_method TEXT, shipping_address_text TEXT NULL, shipping_city TEXT NULL, shipping_district TEXT NULL, item_count INTEGER, subtotal_price REAL, discount_amount REAL, fee_amount REAL, shipping_amount REAL, total_price REAL, currency TEXT, status TEXT, order_date TEXT DEFAULT CURRENT_TIMESTAMP, payment_due_at TEXT NULL, confirmed_at TEXT NULL, fulfilled_at TEXT NULL, cancelled_at TEXT NULL, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->db->exec('CREATE TABLE order_details (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER, product_id INTEGER NULL, product_name_snapshot TEXT, product_sku_snapshot TEXT, quantity INTEGER, price REAL, discount_amount REAL, line_total REAL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->db->exec('CREATE TABLE payments (id INTEGER PRIMARY KEY AUTOINCREMENT, ticket_order_id INTEGER NULL, shop_order_id INTEGER NULL, payment_method TEXT, payment_status TEXT, amount REAL, currency TEXT, transaction_code TEXT, provider_transaction_code TEXT NULL, provider_order_ref TEXT NULL, provider_response_code TEXT NULL, provider_message TEXT NULL, idempotency_key TEXT NULL, checkout_url TEXT NULL, request_payload TEXT NULL, callback_payload TEXT NULL, initiated_at TEXT DEFAULT CURRENT_TIMESTAMP, completed_at TEXT NULL, failed_at TEXT NULL, refunded_at TEXT NULL, payment_date TEXT DEFAULT CURRENT_TIMESTAMP, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    }

    private function seedBaseData(): void
    {
        $this->db->exec("INSERT INTO users (id, name, email, phone) VALUES (1, 'Shop Admin User', 'shop@example.com', '0901234567')");
        $this->db->exec("INSERT INTO products (id, slug, sku, name, price, stock, currency, track_inventory, status, visibility, sort_order) VALUES (1, 'large-popcorn-combo', 'SKU-POP-001', 'Large Popcorn Combo', 85000, 10, 'VND', 1, 'active', 'featured', 1)");
        $this->db->exec("INSERT INTO product_images (product_id, asset_type, image_url, alt_text, sort_order, is_primary, status) VALUES (1, 'thumbnail', '/uploads/products/popcorn.jpg', 'Popcorn', 1, 1, 'active')");
    }

    private function seedShopOrder(array $data): void
    {
        $id = (int) $data['id'];
        $quantity = (int) $data['quantity'];
        $reservedStock = (int) $data['reserved_stock'];
        $paymentDueAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $this->db->exec("UPDATE products SET stock = {$reservedStock} WHERE id = 1");
        $this->db->exec("
            INSERT INTO shop_orders (
                id, order_code, user_id, session_token, contact_name, contact_email, contact_phone,
                fulfillment_method, shipping_address_text, shipping_city, shipping_district, item_count,
                subtotal_price, discount_amount, fee_amount, shipping_amount, total_price, currency,
                status, payment_due_at, order_date, updated_at
            ) VALUES (
                {$id}, '{$data['order_code']}', 1, 'shop-session-{$id}', 'Checkout User', 'checkout@example.com', '0901234567',
                'delivery', '123 Test Street', 'Ho Chi Minh City', 'District 1', {$quantity},
                " . (85000 * $quantity) . ", 0, 0, 0, " . (85000 * $quantity) . ", 'VND',
                '{$data['status']}', '{$paymentDueAt}', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");
        $this->db->exec("
            INSERT INTO order_details (
                order_id, product_id, product_name_snapshot, product_sku_snapshot, quantity, price, discount_amount, line_total
            ) VALUES (
                {$id}, 1, 'Large Popcorn Combo', 'SKU-POP-001', {$quantity}, 85000, 0, " . (85000 * $quantity) . "
            )
        ");
        $this->db->exec("
            INSERT INTO payments (
                shop_order_id, payment_method, payment_status, amount, currency, transaction_code, provider_order_ref,
                provider_message, idempotency_key, initiated_at, payment_date, created_at, updated_at
            ) VALUES (
                {$id}, '{$data['payment_method']}', '{$data['payment_status']}', " . (85000 * $quantity) . ", 'VND', 'PAY-{$id}', '{$data['order_code']}',
                'Seeded payment', 'seed-shop-{$id}', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");
    }
}

class AdminShopOrderIntegrationLogger extends Logger
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
