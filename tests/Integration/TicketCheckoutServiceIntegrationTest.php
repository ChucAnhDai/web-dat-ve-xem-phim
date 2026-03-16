<?php

namespace Tests\Integration;

use App\Core\Logger;
use App\Repositories\PaymentRepository;
use App\Repositories\SeatRepository;
use App\Repositories\ShowtimeRepository;
use App\Repositories\TicketOrderRepository;
use App\Repositories\TicketSeatHoldRepository;
use App\Services\TicketCheckoutService;
use App\Services\TicketLifecycleService;
use App\Support\VnpayGateway;
use App\Validators\TicketOrderValidator;
use PDO;
use PHPUnit\Framework\TestCase;

class TicketCheckoutServiceIntegrationTest extends TestCase
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
            'vnpay' => [
                'enabled' => true,
                'version' => '2.1.0',
                'command' => 'pay',
                'locale' => 'vn',
                'curr_code' => 'VND',
                'order_type' => 'other',
                'tmn_code' => 'TESTCODE',
                'hash_secret' => 'test-secret',
                'pay_url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
                'return_url' => 'http://localhost/web-dat-ve-xem-phim/api/payments/vnpay/return',
                'ipn_url' => 'http://localhost/web-dat-ve-xem-phim/api/payments/vnpay/ipn',
            ],
        ];

        $this->createSchema();
        $this->seedBaseData();
    }

    public function testPreviewOrderReturnsServerComputedPricing(): void
    {
        $sessionToken = str_repeat('a', 48);
        $this->seedHoldRows($sessionToken, [1, 2]);

        $result = $this->makeService()->previewOrder([
            'showtime_id' => 100,
            'seat_ids' => [1, 2],
            'payment_method' => 'momo',
            'fulfillment_method' => 'e_ticket',
        ], $sessionToken);

        $this->assertSame(200, $result['status']);
        $this->assertSame(2, $result['data']['order']['seat_count']);
        $this->assertSame(160000.0, $result['data']['order']['subtotal_price']);
        $this->assertSame(175000.0, $result['data']['order']['total_price']);
        $this->assertSame(['A1', 'A2'], array_column($result['data']['seats'], 'label'));
    }

    public function testCreateOrderPersistsOrderTicketsPaymentAndClearsHolds(): void
    {
        $sessionToken = str_repeat('b', 48);
        $this->seedHoldRows($sessionToken, [1, 2]);

        $result = $this->makeService()->createOrder([
            'showtime_id' => 100,
            'seat_ids' => [1, 2],
            'contact_name' => 'Integration Guest',
            'contact_email' => 'integration@example.com',
            'contact_phone' => '0901234567',
            'payment_method' => 'momo',
            'fulfillment_method' => 'e_ticket',
        ], $sessionToken, 9);

        $this->assertSame(201, $result['status']);
        $this->assertSame('paid', $result['data']['order']['status']);
        $this->assertSame(9, $result['data']['order']['user_id']);
        $this->assertSame(1, $this->countRows('ticket_orders'));
        $this->assertSame(2, $this->countRows('ticket_details'));
        $this->assertSame(1, $this->countRows('payments'));
        $this->assertSame(0, $this->countRows('ticket_seat_holds'));

        $orderRow = $this->db->query('SELECT status, user_id, total_price FROM ticket_orders LIMIT 1')->fetch();
        $paymentRow = $this->db->query('SELECT payment_method, payment_status, amount, currency, provider_order_ref, completed_at FROM payments LIMIT 1')->fetch();

        $this->assertSame('paid', $orderRow['status']);
        $this->assertSame(9, (int) $orderRow['user_id']);
        $this->assertSame(175000.0, (float) $orderRow['total_price']);
        $this->assertSame('momo', $paymentRow['payment_method']);
        $this->assertSame('success', $paymentRow['payment_status']);
        $this->assertSame(175000.0, (float) $paymentRow['amount']);
        $this->assertSame('VND', $paymentRow['currency']);
        $this->assertStringStartsWith('TKT-', (string) $paymentRow['provider_order_ref']);
        $this->assertNotEmpty($paymentRow['completed_at']);
    }

    public function testActiveCheckoutReturnsResumePayloadForPendingVnpayOrder(): void
    {
        $sessionToken = str_repeat('c', 48);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        $this->db->exec("
            INSERT INTO ticket_orders (
                id, order_code, user_id, session_token, contact_name, contact_email, contact_phone,
                fulfillment_method, seat_count, subtotal_price, discount_amount, fee_amount,
                total_price, currency, status, hold_expires_at
            ) VALUES (
                5, 'TKT-RESUME', 9, '{$sessionToken}', 'Resume Guest', 'resume@example.com', '0901234567',
                'e_ticket', 2, 160000, 0, 0, 175000, 'VND', 'pending', '{$expiresAt}'
            )
        ");
        $this->db->exec("
            INSERT INTO ticket_details (
                order_id, showtime_id, seat_id, ticket_code, status, base_price, surcharge_amount, discount_amount, price, qr_payload
            ) VALUES
                (5, 100, 1, 'TIC-RESUME-1', 'pending', 80000, 0, 0, 80000, 'ticket:TIC-RESUME-1'),
                (5, 100, 2, 'TIC-RESUME-2', 'pending', 80000, 15000, 0, 95000, 'ticket:TIC-RESUME-2')
        ");
        $this->db->exec("
            INSERT INTO payments (
                ticket_order_id, payment_method, payment_status, transaction_code, amount, currency,
                provider_order_ref, checkout_url, initiated_at, payment_date
            ) VALUES (
                5, 'vnpay', 'pending', 'PAY-RESUME', 175000, 'VND',
                'TKT-RESUME', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?vnp_TxnRef=TKT-RESUME', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");

        $result = $this->makeService()->activeCheckout($sessionToken, 9);

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['data']['resume_available']);
        $this->assertSame('payment', $result['data']['resume_target']);
        $this->assertSame('TKT-RESUME', $result['data']['order']['order_code']);
        $this->assertSame('integration-movie', $result['data']['order']['movie_slug']);
        $this->assertSame(['A1', 'A2'], array_column($result['data']['seats'], 'label'));
        $this->assertSame('https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?vnp_TxnRef=TKT-RESUME', $result['data']['redirect_url']);
    }

    public function testActiveCheckoutRebuildsFullVnpayRedirectWhenStoredUrlIsTruncated(): void
    {
        $sessionToken = str_repeat('d', 48);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        $gateway = new VnpayGateway($this->paymentConfig);
        $payload = [
            'vnp_Version' => '2.1.0',
            'vnp_TmnCode' => 'TESTCODE',
            'vnp_Amount' => '17500000',
            'vnp_Command' => 'pay',
            'vnp_CreateDate' => '20260316170000',
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => '127.0.0.1',
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => 'Thanh toan ve xem phim TKT-REBUILD',
            'vnp_OrderType' => 'other',
            'vnp_ReturnUrl' => 'http://localhost/web-dat-ve-xem-phim/api/payments/vnpay/return',
            'vnp_TxnRef' => 'TKT-REBUILD',
            'vnp_ExpireDate' => '20260316170500',
        ];
        $fullCheckout = $gateway->buildCheckoutUrl($payload);
        $truncatedCheckoutUrl = substr((string) $fullCheckout['checkout_url'], 0, 500);

        $this->db->exec("
            INSERT INTO ticket_orders (
                id, order_code, user_id, session_token, contact_name, contact_email, contact_phone,
                fulfillment_method, seat_count, subtotal_price, discount_amount, fee_amount,
                total_price, currency, status, hold_expires_at
            ) VALUES (
                6, 'TKT-REBUILD', 9, '{$sessionToken}', 'Resume Guest', 'resume@example.com', '0901234567',
                'e_ticket', 2, 160000, 0, 0, 175000, 'VND', 'pending', '{$expiresAt}'
            )
        ");
        $this->db->exec("
            INSERT INTO ticket_details (
                order_id, showtime_id, seat_id, ticket_code, status, base_price, surcharge_amount, discount_amount, price, qr_payload
            ) VALUES
                (6, 100, 1, 'TIC-REBUILD-1', 'pending', 80000, 0, 0, 80000, 'ticket:TIC-REBUILD-1'),
                (6, 100, 2, 'TIC-REBUILD-2', 'pending', 80000, 15000, 0, 95000, 'ticket:TIC-REBUILD-2')
        ");
        $stmt = $this->db->prepare('
            INSERT INTO payments (
                ticket_order_id, payment_method, payment_status, transaction_code, amount, currency,
                provider_order_ref, checkout_url, request_payload, initiated_at, payment_date
            ) VALUES (
                6, :payment_method, :payment_status, :transaction_code, :amount, :currency,
                :provider_order_ref, :checkout_url, :request_payload, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ');
        $stmt->execute([
            'payment_method' => 'vnpay',
            'payment_status' => 'pending',
            'transaction_code' => 'PAY-REBUILD',
            'amount' => 175000,
            'currency' => 'VND',
            'provider_order_ref' => 'TKT-REBUILD',
            'checkout_url' => $truncatedCheckoutUrl,
            'request_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        $result = $this->makeService()->activeCheckout($sessionToken, 9);

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['data']['resume_available']);
        $this->assertSame('payment', $result['data']['resume_target']);
        $this->assertNotSame($truncatedCheckoutUrl, $result['data']['redirect_url']);
        $this->assertStringContainsString('vnp_SecureHash=', $result['data']['redirect_url']);

        parse_str((string) parse_url($result['data']['redirect_url'], PHP_URL_QUERY), $query);
        $this->assertTrue($gateway->validateSignature($query));

        $storedCheckoutUrl = $this->db->query('SELECT checkout_url FROM payments WHERE provider_order_ref = "TKT-REBUILD" LIMIT 1')->fetchColumn();
        $this->assertSame($result['data']['redirect_url'], $storedCheckoutUrl);
    }

    private function makeService(): TicketCheckoutService
    {
        $orders = new TicketOrderRepository($this->db);
        $payments = new PaymentRepository($this->db);
        $holds = new TicketSeatHoldRepository($this->db);

        return new TicketCheckoutService(
            $this->db,
            new ShowtimeRepository($this->db),
            new SeatRepository($this->db),
            $holds,
            $orders,
            $payments,
            new TicketOrderValidator(),
            new TicketLifecycleService($this->db, $holds, $orders, $payments, new IntegrationTicketCheckoutLogger()),
            null,
            new IntegrationTicketCheckoutLogger(),
            new VnpayGateway($this->paymentConfig)
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
                payment_method TEXT,
                payment_status TEXT,
                transaction_code TEXT,
                amount REAL DEFAULT 0,
                currency TEXT DEFAULT "VND",
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
    }

    private function seedHoldRows(string $sessionToken, array $seatIds): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO ticket_seat_holds (showtime_id, seat_id, user_id, session_token, hold_expires_at)
            VALUES (100, :seat_id, NULL, :session_token, :hold_expires_at)
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
}

class IntegrationTicketCheckoutLogger extends Logger
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
