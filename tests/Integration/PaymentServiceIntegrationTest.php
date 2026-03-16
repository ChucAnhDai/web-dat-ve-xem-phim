<?php

namespace Tests\Integration;

use App\Core\Logger;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\SeatRepository;
use App\Repositories\ShowtimeRepository;
use App\Repositories\TicketOrderRepository;
use App\Repositories\TicketSeatHoldRepository;
use App\Services\PaymentService;
use App\Services\TicketCheckoutContextService;
use App\Services\TicketLifecycleService;
use App\Support\VnpayGateway;
use App\Validators\PaymentValidator;
use PDO;
use PHPUnit\Framework\TestCase;

class PaymentServiceIntegrationTest extends TestCase
{
    private PDO $db;
    private array $config;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->db->exec('PRAGMA foreign_keys = ON');

        $this->config = [
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

    public function testCreateTicketVnpayIntentPersistsPendingOrderAndPayment(): void
    {
        $sessionToken = str_repeat('c', 48);
        $this->seedHoldRows($sessionToken, [1, 2]);

        $result = $this->makeService()->createTicketVnpayIntent([
            'showtime_id' => 100,
            'seat_ids' => [1, 2],
            'contact_name' => 'VNPay Guest',
            'contact_email' => 'vnpay@example.com',
            'contact_phone' => '0901234567',
            'payment_method' => 'vnpay',
            'fulfillment_method' => 'e_ticket',
        ], $sessionToken, 9, [
            'base_url' => 'http://localhost/web-dat-ve-xem-phim',
            'client_ip' => '127.0.0.1',
        ]);

        $this->assertSame(201, $result['status']);
        $this->assertSame('pending', $result['data']['order']['status']);
        $this->assertSame('pending', $result['data']['payment']['payment_status']);
        $this->assertStringContainsString('vnp_TxnRef=', $result['data']['redirect_url']);
        $this->assertSame(1, $this->countRows('ticket_orders'));
        $this->assertSame(2, $this->countRows('ticket_details'));
        $this->assertSame(1, $this->countRows('payments'));
        $this->assertSame(0, $this->countRows('ticket_seat_holds'));
    }

    public function testHandleVnpayIpnMarksPendingOrderAsPaid(): void
    {
        $sessionToken = str_repeat('d', 48);
        $this->seedHoldRows($sessionToken, [1, 2]);

        $intent = $this->makeService()->createTicketVnpayIntent([
            'showtime_id' => 100,
            'seat_ids' => [1, 2],
            'contact_name' => 'VNPay Guest',
            'contact_email' => 'vnpay@example.com',
            'contact_phone' => '0901234567',
            'payment_method' => 'vnpay',
            'fulfillment_method' => 'e_ticket',
        ], $sessionToken, 9, [
            'base_url' => 'http://localhost/web-dat-ve-xem-phim',
            'client_ip' => '127.0.0.1',
        ]);

        $orderCode = (string) $intent['data']['order']['order_code'];
        $amount = (float) $intent['data']['payment']['amount'];
        $callback = [
            'vnp_TxnRef' => $orderCode,
            'vnp_Amount' => (string) ((int) round($amount * 100)),
            'vnp_ResponseCode' => '00',
            'vnp_TransactionStatus' => '00',
            'vnp_TransactionNo' => '135790',
            'vnp_OrderInfo' => 'Thanh toan ve xem phim ' . $orderCode,
        ];
        $callback['vnp_SecureHash'] = $this->signPayload($callback);

        $result = $this->makeService()->handleVnpayIpn($callback);

        $this->assertSame(200, $result['status']);
        $this->assertSame('00', $result['data']['RspCode']);

        $orderRow = $this->db->query('SELECT status, paid_at FROM ticket_orders LIMIT 1')->fetch();
        $paymentRow = $this->db->query('SELECT payment_status, provider_transaction_code FROM payments LIMIT 1')->fetch();
        $ticketStatuses = $this->db->query('SELECT status FROM ticket_details ORDER BY id ASC')->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame('paid', $orderRow['status']);
        $this->assertNotEmpty($orderRow['paid_at']);
        $this->assertSame('success', $paymentRow['payment_status']);
        $this->assertSame('135790', $paymentRow['provider_transaction_code']);
        $this->assertSame(['paid', 'paid'], $ticketStatuses);
    }

    public function testCreateTicketVnpayIntentRejectsSecondPendingCheckoutForSameSession(): void
    {
        $sessionToken = str_repeat('e', 48);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        $this->db->exec("
            INSERT INTO ticket_orders (
                id, order_code, user_id, session_token, status, hold_expires_at
            ) VALUES (
                9, 'TKT-ACTIVE', 9, '{$sessionToken}', 'pending', '{$expiresAt}'
            )
        ");
        $this->seedHoldRows($sessionToken, [1, 2]);

        $result = $this->makeService()->createTicketVnpayIntent([
            'showtime_id' => 100,
            'seat_ids' => [1, 2],
            'contact_name' => 'VNPay Guest',
            'contact_email' => 'vnpay@example.com',
            'contact_phone' => '0901234567',
            'payment_method' => 'vnpay',
            'fulfillment_method' => 'e_ticket',
        ], $sessionToken, 9, [
            'base_url' => 'http://localhost/web-dat-ve-xem-phim',
            'client_ip' => '127.0.0.1',
        ]);

        $this->assertSame(409, $result['status']);
        $this->assertSame(['You already have a checkout waiting for payment in this session.'], $result['errors']['checkout']);
    }

    private function makeService(): PaymentService
    {
        $holds = new TicketSeatHoldRepository($this->db);
        $orders = new TicketOrderRepository($this->db);
        $payments = new PaymentRepository($this->db);

        return new PaymentService(
            $this->db,
            new TicketCheckoutContextService($this->db, new ShowtimeRepository($this->db), new SeatRepository($this->db), $holds),
            $holds,
            $orders,
            $payments,
            new PaymentMethodRepository($this->db),
            new PaymentValidator(),
            new TicketLifecycleService($this->db, $holds, $orders, $payments, new IntegrationPaymentLogger()),
            new VnpayGateway($this->config),
            $this->config,
            new IntegrationPaymentLogger()
        );
    }

    private function createSchema(): void
    {
        $this->db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');

        $this->db->exec('
            CREATE TABLE movies (
                id INTEGER PRIMARY KEY,
                slug TEXT NOT NULL UNIQUE,
                title TEXT NOT NULL,
                poster_url TEXT,
                status TEXT NOT NULL
            )
        ');

        $this->db->exec('
            CREATE TABLE cinemas (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                city TEXT NOT NULL,
                status TEXT NOT NULL
            )
        ');

        $this->db->exec('
            CREATE TABLE rooms (
                id INTEGER PRIMARY KEY,
                cinema_id INTEGER NOT NULL,
                room_name TEXT NOT NULL,
                room_type TEXT,
                screen_label TEXT,
                total_seats INTEGER DEFAULT 0,
                status TEXT NOT NULL,
                FOREIGN KEY (cinema_id) REFERENCES cinemas(id)
            )
        ');

        $this->db->exec('
            CREATE TABLE seats (
                id INTEGER PRIMARY KEY,
                room_id INTEGER NOT NULL,
                seat_row TEXT NOT NULL,
                seat_number INTEGER NOT NULL,
                seat_type TEXT NOT NULL,
                status TEXT NOT NULL,
                FOREIGN KEY (room_id) REFERENCES rooms(id)
            )
        ');

        $this->db->exec('
            CREATE TABLE showtimes (
                id INTEGER PRIMARY KEY,
                movie_id INTEGER NOT NULL,
                room_id INTEGER NOT NULL,
                show_date TEXT NOT NULL,
                start_time TEXT NOT NULL,
                end_time TEXT NOT NULL,
                price REAL NOT NULL,
                status TEXT NOT NULL,
                presentation_type TEXT,
                language_version TEXT,
                FOREIGN KEY (movie_id) REFERENCES movies(id),
                FOREIGN KEY (room_id) REFERENCES rooms(id)
            )
        ');

        $this->db->exec('
            CREATE TABLE ticket_orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_code TEXT NOT NULL UNIQUE,
                user_id INTEGER NULL,
                session_token TEXT NULL,
                contact_name TEXT,
                contact_email TEXT,
                contact_phone TEXT,
                fulfillment_method TEXT,
                seat_count INTEGER DEFAULT 0,
                subtotal_price REAL DEFAULT 0,
                discount_amount REAL DEFAULT 0,
                fee_amount REAL DEFAULT 0,
                total_price REAL DEFAULT 0,
                currency TEXT DEFAULT "VND",
                status TEXT NOT NULL,
                hold_expires_at TEXT NULL,
                paid_at TEXT NULL,
                cancelled_at TEXT NULL,
                order_date TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ');

        $this->db->exec('
            CREATE TABLE ticket_details (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                showtime_id INTEGER NOT NULL,
                seat_id INTEGER NOT NULL,
                ticket_code TEXT NOT NULL UNIQUE,
                status TEXT NOT NULL,
                base_price REAL DEFAULT 0,
                surcharge_amount REAL DEFAULT 0,
                discount_amount REAL DEFAULT 0,
                price REAL DEFAULT 0,
                qr_payload TEXT,
                scanned_at TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (order_id) REFERENCES ticket_orders(id),
                FOREIGN KEY (showtime_id) REFERENCES showtimes(id),
                FOREIGN KEY (seat_id) REFERENCES seats(id)
            )
        ');

        $this->db->exec('
            CREATE TABLE ticket_seat_holds (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                showtime_id INTEGER NOT NULL,
                seat_id INTEGER NOT NULL,
                user_id INTEGER NULL,
                session_token TEXT NOT NULL,
                hold_expires_at TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (showtime_id, seat_id),
                FOREIGN KEY (showtime_id) REFERENCES showtimes(id),
                FOREIGN KEY (seat_id) REFERENCES seats(id),
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ');

        $this->db->exec('
            CREATE TABLE payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ticket_order_id INTEGER NULL,
                shop_order_id INTEGER NULL,
                payment_method TEXT NOT NULL,
                payment_status TEXT NOT NULL,
                amount REAL DEFAULT 0,
                currency TEXT DEFAULT "VND",
                transaction_code TEXT NULL,
                provider_transaction_code TEXT NULL,
                provider_order_ref TEXT NULL,
                provider_response_code TEXT NULL,
                provider_message TEXT NULL,
                idempotency_key TEXT NULL,
                checkout_url TEXT NULL,
                request_payload TEXT NULL,
                callback_payload TEXT NULL,
                initiated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                completed_at TEXT NULL,
                failed_at TEXT NULL,
                refunded_at TEXT NULL,
                payment_date TEXT DEFAULT CURRENT_TIMESTAMP,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->db->exec('
            CREATE TABLE payment_methods (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                provider TEXT NOT NULL,
                channel_type TEXT NOT NULL,
                status TEXT NOT NULL,
                fee_rate_percent REAL DEFAULT 0,
                fixed_fee_amount REAL DEFAULT 0,
                settlement_cycle TEXT DEFAULT "instant",
                supports_refund INTEGER DEFAULT 0,
                supports_webhook INTEGER DEFAULT 0,
                supports_redirect INTEGER DEFAULT 0,
                display_order INTEGER DEFAULT 0,
                description TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    private function seedBaseData(): void
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        $this->db->exec("INSERT INTO users (id, name) VALUES (9, 'Integration User')");
        $this->db->exec("INSERT INTO movies (id, slug, title, poster_url, status) VALUES (1, 'integration-movie', 'Integration Movie', 'https://example.com/poster.jpg', 'now_showing')");
        $this->db->exec("INSERT INTO cinemas (id, name, city, status) VALUES (1, 'CinemaX', 'Ho Chi Minh City', 'active')");
        $this->db->exec("INSERT INTO rooms (id, cinema_id, room_name, room_type, screen_label, total_seats, status) VALUES (1, 1, 'Hall 1', 'standard', 'Screen 1', 4, 'active')");
        $this->db->exec("
            INSERT INTO seats (id, room_id, seat_row, seat_number, seat_type, status) VALUES
                (1, 1, 'A', 1, 'normal', 'available'),
                (2, 1, 'A', 2, 'vip', 'available'),
                (3, 1, 'A', 3, 'normal', 'available'),
                (4, 1, 'A', 4, 'normal', 'available')
        ");
        $this->db->exec("
            INSERT INTO showtimes (id, movie_id, room_id, show_date, start_time, end_time, price, status, presentation_type, language_version) VALUES
                (100, 1, 1, '{$tomorrow}', '18:00:00', '20:00:00', 80000, 'published', '2d', 'subtitled')
        ");
        $this->db->exec("
            INSERT INTO payment_methods (code, name, provider, channel_type, status, fee_rate_percent, fixed_fee_amount, settlement_cycle, supports_refund, supports_webhook, supports_redirect, display_order)
            VALUES ('vnpay', 'VNPay', 'vnpay', 'gateway', 'active', 2.1, 0, 'T+1', 1, 1, 1, 1)
        ");
    }

    private function seedHoldRows(string $sessionToken, array $seatIds): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO ticket_seat_holds (showtime_id, seat_id, user_id, session_token, hold_expires_at)
            VALUES (100, :seat_id, 9, :session_token, :hold_expires_at)
        ');

        foreach ($seatIds as $seatId) {
            $stmt->execute([
                'seat_id' => $seatId,
                'session_token' => $sessionToken,
                'hold_expires_at' => date('Y-m-d H:i:s', strtotime('+15 minutes')),
            ]);
        }
    }

    private function countRows(string $table): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }

    private function signPayload(array $payload): string
    {
        ksort($payload);

        return hash_hmac('sha512', http_build_query($payload), 'test-secret');
    }
}

class IntegrationPaymentLogger extends Logger
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
