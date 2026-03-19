<?php

namespace Tests\Integration;

use App\Core\Logger;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\PaymentRepository;
use App\Services\AdminPaymentManagementService;
use App\Validators\AdminPaymentManagementValidator;
use PDO;
use PHPUnit\Framework\TestCase;

class AdminPaymentManagementServiceIntegrationTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->createSchema();
        $this->seedBaseData();
    }

    public function testListPaymentsReturnsLiveSummaryAndMethodOptions(): void
    {
        $service = $this->makeService();

        $result = $service->listPayments([
            'search' => 'ORD-',
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertSame(2, $result['data']['meta']['total']);
        $this->assertSame(2, $result['data']['summary']['total_records']);
        $this->assertSame(125000.0, $result['data']['summary']['captured_value']);
        $this->assertSame(1, $result['data']['summary']['issue_count']);
        $this->assertSame('ORD-SHOP-001', $result['data']['items'][0]['order_code']);
        $this->assertSame('shop', $result['data']['items'][0]['order_scope']);
        $this->assertCount(2, $result['data']['method_options']);
        $this->assertSame('momo', $result['data']['method_options'][0]['code']);
        $this->assertSame('vnpay', $result['data']['method_options'][1]['code']);
    }

    public function testCreateUpdateAndArchivePaymentMethodPersistLiveSqlChanges(): void
    {
        $service = $this->makeService();

        $created = $service->createPaymentMethod([
            'code' => 'stripe',
            'name' => 'Stripe Gateway',
            'provider' => 'stripe',
            'channel_type' => 'gateway',
            'status' => 'active',
            'fee_rate_percent' => '2.90',
            'fixed_fee_amount' => '1000',
            'settlement_cycle' => 'T+2',
            'supports_refund' => true,
            'supports_webhook' => true,
            'supports_redirect' => true,
            'display_order' => '5',
            'description' => 'Card processor',
        ], 7);

        $this->assertSame(201, $created['status']);
        $methodId = (int) $created['data']['id'];
        $this->assertSame('stripe', $this->db->query("SELECT code FROM payment_methods WHERE id = {$methodId}")->fetchColumn());

        $updated = $service->updatePaymentMethod($methodId, [
            'code' => 'stripe',
            'name' => 'Stripe Checkout',
            'provider' => 'stripe',
            'channel_type' => 'gateway',
            'status' => 'maintenance',
            'fee_rate_percent' => '3.10',
            'fixed_fee_amount' => '1500',
            'settlement_cycle' => 'T+3',
            'supports_refund' => true,
            'supports_webhook' => false,
            'supports_redirect' => true,
            'display_order' => '6',
            'description' => 'Temporarily throttled',
        ], 7);

        $this->assertSame(200, $updated['status']);
        $this->assertSame('maintenance', $updated['data']['status']);
        $this->assertSame('Stripe Checkout', $this->db->query("SELECT name FROM payment_methods WHERE id = {$methodId}")->fetchColumn());

        $archived = $service->archivePaymentMethod($methodId, 7);

        $this->assertSame(200, $archived['status']);
        $this->assertSame('disabled', $archived['data']['status']);
        $this->assertSame('disabled', $this->db->query("SELECT status FROM payment_methods WHERE id = {$methodId}")->fetchColumn());
    }

    private function makeService(): AdminPaymentManagementService
    {
        return new AdminPaymentManagementService(
            $this->db,
            new PaymentRepository($this->db),
            new PaymentMethodRepository($this->db),
            new AdminPaymentManagementValidator(),
            new AdminPaymentIntegrationLogger()
        );
    }

    private function createSchema(): void
    {
        $this->db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, phone TEXT)');
        $this->db->exec('CREATE TABLE ticket_orders (id INTEGER PRIMARY KEY, user_id INTEGER NULL, order_code TEXT, status TEXT, contact_name TEXT, contact_email TEXT, contact_phone TEXT, total_price REAL, currency TEXT, order_date TEXT)');
        $this->db->exec('CREATE TABLE shop_orders (id INTEGER PRIMARY KEY, user_id INTEGER NULL, order_code TEXT, status TEXT, contact_name TEXT, contact_email TEXT, contact_phone TEXT, total_price REAL, currency TEXT, order_date TEXT)');
        $this->db->exec('CREATE TABLE payment_methods (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT UNIQUE, name TEXT, provider TEXT, channel_type TEXT, status TEXT, fee_rate_percent REAL, fixed_fee_amount REAL, settlement_cycle TEXT, supports_refund INTEGER, supports_webhook INTEGER, supports_redirect INTEGER, display_order INTEGER, description TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->db->exec('CREATE TABLE payments (id INTEGER PRIMARY KEY AUTOINCREMENT, ticket_order_id INTEGER NULL, shop_order_id INTEGER NULL, payment_method TEXT, payment_status TEXT, amount REAL, currency TEXT, transaction_code TEXT, provider_transaction_code TEXT NULL, provider_order_ref TEXT NULL, provider_response_code TEXT NULL, provider_message TEXT NULL, idempotency_key TEXT NULL, checkout_url TEXT NULL, request_payload TEXT NULL, callback_payload TEXT NULL, initiated_at TEXT DEFAULT CURRENT_TIMESTAMP, completed_at TEXT NULL, failed_at TEXT NULL, refunded_at TEXT NULL, payment_date TEXT DEFAULT CURRENT_TIMESTAMP, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    }

    private function seedBaseData(): void
    {
        $this->db->exec("INSERT INTO users (id, name, email, phone) VALUES (1, 'Admin User', 'admin@example.com', '0901234567')");
        $this->db->exec("
            INSERT INTO ticket_orders (id, user_id, order_code, status, contact_name, contact_email, contact_phone, total_price, currency, order_date)
            VALUES (1, 1, 'ORD-TICKET-001', 'confirmed', 'Ticket Buyer', 'ticket@example.com', '0900000001', 95000, 'VND', '2026-03-18 08:30:00')
        ");
        $this->db->exec("
            INSERT INTO shop_orders (id, user_id, order_code, status, contact_name, contact_email, contact_phone, total_price, currency, order_date)
            VALUES (2, 1, 'ORD-SHOP-001', 'confirmed', 'Shop Buyer', 'shop@example.com', '0900000002', 125000, 'VND', '2026-03-18 09:00:00')
        ");
        $this->db->exec("
            INSERT INTO payment_methods (code, name, provider, channel_type, status, fee_rate_percent, fixed_fee_amount, settlement_cycle, supports_refund, supports_webhook, supports_redirect, display_order, description)
            VALUES
                ('momo', 'MoMo Wallet', 'momo', 'e_wallet', 'active', 2.4, 0, 'T+1', 1, 1, 1, 1, 'Wallet'),
                ('vnpay', 'VNPay', 'vnpay', 'gateway', 'active', 2.1, 0, 'T+1', 1, 1, 1, 2, 'Gateway')
        ");
        $this->db->exec("
            INSERT INTO payments (
                ticket_order_id, shop_order_id, payment_method, payment_status, amount, currency, transaction_code,
                provider_transaction_code, provider_order_ref, provider_response_code, provider_message, idempotency_key,
                request_payload, callback_payload, initiated_at, completed_at, failed_at, refunded_at, payment_date, created_at, updated_at
            ) VALUES
                (1, NULL, 'momo', 'failed', 95000, 'VND', 'PAY-TKT-001', 'MOMO-001', 'ORD-TICKET-001', '99', 'Declined', 'idem-1', '{\"intent\":\"ticket\"}', '{\"status\":\"failed\"}', '2026-03-18 08:30:00', NULL, '2026-03-18 08:40:00', NULL, '2026-03-18 08:40:00', '2026-03-18 08:30:00', '2026-03-18 08:40:00'),
                (NULL, 2, 'vnpay', 'success', 125000, 'VND', 'PAY-SHP-001', 'VNP-001', 'ORD-SHOP-001', '00', 'Captured', 'idem-2', '{\"intent\":\"shop\"}', '{\"status\":\"success\"}', '2026-03-18 09:00:00', '2026-03-18 09:03:00', NULL, NULL, '2026-03-18 09:03:00', '2026-03-18 09:00:00', '2026-03-18 09:03:00')
        ");
    }
}

class AdminPaymentIntegrationLogger extends Logger
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
