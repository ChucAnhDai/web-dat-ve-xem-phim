<?php

namespace Tests\Integration;

use App\Core\Logger;
use App\Repositories\OrderDetailRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ShopOrderRepository;
use App\Services\ShopOrderLifecycleService;
use App\Services\UserShopOrderService;
use App\Support\AssetUrlResolver;
use App\Validators\ShopOrderAccessValidator;
use PDO;
use PHPUnit\Framework\TestCase;

class UserShopOrderServiceIntegrationTest extends TestCase
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

    public function testListMyOrdersReturnsMemberHistoryWithPreviewItems(): void
    {
        $this->seedOrder([
            'id' => 1,
            'order_code' => 'SHP-MEMBER-001',
            'user_id' => 7,
            'session_token' => null,
            'status' => 'pending',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'quantity' => 2,
            'stock_after_reserve' => 8,
        ]);

        $result = $this->makeService()->listMyOrders(7, ['per_page' => 20]);

        $this->assertSame(200, $result['status']);
        $this->assertSame('member', $result['data']['source']);
        $this->assertCount(1, $result['data']['items']);
        $this->assertSame('SHP-MEMBER-001', $result['data']['items'][0]['order_code']);
        $this->assertTrue($result['data']['items'][0]['can_cancel']);
        $this->assertCount(1, $result['data']['items'][0]['preview_items']);
    }

    public function testListSessionOrdersReturnsOnlyGuestOrdersForCookieSession(): void
    {
        $token = str_repeat('s', 64);
        $this->seedOrder([
            'id' => 2,
            'order_code' => 'SHP-GUEST-SESSION',
            'user_id' => null,
            'session_token' => $token,
            'status' => 'pending',
            'payment_method' => 'vnpay',
            'payment_status' => 'pending',
            'quantity' => 1,
            'stock_after_reserve' => 9,
        ]);
        $this->seedOrder([
            'id' => 3,
            'order_code' => 'SHP-OTHER-SESSION',
            'user_id' => null,
            'session_token' => str_repeat('x', 64),
            'status' => 'completed',
            'payment_method' => 'cash',
            'payment_status' => 'success',
            'quantity' => 1,
            'stock_after_reserve' => 9,
        ]);

        $result = $this->makeService()->listSessionOrders($token, ['per_page' => 20]);

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['data']['session_attached']);
        $this->assertCount(1, $result['data']['items']);
        $this->assertSame('SHP-GUEST-SESSION', $result['data']['items'][0]['order_code']);
        $this->assertTrue($result['data']['items'][0]['is_guest_order']);
    }

    public function testLookupGuestOrderReturnsDetailWhenContactMatches(): void
    {
        $token = str_repeat('g', 64);
        $this->seedOrder([
            'id' => 4,
            'order_code' => 'SHP-GUEST-LOOKUP',
            'user_id' => null,
            'session_token' => $token,
            'status' => 'pending',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'quantity' => 1,
            'stock_after_reserve' => 9,
        ]);

        $result = $this->makeService()->lookupGuestOrder([
            'order_code' => 'SHP-GUEST-LOOKUP',
            'contact_email' => 'guest@example.com',
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertSame('lookup', $result['data']['source']);
        $this->assertSame('SHP-GUEST-LOOKUP', $result['data']['order']['order_code']);
        $this->assertSame('guest@example.com', $result['data']['order']['contact_email']);
    }

    public function testCancelGuestOrderByLookupCancelsOrderAndRestoresInventory(): void
    {
        $token = str_repeat('c', 64);
        $this->seedOrder([
            'id' => 5,
            'order_code' => 'SHP-GUEST-CANCEL',
            'user_id' => null,
            'session_token' => $token,
            'status' => 'pending',
            'payment_method' => 'vnpay',
            'payment_status' => 'pending',
            'quantity' => 2,
            'stock_after_reserve' => 8,
        ]);

        $result = $this->makeService()->cancelGuestOrder([
            'order_code' => 'SHP-GUEST-CANCEL',
            'contact_email' => 'guest@example.com',
            'contact_phone' => '0901234567',
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertSame('cancelled', $result['data']['order']['status']);
        $this->assertSame('cancelled', $result['data']['order']['payment_status']);
        $this->assertSame('cancelled', $this->db->query("SELECT status FROM shop_orders WHERE id = 5")->fetchColumn());
        $this->assertSame('cancelled', $this->db->query("SELECT payment_status FROM payments WHERE shop_order_id = 5")->fetchColumn());
        $this->assertSame(10, (int) $this->db->query("SELECT stock FROM products WHERE id = 1")->fetchColumn());
    }

    private function makeService(): UserShopOrderService
    {
        $logger = new UserShopOrderIntegrationLogger();
        $orders = new ShopOrderRepository($this->db);
        $details = new OrderDetailRepository($this->db);
        $payments = new PaymentRepository($this->db);

        return new UserShopOrderService(
            $this->db,
            $orders,
            $details,
            $payments,
            new ShopOrderAccessValidator(),
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
        $this->db->exec("INSERT INTO users (id, name, email, phone) VALUES (7, 'Member User', 'member@example.com', '0901999999')");
        $this->db->exec("INSERT INTO products (id, slug, sku, name, price, stock, currency, track_inventory, status, visibility, sort_order) VALUES (1, 'large-popcorn-combo', 'SKU-POP-001', 'Large Popcorn Combo', 85000, 10, 'VND', 1, 'active', 'featured', 1)");
        $this->db->exec("INSERT INTO product_images (product_id, asset_type, image_url, alt_text, sort_order, is_primary, status) VALUES (1, 'thumbnail', '/uploads/products/popcorn.jpg', 'Popcorn', 1, 1, 'active')");
    }

    private function seedOrder(array $data): void
    {
        $id = (int) $data['id'];
        $quantity = (int) $data['quantity'];
        $stockAfterReserve = (int) $data['stock_after_reserve'];
        $paymentDueAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        $userIdSql = $data['user_id'] === null ? 'NULL' : (string) (int) $data['user_id'];
        $sessionSql = $data['session_token'] === null ? 'NULL' : "'" . $data['session_token'] . "'";

        $this->db->exec("UPDATE products SET stock = {$stockAfterReserve} WHERE id = 1");
        $this->db->exec("
            INSERT INTO shop_orders (
                id, order_code, user_id, session_token, contact_name, contact_email, contact_phone,
                fulfillment_method, shipping_address_text, shipping_city, shipping_district, item_count,
                subtotal_price, discount_amount, fee_amount, shipping_amount, total_price, currency,
                status, payment_due_at, order_date, updated_at
            ) VALUES (
                {$id}, '{$data['order_code']}', {$userIdSql}, {$sessionSql}, 'Guest User', 'guest@example.com', '0901234567',
                'pickup', NULL, NULL, NULL, {$quantity},
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
        $checkoutUrl = $data['payment_method'] === 'vnpay'
            ? "'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?order={$id}'"
            : 'NULL';
        $this->db->exec("
            INSERT INTO payments (
                shop_order_id, payment_method, payment_status, amount, currency, transaction_code, provider_order_ref,
                provider_message, idempotency_key, checkout_url, initiated_at, payment_date, created_at, updated_at
            ) VALUES (
                {$id}, '{$data['payment_method']}', '{$data['payment_status']}', " . (85000 * $quantity) . ", 'VND', 'PAY-{$id}', '{$data['order_code']}',
                'Seeded payment', 'seed-shop-{$id}', {$checkoutUrl}, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");
    }
}

class UserShopOrderIntegrationLogger extends Logger
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
