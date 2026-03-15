<?php

namespace Tests\Integration;

use App\Core\Logger;
use App\Services\DemoDatasetMaintenanceService;
use PDO;
use PHPUnit\Framework\TestCase;

class DemoDatasetMaintenanceServiceIntegrationTest extends TestCase
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

    public function testCleanupLegacyCinemaFixturesArchivesKnownTestRecordsAndKeepsDemoData(): void
    {
        $service = new DemoDatasetMaintenanceService($this->db, new IntegrationDemoDatasetMaintenanceLogger());

        $summary = $service->cleanupLegacyCinemaFixtures();

        $this->assertSame([1, 2], $summary['legacy_cinema_ids']);
        $this->assertSame(2, $summary['archived_cinemas']);
        $this->assertSame(3, $summary['archived_rooms']);
        $this->assertSame(3, $summary['archived_seats']);
        $this->assertSame(2, $summary['archived_showtimes']);

        $legacyCinemaStatuses = $this->db->query("SELECT slug, status FROM cinemas WHERE slug IN ('123123', 'test') ORDER BY id ASC")->fetchAll();
        $demoCinemaStatus = (string) $this->db->query("SELECT status FROM cinemas WHERE slug = 'cinemax-landmark-81'")->fetchColumn();
        $legacyRoomTotals = array_map(static function (array $row): array {
            $row['total_seats'] = (int) $row['total_seats'];

            return $row;
        }, $this->db->query("SELECT room_name, status, total_seats FROM rooms WHERE cinema_id IN (1, 2) ORDER BY id ASC")->fetchAll() ?: []);
        $legacySeatStatuses = $this->db->query("SELECT status FROM seats WHERE room_id IN (1, 2, 3) ORDER BY id ASC")->fetchAll();
        $showtimeStatuses = array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];

            return $row;
        }, $this->db->query("SELECT id, status FROM showtimes ORDER BY id ASC")->fetchAll() ?: []);

        $this->assertSame([
            ['slug' => '123123', 'status' => 'archived'],
            ['slug' => 'test', 'status' => 'archived'],
        ], $legacyCinemaStatuses);
        $this->assertSame('active', $demoCinemaStatus);
        $this->assertSame([
            ['room_name' => '123', 'status' => 'archived', 'total_seats' => 0],
            ['room_name' => 'Room test suc ha noi', 'status' => 'archived', 'total_seats' => 0],
            ['room_name' => 'anime ha noi', 'status' => 'archived', 'total_seats' => 0],
        ], $legacyRoomTotals);
        $this->assertSame([
            ['status' => 'archived'],
            ['status' => 'archived'],
            ['status' => 'archived'],
        ], $legacySeatStatuses);
        $this->assertSame([
            ['id' => 1, 'status' => 'archived'],
            ['id' => 2, 'status' => 'archived'],
            ['id' => 3, 'status' => 'published'],
        ], $showtimeStatuses);
    }

    private function createSchema(): void
    {
        $this->db->exec('
            CREATE TABLE cinemas (
                id INTEGER PRIMARY KEY,
                slug TEXT NOT NULL,
                name TEXT NOT NULL,
                city TEXT,
                status TEXT NOT NULL
            )
        ');

        $this->db->exec('
            CREATE TABLE rooms (
                id INTEGER PRIMARY KEY,
                cinema_id INTEGER NOT NULL,
                room_name TEXT NOT NULL,
                total_seats INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL,
                FOREIGN KEY (cinema_id) REFERENCES cinemas(id)
            )
        ');

        $this->db->exec('
            CREATE TABLE seats (
                id INTEGER PRIMARY KEY,
                room_id INTEGER NOT NULL,
                status TEXT NOT NULL,
                FOREIGN KEY (room_id) REFERENCES rooms(id)
            )
        ');

        $this->db->exec('
            CREATE TABLE showtimes (
                id INTEGER PRIMARY KEY,
                room_id INTEGER NOT NULL,
                status TEXT NOT NULL,
                FOREIGN KEY (room_id) REFERENCES rooms(id)
            )
        ');
    }

    private function seedBaseData(): void
    {
        $this->db->exec("
            INSERT INTO cinemas (id, slug, name, city, status) VALUES
                (1, '123123', '123123', '12', 'active'),
                (2, 'test', 'test', 'test', 'active'),
                (3, 'cinemax-landmark-81', 'CinemaX Landmark 81', 'Ho Chi Minh City', 'active')
        ");

        $this->db->exec("
            INSERT INTO rooms (id, cinema_id, room_name, total_seats, status) VALUES
                (1, 1, '123', 0, 'active'),
                (2, 2, 'Room test suc ha noi', 0, 'active'),
                (3, 2, 'anime ha noi', 96, 'active'),
                (4, 3, 'Hall 1 - IMAX', 140, 'active')
        ");

        $this->db->exec("
            INSERT INTO seats (id, room_id, status) VALUES
                (1, 3, 'available'),
                (2, 3, 'maintenance'),
                (3, 3, 'disabled')
        ");

        $this->db->exec("
            INSERT INTO showtimes (id, room_id, status) VALUES
                (1, 1, 'draft'),
                (2, 3, 'published'),
                (3, 4, 'published')
        ");
    }
}

class IntegrationDemoDatasetMaintenanceLogger extends Logger
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
