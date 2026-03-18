<?php

namespace Tests\Integration;

use App\Core\Logger;
use App\Repositories\CartItemRepository;
use App\Repositories\CartRepository;
use App\Repositories\OrderDetailRepository;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ShopOrderRepository;
use App\Services\ShopCartService;
use App\Services\ShopCheckoutService;
use App\Services\ShopOrderLifecycleService;
use App\Support\VnpayGateway;
use App\Validators\ShopCheckoutValidator;
use App\Validators\ShopCartValidator;
use PDO;
use PHPUnit\Framework\TestCase;

class ShopCheckoutServiceIntegrationTest extends TestCase
{
    private PDO $db;
    private array $paymentConfig;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->db->exec('PRAGMA foreign_keys = ON');

        $this->paymentConfig = [
            'currency' => 'VND',
            'vnpay' => [
                'enabled' => true,
                'version' => '2.1.0',
                'command' => 'pay',
                'locale' => 'vn',
                'curr_code' => 'VND',
                'order_type' => 'other',
                'expire_minutes' => 15,
                'tmn_code' => 'TESTCODE',
                'hash_secret' => 'test-secret',
                'pay_url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
                'return_url' => '',
                'ipn_url' => '',
            ],
        ];

        $this->createSchema();
        $this->seedBaseData();
    }

    public function testCreateCheckoutWithCashPickupConvertsCartAndReservesInventory(): void
    {
        $token = str_repeat('a', 64);
        $this->seedCart(1, $token, [[1, 2, 85000]]);

        $result = $this->makeService()->createCheckout([
            'contact_name' => 'Checkout User',
            'contact_email' => 'checkout@example.com',
            'contact_phone' => '0901234567',
            'fulfillment_method' => 'pickup',
            'payment_method' => 'cash',
        ], 'shop-checkout-cash-001', null, $token, [
            'base_url' => 'http://localhost/web-dat-ve-xem-phim',
            'client_ip' => '127.0.0.1',
        ]);

        $this->assertSame(201, $result['status']);
        $this->assertSame('pending', $result['data']['order']['status']);
        $this->assertSame('cash', $result['data']['payment']['payment_method']);
        $this->assertNull($result['data']['redirect_url']);
        $this->assertSame('converted', $this->db->query('SELECT status FROM carts WHERE id = 1')->fetchColumn());
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM cart_items WHERE cart_id = 1')->fetchColumn());
        $this->assertSame(23, (int) $this->db->query('SELECT stock FROM products WHERE id = 1')->fetchColumn());
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM order_details')->fetchColumn());
    }

    public function testCreateCheckoutWithSameIdempotencyKeyReplaysExistingOrder(): void
    {
        $token = str_repeat('b', 64);
        $this->seedCart(2, $token, [[1, 1, 85000]]);
        $service = $this->makeService();

        $first = $service->createCheckout([
            'contact_name' => 'Checkout User',
            'contact_email' => 'checkout@example.com',
            'contact_phone' => '0901234567',
            'fulfillment_method' => 'pickup',
            'payment_method' => 'vnpay',
        ], 'shop-checkout-vnpay-001', null, $token, [
            'base_url' => 'http://localhost/web-dat-ve-xem-phim',
            'client_ip' => '127.0.0.1',
        ]);

        $second = $service->createCheckout([
            'contact_name' => 'Checkout User',
            'contact_email' => 'checkout@example.com',
            'contact_phone' => '0901234567',
            'fulfillment_method' => 'pickup',
            'payment_method' => 'vnpay',
        ], 'shop-checkout-vnpay-001', null, $token, [
            'base_url' => 'http://localhost/web-dat-ve-xem-phim',
            'client_ip' => '127.0.0.1',
        ]);

        $this->assertSame(201, $first['status']);
        $this->assertStringContainsString('vnp_TxnRef=', $first['data']['redirect_url']);
        $this->assertSame(200, $second['status']);
        $this->assertTrue($second['data']['idempotent_replay']);
        $this->assertSame($first['data']['order']['order_code'], $second['data']['order']['order_code']);
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
    }

    public function testGetCheckoutReturnsActivePendingOrderResumePayload(): void
    {
        $token = str_repeat('c', 64);
        $this->db->exec("
            INSERT INTO shop_orders (
                id, order_code, user_id, session_token, contact_name, contact_email, contact_phone,
                fulfillment_method, item_count, subtotal_price, discount_amount, fee_amount, shipping_amount,
                total_price, currency, status, payment_due_at, order_date, updated_at
            ) VALUES (
                5, 'SHP-RESUME', NULL, '{$token}', 'Resume User', 'resume@example.com', '0901000000',
                'pickup', 1, 85000, 0, 0, 0, 85000, 'VND', 'pending',
                '2099-01-01 00:00:00', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");
        $this->db->exec("
            INSERT INTO order_details (
                order_id, product_id, product_name_snapshot, product_sku_snapshot, quantity, price, discount_amount, line_total
            ) VALUES (
                5, 1, 'Large Popcorn Combo', 'SKU-POP-001', 1, 85000, 0, 85000
            )
        ");
        $this->db->exec("
            INSERT INTO payments (
                shop_order_id, payment_method, payment_status, amount, currency, transaction_code, provider_order_ref,
                provider_message, idempotency_key, checkout_url, initiated_at, payment_date, created_at, updated_at
            ) VALUES (
                5, 'vnpay', 'pending', 85000, 'VND', 'PAY-RESUME', 'SHP-RESUME',
                'Pending redirect to VNPay.', 'shop-resume-001', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?resume=1',
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");

        $result = $this->makeService()->getCheckout(null, $token);

        $this->assertSame(200, $result['status']);
        $this->assertFalse($result['data']['checkout_ready']);
        $this->assertTrue($result['data']['active_order']['resume_available']);
        $this->assertSame('SHP-RESUME', $result['data']['active_order']['order']['order_code']);
    }

    private function makeService(): ShopCheckoutService
    {
        $logger = new ShopCheckoutTestLogger();
        $cartService = new ShopCartService(
            $this->db,
            new CartRepository($this->db),
            new CartItemRepository($this->db),
            new ProductRepository($this->db),
            new ShopCartValidator(),
            $logger
        );

        return new ShopCheckoutService(
            $this->db,
            $cartService,
            new CartRepository($this->db),
            new CartItemRepository($this->db),
            new ProductRepository($this->db),
            new ShopOrderRepository($this->db),
            new OrderDetailRepository($this->db),
            new PaymentRepository($this->db),
            new PaymentMethodRepository($this->db),
            new ShopCheckoutValidator(),
            new ShopOrderLifecycleService($this->db, new ShopOrderRepository($this->db), new OrderDetailRepository($this->db), new ProductRepository($this->db), new PaymentRepository($this->db), $logger),
            new VnpayGateway($this->paymentConfig),
            $logger,
            null,
            $this->paymentConfig
        );
    }

    private function createSchema(): void
    {
        $this->db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, password TEXT, phone TEXT, role TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->db->exec('CREATE TABLE product_categories (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, description TEXT, display_order INTEGER, visibility TEXT, status TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->db->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, category_id INTEGER, slug TEXT, sku TEXT, name TEXT, short_description TEXT, description TEXT, price REAL, compare_at_price REAL, stock INTEGER, currency TEXT, track_inventory INTEGER, status TEXT, visibility TEXT, sort_order INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->db->exec('CREATE TABLE product_details (id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER UNIQUE, brand TEXT, weight TEXT, origin TEXT, description TEXT, attributes_json TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->db->exec('CREATE TABLE product_images (id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER, asset_type TEXT, image_url TEXT, alt_text TEXT, sort_order INTEGER, is_primary INTEGER, status TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->db->exec('CREATE TABLE carts (id INTEGER PRIMARY KEY, user_id INTEGER NULL, session_token TEXT NULL, currency TEXT, status TEXT, expires_at TEXT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->db->exec('CREATE TABLE cart_items (id INTEGER PRIMARY KEY AUTOINCREMENT, cart_id INTEGER, product_id INTEGER, quantity INTEGER, price REAL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->db->exec('CREATE TABLE shop_orders (id INTEGER PRIMARY KEY AUTOINCREMENT, order_code TEXT UNIQUE, user_id INTEGER NULL, session_token TEXT NULL, address_id INTEGER NULL, contact_name TEXT, contact_email TEXT, contact_phone TEXT, fulfillment_method TEXT, shipping_address_text TEXT NULL, shipping_city TEXT NULL, shipping_district TEXT NULL, item_count INTEGER, subtotal_price REAL, discount_amount REAL, fee_amount REAL, shipping_amount REAL, total_price REAL, currency TEXT, status TEXT, order_date TEXT DEFAULT CURRENT_TIMESTAMP, payment_due_at TEXT NULL, confirmed_at TEXT NULL, fulfilled_at TEXT NULL, cancelled_at TEXT NULL, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->db->exec('CREATE TABLE order_details (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER, product_id INTEGER NULL, product_name_snapshot TEXT, product_sku_snapshot TEXT, quantity INTEGER, price REAL, discount_amount REAL, line_total REAL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->db->exec('CREATE TABLE payments (id INTEGER PRIMARY KEY AUTOINCREMENT, ticket_order_id INTEGER NULL, shop_order_id INTEGER NULL, payment_method TEXT, payment_status TEXT, amount REAL, currency TEXT, transaction_code TEXT, provider_transaction_code TEXT NULL, provider_order_ref TEXT NULL, provider_response_code TEXT NULL, provider_message TEXT NULL, idempotency_key TEXT NULL, checkout_url TEXT NULL, request_payload TEXT NULL, callback_payload TEXT NULL, initiated_at TEXT DEFAULT CURRENT_TIMESTAMP, completed_at TEXT NULL, failed_at TEXT NULL, refunded_at TEXT NULL, payment_date TEXT DEFAULT CURRENT_TIMESTAMP, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->db->exec('CREATE TABLE payment_methods (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT, name TEXT, provider TEXT, channel_type TEXT, status TEXT, fee_rate_percent REAL, fixed_fee_amount REAL, settlement_cycle TEXT, supports_refund INTEGER, supports_webhook INTEGER, supports_redirect INTEGER, display_order INTEGER, description TEXT)');
    }

    private function seedBaseData(): void
    {
        $this->db->exec("INSERT INTO product_categories (id, name, slug, description, display_order, visibility, status) VALUES (1, 'Snacks', 'snacks', 'Fresh cinema snacks', 1, 'featured', 'active')");
        $this->db->exec("INSERT INTO products (id, category_id, slug, sku, name, short_description, description, price, compare_at_price, stock, currency, track_inventory, status, visibility, sort_order) VALUES (1, 1, 'large-popcorn-combo', 'SKU-POP-001', 'Large Popcorn Combo', 'Best-selling combo', 'Served hot and fresh', 85000, 99000, 25, 'VND', 1, 'active', 'featured', 1)");
        $this->db->exec("INSERT INTO product_details (product_id, brand, weight, origin, description, attributes_json) VALUES (1, 'CineShop', '380g', 'Vietnam', 'Detailed bundle information', '{\"bundle\":true}')");
        $this->db->exec("INSERT INTO product_images (product_id, asset_type, image_url, alt_text, sort_order, is_primary, status) VALUES (1, 'thumbnail', 'https://example.com/popcorn-thumb.jpg', 'Popcorn combo thumbnail', 1, 1, 'active')");
        $this->db->exec("INSERT INTO payment_methods (code, name, provider, channel_type, status, fee_rate_percent, fixed_fee_amount, settlement_cycle, supports_refund, supports_webhook, supports_redirect, display_order, description) VALUES ('vnpay', 'VNPay', 'vnpay', 'gateway', 'active', 2.1, 0, 'T+1', 1, 1, 1, 1, 'VNPay gateway'), ('cash', 'Cash At Counter', 'internal', 'counter', 'active', 0, 0, 'instant', 1, 0, 0, 2, 'Cash pickup')");
    }

    private function seedCart(int $cartId, string $token, array $lines): void
    {
        $this->db->exec("INSERT INTO carts (id, user_id, session_token, currency, status, expires_at, created_at, updated_at) VALUES ({$cartId}, NULL, '{$token}', 'VND', 'active', '2099-01-01 00:00:00', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $stmt = $this->db->prepare('INSERT INTO cart_items (cart_id, product_id, quantity, price, created_at, updated_at) VALUES (:cart_id, :product_id, :quantity, :price, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
        foreach ($lines as $line) {
            $stmt->execute([
                'cart_id' => $cartId,
                'product_id' => $line[0],
                'quantity' => $line[1],
                'price' => $line[2],
            ]);
        }
    }
}

class ShopCheckoutTestLogger extends Logger
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
