<?php

namespace Tests\Unit;

use App\Core\Logger;
use App\Repositories\CinemaRepository;
use App\Repositories\RoomRepository;
use App\Repositories\SeatRepository;
use App\Services\CinemaManagementService;
use App\Validators\CinemaManagementValidator;
use PDO;
use PHPUnit\Framework\TestCase;

class CinemaManagementServiceTest extends TestCase
{
    public function testCreateCinemaReturnsConflictWhenSlugExists(): void
    {
        $cinemas = new UnitFakeCinemaManagementCinemaRepository();
        $cinemas->slugRow = ['id' => 8, 'slug' => 'demo-cinema'];

        $service = new CinemaManagementService(
            new PDO('sqlite::memory:'),
            $cinemas,
            new UnitFakeCinemaManagementRoomRepository(),
            new UnitFakeCinemaManagementSeatRepository(),
            new CinemaManagementValidator(),
            new UnitFakeCinemaManagementLogger()
        );

        $result = $service->createCinema([
            'name' => 'Demo Cinema',
            'city' => 'Ho Chi Minh City',
            'address' => '123 Demo Street',
            'status' => 'active',
        ], 10);

        $this->assertSame(409, $result['status']);
        $this->assertSame(['Cinema slug already exists.'], $result['errors']['slug']);
    }

    public function testReplaceRoomSeatsRecomputesRoomCapacity(): void
    {
        $rooms = new UnitFakeCinemaManagementRoomRepository();
        $rooms->roomById = [
            'id' => 12,
            'cinema_id' => 3,
            'cinema_name' => 'Downtown Cinema',
            'cinema_city' => 'Da Nang',
            'cinema_status' => 'active',
            'room_name' => 'Hall 2',
            'room_type' => 'standard_2d',
            'screen_label' => 'Screen 2',
            'projection_type' => 'digital_4k',
            'sound_profile' => 'dolby_7_1',
            'cleaning_buffer_minutes' => 15,
            'total_seats' => 0,
            'status' => 'active',
        ];
        $seats = new UnitFakeCinemaManagementSeatRepository();

        $service = new CinemaManagementService(
            new PDO('sqlite::memory:'),
            new UnitFakeCinemaManagementCinemaRepository(),
            $rooms,
            $seats,
            new CinemaManagementValidator(),
            new UnitFakeCinemaManagementLogger()
        );

        $result = $service->replaceRoomSeats(12, [
            'seats' => [
                ['seat_row' => 'B', 'seat_number' => 2, 'seat_type' => 'vip', 'status' => 'available'],
                ['seat_row' => 'A', 'seat_number' => 1, 'seat_type' => 'normal', 'status' => 'available'],
                ['seat_row' => 'A', 'seat_number' => 2, 'seat_type' => 'normal', 'status' => 'maintenance'],
            ],
        ], 5);

        $this->assertSame(200, $result['status']);
        $this->assertSame(3, $rooms->updatedTotalSeats);
        $this->assertCount(3, $seats->layout);
        $this->assertSame(3, $result['data']['room']['total_seats']);
        $this->assertSame(1, $result['data']['summary']['maintenance']);
    }

    public function testArchiveCinemaBlocksFuturePublishedShowtimes(): void
    {
        $cinemas = new UnitFakeCinemaManagementCinemaRepository();
        $cinemas->cinemaById = [
            'id' => 9,
            'slug' => 'future-cinema',
            'name' => 'Future Cinema',
            'city' => 'Ha Noi',
            'address' => '456 Demo Street',
            'status' => 'active',
        ];
        $cinemas->hasFuturePublishedShowtimesFlag = true;

        $service = new CinemaManagementService(
            new PDO('sqlite::memory:'),
            $cinemas,
            new UnitFakeCinemaManagementRoomRepository(),
            new UnitFakeCinemaManagementSeatRepository(),
            new CinemaManagementValidator(),
            new UnitFakeCinemaManagementLogger()
        );

        $result = $service->archiveCinema(9, 15);

        $this->assertSame(409, $result['status']);
        $this->assertSame(['Cannot archive cinema while published future showtimes exist.'], $result['errors']['cinema']);
    }
}

class UnitFakeCinemaManagementCinemaRepository extends CinemaRepository
{
    public ?array $slugRow = null;
    public ?array $cinemaById = null;
    public bool $hasActiveRoomsFlag = false;
    public bool $hasFuturePublishedShowtimesFlag = false;

    public function __construct()
    {
    }

    public function findBySlug(string $slug, ?int $excludeId = null): ?array
    {
        return $this->slugRow;
    }

    public function findById(int $id): ?array
    {
        return $this->cinemaById;
    }

    public function hasActiveRooms(int $cinemaId): bool
    {
        return $this->hasActiveRoomsFlag;
    }

    public function hasFuturePublishedShowtimes(int $cinemaId): bool
    {
        return $this->hasFuturePublishedShowtimesFlag;
    }

    public function listCities(array $filters = []): array
    {
        return [];
    }
}

class UnitFakeCinemaManagementRoomRepository extends RoomRepository
{
    public ?array $roomById = null;
    public bool $hasFuturePublishedShowtimesFlag = false;
    public bool $hasBookedTicketsFlag = false;
    public ?int $updatedTotalSeats = null;

    public function __construct()
    {
    }

    public function findById(int $id): ?array
    {
        return $this->roomById;
    }

    public function hasFuturePublishedShowtimes(int $roomId): bool
    {
        return $this->hasFuturePublishedShowtimesFlag;
    }

    public function hasBookedTickets(int $roomId): bool
    {
        return $this->hasBookedTicketsFlag;
    }

    public function updateTotalSeats(int $roomId, int $totalSeats): void
    {
        $this->updatedTotalSeats = $totalSeats;
        if ($this->roomById !== null) {
            $this->roomById['total_seats'] = $totalSeats;
        }
    }
}

class UnitFakeCinemaManagementSeatRepository extends SeatRepository
{
    public array $layout = [];
    public bool $hasBookedTicketsFlag = false;

    public function __construct()
    {
    }

    public function replaceRoomLayout(int $roomId, array $seats): void
    {
        $this->layout = array_map(static function (array $seat) use ($roomId): array {
            static $nextId = 1;

            return [
                'id' => $nextId++,
                'room_id' => $roomId,
                'seat_row' => $seat['seat_row'],
                'seat_number' => $seat['seat_number'],
                'seat_type' => $seat['seat_type'],
                'status' => $seat['status'],
            ];
        }, $seats);
    }

    public function listRoomLayout(int $roomId): array
    {
        return $this->layout;
    }

    public function summarizeRoomLayout(int $roomId): array
    {
        return [
            'total' => count($this->layout),
            'normal' => count(array_filter($this->layout, static fn (array $seat): bool => $seat['seat_type'] === 'normal')),
            'vip' => count(array_filter($this->layout, static fn (array $seat): bool => $seat['seat_type'] === 'vip')),
            'couple' => count(array_filter($this->layout, static fn (array $seat): bool => $seat['seat_type'] === 'couple')),
            'available' => count(array_filter($this->layout, static fn (array $seat): bool => $seat['status'] === 'available')),
            'maintenance' => count(array_filter($this->layout, static fn (array $seat): bool => $seat['status'] === 'maintenance')),
            'disabled' => count(array_filter($this->layout, static fn (array $seat): bool => $seat['status'] === 'disabled')),
        ];
    }

    public function hasBookedTicketsForRoom(int $roomId): bool
    {
        return $this->hasBookedTicketsFlag;
    }
}

class UnitFakeCinemaManagementLogger extends Logger
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
