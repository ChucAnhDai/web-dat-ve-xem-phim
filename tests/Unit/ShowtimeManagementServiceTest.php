<?php

namespace Tests\Unit;

use App\Core\Logger;
use App\Repositories\AdminShowtimeRepository;
use App\Repositories\MovieRepository;
use App\Repositories\RoomRepository;
use App\Services\ShowtimeManagementService;
use App\Validators\ShowtimeManagementValidator;
use PDO;
use PHPUnit\Framework\TestCase;

class ShowtimeManagementServiceTest extends TestCase
{
    public function testCreateShowtimeRejectsOverlapForSameRoom(): void
    {
        $showtimes = new UnitFakeAdminShowtimeRepository();
        $showtimes->hasOverlapFlag = true;

        $service = $this->makeService($showtimes, [
            'id' => 4,
            'status' => 'now_showing',
            'duration_minutes' => 120,
        ], [
            'id' => 8,
            'cinema_id' => 3,
            'status' => 'active',
            'cinema_status' => 'active',
            'cleaning_buffer_minutes' => 15,
        ]);

        $result = $service->createShowtime([
            'movie_id' => 4,
            'room_id' => 8,
            'show_date' => '2026-03-20',
            'start_time' => '10:30',
            'price' => 95000,
            'status' => 'published',
        ], 7);

        $this->assertSame(409, $result['status']);
        $this->assertSame(['Room already has an overlapping showtime.'], $result['errors']['showtime']);
    }

    public function testCreateShowtimeRejectsPublishedWhenRoomIsInactive(): void
    {
        $service = $this->makeService(new UnitFakeAdminShowtimeRepository(), [
            'id' => 4,
            'status' => 'now_showing',
            'duration_minutes' => 120,
        ], [
            'id' => 8,
            'cinema_id' => 3,
            'status' => 'maintenance',
            'cinema_status' => 'active',
            'cleaning_buffer_minutes' => 15,
        ]);

        $result = $service->createShowtime([
            'movie_id' => 4,
            'room_id' => 8,
            'show_date' => '2026-03-20',
            'start_time' => '10:30',
            'price' => 95000,
            'status' => 'published',
        ], 7);

        $this->assertSame(409, $result['status']);
        $this->assertSame(['Published showtimes require an active room.'], $result['errors']['status']);
    }

    public function testCreateShowtimeCalculatesEndTimeOnServerBeforePersisting(): void
    {
        $showtimes = new UnitFakeAdminShowtimeRepository();

        $service = $this->makeService($showtimes, [
            'id' => 4,
            'status' => 'now_showing',
            'duration_minutes' => 120,
        ], [
            'id' => 8,
            'cinema_id' => 3,
            'status' => 'active',
            'cinema_status' => 'active',
            'cleaning_buffer_minutes' => 15,
        ]);

        $result = $service->createShowtime([
            'movie_id' => 4,
            'room_id' => 8,
            'show_date' => '2026-03-20',
            'start_time' => '10:30',
            'price' => 95000,
            'status' => 'draft',
            'presentation_type' => 'imax',
            'language_version' => 'dubbed',
        ], 7);

        $this->assertSame(201, $result['status']);
        $this->assertSame('10:30:00', $showtimes->createdData['start_time']);
        $this->assertSame('12:45:00', $showtimes->createdData['end_time']);
        $this->assertSame('imax', $showtimes->createdData['presentation_type']);
        $this->assertSame('dubbed', $showtimes->createdData['language_version']);
    }

    private function makeService(
        UnitFakeAdminShowtimeRepository $showtimes,
        array $movie,
        array $room
    ): ShowtimeManagementService {
        $movies = new UnitFakeShowtimeMovieRepository();
        $movies->movieById = $movie;

        $rooms = new UnitFakeShowtimeRoomRepository();
        $rooms->roomById = $room;

        return new ShowtimeManagementService(
            new PDO('sqlite::memory:'),
            $showtimes,
            $movies,
            $rooms,
            new ShowtimeManagementValidator(),
            new UnitFakeShowtimeManagementLogger()
        );
    }
}

class UnitFakeAdminShowtimeRepository extends AdminShowtimeRepository
{
    public bool $hasOverlapFlag = false;
    public array $createdData = [];
    public int $createdId = 55;

    public function __construct()
    {
    }

    public function hasOverlap(int $roomId, string $showDate, string $startTime, string $endTime, ?int $excludeId = null): bool
    {
        return $this->hasOverlapFlag;
    }

    public function create(array $data): int
    {
        $this->createdData = $data;

        return $this->createdId;
    }

    public function findById(int $id): ?array
    {
        if ($id !== $this->createdId || $this->createdData === []) {
            return null;
        }

        return array_merge([
            'id' => $id,
            'movie_slug' => 'demo-movie',
            'movie_title' => 'Demo Movie',
            'duration_minutes' => 120,
            'poster_url' => null,
            'cinema_id' => 3,
            'cinema_slug' => 'demo-cinema',
            'cinema_name' => 'Demo Cinema',
            'cinema_city' => 'Ho Chi Minh City',
            'cinema_status' => 'active',
            'room_name' => 'Hall 8',
            'room_type' => 'imax',
            'screen_label' => 'Screen 8',
            'total_seats' => 120,
            'room_status' => 'active',
            'booked_seats' => 0,
            'created_at' => null,
            'updated_at' => null,
        ], $this->createdData);
    }
}

class UnitFakeShowtimeMovieRepository extends MovieRepository
{
    public ?array $movieById = null;

    public function __construct()
    {
    }

    public function findById(int $id): ?array
    {
        return $this->movieById;
    }
}

class UnitFakeShowtimeRoomRepository extends RoomRepository
{
    public ?array $roomById = null;

    public function __construct()
    {
    }

    public function findById(int $id): ?array
    {
        return $this->roomById;
    }
}

class UnitFakeShowtimeManagementLogger extends Logger
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
