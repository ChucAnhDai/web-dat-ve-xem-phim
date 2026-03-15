<?php

namespace Tests\Integration;

use App\Core\Logger;
use App\Repositories\SeatRepository;
use App\Repositories\ShowtimeRepository;
use App\Services\ShowtimeCatalogService;
use App\Validators\ShowtimeManagementValidator;
use PDO;
use PHPUnit\Framework\TestCase;

class ShowtimeCatalogServiceIntegrationTest extends TestCase
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

    public function testListShowtimesExcludesArchivedMoviesFromPublicCatalog(): void
    {
        $service = $this->makeService();

        $result = $service->listShowtimes([
            'page' => 1,
            'per_page' => 20,
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertCount(1, $result['data']['items']);
        $this->assertSame('Public Movie', $result['data']['items'][0]['movie_title']);
        $this->assertSame([['id' => 2, 'title' => 'Public Movie']], $result['data']['options']['movies']);
    }

    public function testGetSeatMapReturnsNotFoundForArchivedMovieShowtime(): void
    {
        $service = $this->makeService();

        $result = $service->getSeatMap(10);

        $this->assertSame(404, $result['status']);
        $this->assertSame(['Showtime not found.'], $result['errors']['showtime']);
    }

    public function testGetSeatMapMarksHeldSeatForCurrentSession(): void
    {
        $this->db->exec("
            INSERT INTO ticket_seat_holds (id, showtime_id, seat_id, session_token, hold_expires_at)
            VALUES (1, 11, 2, '" . str_repeat('f', 48) . "', datetime('now', '+10 minutes'))
        ");

        $service = $this->makeService();

        $result = $service->getSeatMapForSession(11, str_repeat('f', 48));

        $this->assertSame(200, $result['status']);
        $this->assertSame(1, $result['data']['summary']['held_seats']);
        $this->assertSame(1, $result['data']['summary']['held_by_current_session_seats']);
        $this->assertTrue($result['data']['seats'][1]['is_held']);
        $this->assertTrue($result['data']['seats'][1]['held_by_current_session']);
        $this->assertTrue($result['data']['seats'][1]['is_selectable']);
    }

    private function makeService(): ShowtimeCatalogService
    {
        return new ShowtimeCatalogService(
            new ShowtimeRepository($this->db),
            new SeatRepository($this->db),
            new ShowtimeManagementValidator(),
            new IntegrationShowtimeCatalogLogger()
        );
    }

    private function createSchema(): void
    {
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
                session_token TEXT NOT NULL,
                hold_expires_at TEXT NOT NULL,
                UNIQUE (showtime_id, seat_id),
                FOREIGN KEY (showtime_id) REFERENCES showtimes(id),
                FOREIGN KEY (seat_id) REFERENCES seats(id)
            )
        ');
    }

    private function seedBaseData(): void
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        $this->db->exec("
            INSERT INTO movies (id, slug, title, poster_url, status) VALUES
                (1, 'archived-movie', 'Archived Movie', 'https://example.com/a.jpg', 'archived'),
                (2, 'public-movie', 'Public Movie', 'https://example.com/b.jpg', 'now_showing')
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
                (3, 1, 'B', 1, 'normal', 'maintenance'),
                (4, 1, 'B', 2, 'normal', 'available')
        ");

        $this->db->exec("
            INSERT INTO showtimes (id, movie_id, room_id, show_date, start_time, end_time, price, status, presentation_type, language_version) VALUES
                (10, 1, 1, '{$tomorrow}', '10:00:00', '12:00:00', 145000, 'published', 'imax', 'subtitled'),
                (11, 2, 1, '{$tomorrow}', '14:00:00', '16:00:00', 155000, 'published', 'imax', 'original')
        ");
    }
}

class IntegrationShowtimeCatalogLogger extends Logger
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
