<?php

namespace Tests\Integration;

use App\Core\Logger;
use App\Repositories\SeatRepository;
use App\Repositories\ShowtimeRepository;
use App\Repositories\TicketSeatHoldRepository;
use App\Services\TicketHoldService;
use App\Validators\TicketHoldValidator;
use PDO;
use PHPUnit\Framework\TestCase;

class TicketHoldServiceIntegrationTest extends TestCase
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

    public function testCreateHoldPersistsAndRefreshesCurrentSessionSeats(): void
    {
        $service = $this->makeService();

        $firstResult = $service->createHold([
            'showtime_id' => 100,
            'seat_ids' => [1, 2],
        ], str_repeat('a', 48));

        $this->assertSame(200, $firstResult['status']);
        $this->assertSame(2, $this->countRows('ticket_seat_holds'));

        $secondResult = $service->createHold([
            'showtime_id' => 100,
            'seat_ids' => [2, 4],
        ], str_repeat('a', 48));

        $this->assertSame(200, $secondResult['status']);
        $this->assertSame(2, $this->countRows('ticket_seat_holds'));
        $this->assertSame([2, 4], $this->activeSeatIdsForSession(str_repeat('a', 48)));
    }

    public function testCreateHoldRejectsSeatHeldByAnotherSession(): void
    {
        $this->db->exec("
            INSERT INTO ticket_seat_holds (showtime_id, seat_id, user_id, session_token, hold_expires_at)
            VALUES (100, 1, NULL, '" . str_repeat('b', 48) . "', datetime('now', '+15 minutes'))
        ");

        $result = $this->makeService()->createHold([
            'showtime_id' => 100,
            'seat_ids' => [1],
        ], str_repeat('c', 48));

        $this->assertSame(409, $result['status']);
        $this->assertSame(['Seat A1 is temporarily held by another customer.'], $result['errors']['seat_ids']);
    }

    public function testReleaseHoldDeletesOnlyCurrentSessionRows(): void
    {
        $this->db->exec("
            INSERT INTO ticket_seat_holds (showtime_id, seat_id, user_id, session_token, hold_expires_at)
            VALUES
                (100, 1, NULL, '" . str_repeat('d', 48) . "', datetime('now', '+15 minutes')),
                (100, 2, NULL, '" . str_repeat('d', 48) . "', datetime('now', '+15 minutes')),
                (100, 4, NULL, '" . str_repeat('e', 48) . "', datetime('now', '+15 minutes'))
        ");

        $result = $this->makeService()->releaseHold(100, str_repeat('d', 48));

        $this->assertSame(200, $result['status']);
        $this->assertSame(2, $result['data']['released_count']);
        $this->assertSame([4], $this->activeSeatIdsForSession(str_repeat('e', 48)));
    }

    private function makeService(): TicketHoldService
    {
        return new TicketHoldService(
            $this->db,
            new ShowtimeRepository($this->db),
            new SeatRepository($this->db),
            new TicketSeatHoldRepository($this->db),
            new TicketHoldValidator(),
            new IntegrationTicketHoldLogger()
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
                id INTEGER PRIMARY KEY,
                status TEXT NOT NULL
            )
        ');

        $this->db->exec('
            CREATE TABLE ticket_details (
                id INTEGER PRIMARY KEY,
                order_id INTEGER,
                showtime_id INTEGER,
                seat_id INTEGER,
                FOREIGN KEY (order_id) REFERENCES ticket_orders(id),
                FOREIGN KEY (showtime_id) REFERENCES showtimes(id),
                FOREIGN KEY (seat_id) REFERENCES seats(id)
            )
        ');

        $this->db->exec('
            CREATE TABLE ticket_seat_holds (
                id INTEGER PRIMARY KEY,
                showtime_id INTEGER NOT NULL,
                seat_id INTEGER NOT NULL,
                user_id INTEGER NULL,
                session_token TEXT NOT NULL,
                hold_expires_at TEXT NOT NULL,
                UNIQUE (showtime_id, seat_id),
                FOREIGN KEY (showtime_id) REFERENCES showtimes(id),
                FOREIGN KEY (seat_id) REFERENCES seats(id),
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ');
    }

    private function seedBaseData(): void
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        $this->db->exec("
            INSERT INTO movies (id, slug, title, poster_url, status) VALUES
                (1, 'public-movie', 'Public Movie', 'https://example.com/poster.jpg', 'now_showing')
        ");

        $this->db->exec("
            INSERT INTO cinemas (id, name, city, status) VALUES
                (1, 'CinemaX', 'Ho Chi Minh City', 'active')
        ");

        $this->db->exec("
            INSERT INTO rooms (id, cinema_id, room_name, room_type, screen_label, total_seats, status) VALUES
                (1, 1, 'Hall 1', 'imax', 'Screen 1', 4, 'active')
        ");

        $this->db->exec("
            INSERT INTO seats (id, room_id, seat_row, seat_number, seat_type, status) VALUES
                (1, 1, 'A', 1, 'normal', 'available'),
                (2, 1, 'A', 2, 'vip', 'available'),
                (3, 1, 'B', 1, 'normal', 'available'),
                (4, 1, 'B', 2, 'normal', 'available')
        ");

        $this->db->exec("
            INSERT INTO showtimes (id, movie_id, room_id, show_date, start_time, end_time, price, status, presentation_type, language_version) VALUES
                (100, 1, 1, '{$tomorrow}', '18:00:00', '20:00:00', 145000, 'published', 'imax', 'subtitled')
        ");

        $this->db->exec("
            INSERT INTO ticket_orders (id, status) VALUES (1, 'paid')
        ");

        $this->db->exec("
            INSERT INTO ticket_details (id, order_id, showtime_id, seat_id) VALUES
                (1, 1, 100, 3)
        ");
    }

    private function countRows(string $table): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }

    private function activeSeatIdsForSession(string $sessionToken): array
    {
        $stmt = $this->db->prepare('
            SELECT seat_id
            FROM ticket_seat_holds
            WHERE session_token = :session_token
              AND hold_expires_at > CURRENT_TIMESTAMP
            ORDER BY seat_id ASC
        ');
        $stmt->execute(['session_token' => $sessionToken]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
}

class IntegrationTicketHoldLogger extends Logger
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
