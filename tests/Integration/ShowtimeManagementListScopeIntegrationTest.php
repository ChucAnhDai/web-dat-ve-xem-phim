<?php

namespace Tests\Integration;

use App\Core\Logger;
use App\Repositories\AdminShowtimeRepository;
use App\Repositories\MovieRepository;
use App\Repositories\RoomRepository;
use App\Services\ShowtimeManagementService;
use App\Validators\ShowtimeManagementValidator;
use PDO;
use PHPUnit\Framework\TestCase;

class ShowtimeManagementListScopeIntegrationTest extends TestCase
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

    public function testListShowtimesDefaultsToOperationalScope(): void
    {
        $service = $this->makeService();

        $result = $service->listShowtimes([
            'page' => 1,
            'per_page' => 20,
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertCount(1, $result['data']['items']);
        $this->assertSame('Morning Show', $result['data']['items'][0]['movie_title']);
        $this->assertSame('published', $result['data']['items'][0]['status']);
        $this->assertSame(1, $result['data']['summary']['total']);
    }

    public function testListShowtimesReturnsArchivedRecordsInsideArchiveScope(): void
    {
        $service = $this->makeService();

        $result = $service->listShowtimes([
            'scope' => 'archived',
            'page' => 1,
            'per_page' => 20,
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertCount(1, $result['data']['items']);
        $this->assertSame('Archive Show', $result['data']['items'][0]['movie_title']);
        $this->assertSame('archived', $result['data']['items'][0]['status']);
        $this->assertSame(1, $result['data']['summary']['total']);
    }

    private function makeService(): ShowtimeManagementService
    {
        return new ShowtimeManagementService(
            $this->db,
            new AdminShowtimeRepository($this->db),
            new MovieRepository($this->db),
            new RoomRepository($this->db),
            new ShowtimeManagementValidator(),
            new IntegrationShowtimeManagementListLogger()
        );
    }

    private function createSchema(): void
    {
        $this->db->exec('
            CREATE TABLE movies (
                id INTEGER PRIMARY KEY,
                primary_category_id INTEGER,
                slug TEXT NOT NULL UNIQUE,
                title TEXT NOT NULL,
                summary TEXT,
                duration_minutes INTEGER NOT NULL,
                release_date TEXT,
                poster_url TEXT,
                trailer_url TEXT,
                age_rating TEXT,
                language TEXT,
                director TEXT,
                writer TEXT,
                cast_text TEXT,
                studio TEXT,
                average_rating REAL DEFAULT 0,
                review_count INTEGER DEFAULT 0,
                status TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->db->exec('
            CREATE TABLE cinemas (
                id INTEGER PRIMARY KEY,
                slug TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                city TEXT NOT NULL,
                address TEXT NOT NULL,
                manager_name TEXT,
                support_phone TEXT,
                status TEXT NOT NULL,
                opening_time TEXT,
                closing_time TEXT,
                latitude REAL,
                longitude REAL,
                description TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->db->exec('
            CREATE TABLE rooms (
                id INTEGER PRIMARY KEY,
                cinema_id INTEGER NOT NULL,
                room_name TEXT NOT NULL,
                room_type TEXT NOT NULL,
                screen_label TEXT NOT NULL,
                projection_type TEXT NOT NULL,
                sound_profile TEXT NOT NULL,
                cleaning_buffer_minutes INTEGER NOT NULL DEFAULT 15,
                total_seats INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (cinema_id) REFERENCES cinemas(id)
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
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
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
                seat_id INTEGER
            )
        ');
    }

    private function seedBaseData(): void
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        $this->db->exec("
            INSERT INTO movies (
                id, slug, title, duration_minutes, poster_url, status, created_at, updated_at
            ) VALUES
                (1, 'morning-show', 'Morning Show', 120, 'https://example.com/a.jpg', 'now_showing', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (2, 'archive-show', 'Archive Show', 100, 'https://example.com/b.jpg', 'now_showing', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (3, 'parent-archived-show', 'Parent Archived Show', 95, 'https://example.com/c.jpg', 'archived', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $this->db->exec("
            INSERT INTO cinemas (
                id, slug, name, city, address, status, created_at, updated_at
            ) VALUES
                (1, 'cinema-x', 'CinemaX', 'Ho Chi Minh City', '1 Demo Street', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $this->db->exec("
            INSERT INTO rooms (
                id, cinema_id, room_name, room_type, screen_label, projection_type, sound_profile, cleaning_buffer_minutes, total_seats, status, created_at, updated_at
            ) VALUES
                (1, 1, 'Hall 1', 'imax', 'Screen A', 'laser', 'dolby_atmos', 15, 140, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $this->db->exec("
            INSERT INTO showtimes (
                id, movie_id, room_id, show_date, start_time, end_time, price, status, presentation_type, language_version, created_at, updated_at
            ) VALUES
                (1, 1, 1, '{$tomorrow}', '09:00:00', '11:15:00', 145000, 'published', 'imax', 'subtitled', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (2, 2, 1, '{$tomorrow}', '13:00:00', '15:00:00', 95000, 'archived', '2d', 'dubbed', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (3, 3, 1, '{$tomorrow}', '16:00:00', '17:35:00', 99000, 'published', '2d', 'original', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
    }
}

class IntegrationShowtimeManagementListLogger extends Logger
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
