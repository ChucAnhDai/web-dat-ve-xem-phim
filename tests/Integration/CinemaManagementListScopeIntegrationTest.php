<?php

namespace Tests\Integration;

use App\Core\Logger;
use App\Repositories\CinemaRepository;
use App\Repositories\RoomRepository;
use App\Repositories\SeatRepository;
use App\Services\CinemaManagementService;
use App\Validators\CinemaManagementValidator;
use PDO;
use PHPUnit\Framework\TestCase;

class CinemaManagementListScopeIntegrationTest extends TestCase
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

    public function testListCinemasDefaultsToOperationalScope(): void
    {
        $service = $this->makeService();

        $result = $service->listCinemas([
            'page' => 1,
            'per_page' => 20,
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertCount(1, $result['data']['items']);
        $this->assertSame('Live Cinema', $result['data']['items'][0]['name']);
        $this->assertSame(1, $result['data']['summary']['total']);
        $this->assertSame(['Ho Chi Minh City'], $result['data']['options']['cities']);
    }

    public function testListCinemasReturnsArchivedRecordsInsideArchiveScope(): void
    {
        $service = $this->makeService();

        $result = $service->listCinemas([
            'scope' => 'archived',
            'page' => 1,
            'per_page' => 20,
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertCount(1, $result['data']['items']);
        $this->assertSame('Archived Cinema', $result['data']['items'][0]['name']);
        $this->assertSame('archived', $result['data']['items'][0]['status']);
        $this->assertSame(1, $result['data']['summary']['total']);
    }

    public function testListRoomsDefaultsToOperationalScopeAndArchivedScopeCanBeViewedSeparately(): void
    {
        $service = $this->makeService();

        $defaultResult = $service->listRooms([
            'page' => 1,
            'per_page' => 20,
        ]);
        $archivedResult = $service->listRooms([
            'scope' => 'archived',
            'page' => 1,
            'per_page' => 20,
        ]);

        $this->assertSame(200, $defaultResult['status']);
        $this->assertCount(1, $defaultResult['data']['items']);
        $this->assertSame('Hall 1', $defaultResult['data']['items'][0]['room_name']);
        $this->assertSame('Live Cinema', $defaultResult['data']['options']['cinemas'][0]['name']);

        $this->assertSame(200, $archivedResult['status']);
        $this->assertCount(1, $archivedResult['data']['items']);
        $this->assertSame('Archive Hall', $archivedResult['data']['items'][0]['room_name']);
        $this->assertSame('archived', $archivedResult['data']['items'][0]['status']);
    }

    private function makeService(): CinemaManagementService
    {
        return new CinemaManagementService(
            $this->db,
            new CinemaRepository($this->db),
            new RoomRepository($this->db),
            new SeatRepository($this->db),
            new CinemaManagementValidator(),
            new IntegrationCinemaManagementListLogger()
        );
    }

    private function createSchema(): void
    {
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
            CREATE TABLE seats (
                id INTEGER PRIMARY KEY,
                room_id INTEGER NOT NULL,
                seat_row TEXT NOT NULL,
                seat_number INTEGER NOT NULL,
                seat_type TEXT NOT NULL,
                status TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
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
                status TEXT NOT NULL
            )
        ');

        $this->db->exec('
            CREATE TABLE ticket_orders (
                id INTEGER PRIMARY KEY,
                session_token TEXT NULL,
                status TEXT NOT NULL
            )
        ');

        $this->db->exec('
            CREATE TABLE ticket_details (
                id INTEGER PRIMARY KEY,
                order_id INTEGER NOT NULL,
                showtime_id INTEGER NOT NULL,
                seat_id INTEGER NOT NULL
            )
        ');
    }

    private function seedBaseData(): void
    {
        $this->db->exec("
            INSERT INTO cinemas (
                id, slug, name, city, address, manager_name, support_phone, status, description, created_at, updated_at
            ) VALUES
                (1, 'live-cinema', 'Live Cinema', 'Ho Chi Minh City', '1 Demo Street', 'Ops Lead', '0900000001', 'active', 'Operational cinema', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (2, 'archived-cinema', 'Archived Cinema', 'Da Nang', '2 Archive Street', 'Archive Lead', '0900000002', 'archived', 'Archived cinema', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $this->db->exec("
            INSERT INTO rooms (
                id, cinema_id, room_name, room_type, screen_label, projection_type, sound_profile, cleaning_buffer_minutes, total_seats, status, created_at, updated_at
            ) VALUES
                (1, 1, 'Hall 1', 'imax', 'Screen A', 'laser', 'dolby_atmos', 15, 140, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (2, 1, 'Archive Hall', 'standard_2d', 'Screen B', 'digital_4k', 'stereo', 10, 96, 'archived', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (3, 2, 'Legacy Hall', 'standard_2d', 'Screen C', 'digital_4k', 'stereo', 10, 96, 'closed', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
    }
}

class IntegrationCinemaManagementListLogger extends Logger
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
