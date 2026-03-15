<?php

namespace Tests\Unit;

use App\Core\Logger;
use App\Repositories\SeatRepository;
use App\Repositories\ShowtimeRepository;
use App\Repositories\TicketSeatHoldRepository;
use App\Services\TicketHoldService;
use App\Validators\TicketHoldValidator;
use PDO;
use PHPUnit\Framework\TestCase;

class TicketHoldServiceTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function testCreateHoldReturnsSuccessWhenSeatsAreAvailable(): void
    {
        $showtimes = new UnitFakeTicketHoldShowtimeRepository();
        $showtimes->detail = [
            'id' => 501,
            'room_id' => 9,
        ];

        $seats = new UnitFakeTicketHoldSeatRepository();
        $seats->seatRows = [
            ['id' => 11, 'seat_row' => 'A', 'seat_number' => 1, 'status' => 'available'],
            ['id' => 12, 'seat_row' => 'A', 'seat_number' => 2, 'status' => 'available'],
        ];

        $holds = new UnitFakeTicketSeatHoldRepository();

        $service = new TicketHoldService(
            $this->db,
            $showtimes,
            $seats,
            $holds,
            new TicketHoldValidator(),
            new UnitFakeTicketHoldLogger()
        );

        $result = $service->createHold([
            'showtime_id' => 501,
            'seat_ids' => [11, 12],
        ], str_repeat('a', 48));

        $this->assertSame(200, $result['status']);
        $this->assertSame(['A1', 'A2'], $result['data']['seat_labels']);
        $this->assertCount(2, $holds->createdRows);
        $this->assertSame([501, str_repeat('a', 48)], $holds->releasedArgs);
    }

    public function testCreateHoldRejectsBookedSeats(): void
    {
        $showtimes = new UnitFakeTicketHoldShowtimeRepository();
        $showtimes->detail = [
            'id' => 501,
            'room_id' => 9,
        ];

        $seats = new UnitFakeTicketHoldSeatRepository();
        $seats->seatRows = [
            ['id' => 11, 'seat_row' => 'A', 'seat_number' => 1, 'status' => 'available'],
        ];
        $seats->bookedSeatIds = [11];

        $service = new TicketHoldService(
            $this->db,
            $showtimes,
            $seats,
            new UnitFakeTicketSeatHoldRepository(),
            new TicketHoldValidator(),
            new UnitFakeTicketHoldLogger()
        );

        $result = $service->createHold([
            'showtime_id' => 501,
            'seat_ids' => [11],
        ], str_repeat('b', 48));

        $this->assertSame(409, $result['status']);
        $this->assertSame(['Seat A1 has already been booked.'], $result['errors']['seat_ids']);
    }

    public function testCreateHoldRejectsConflictsFromAnotherSession(): void
    {
        $showtimes = new UnitFakeTicketHoldShowtimeRepository();
        $showtimes->detail = [
            'id' => 501,
            'room_id' => 9,
        ];

        $seats = new UnitFakeTicketHoldSeatRepository();
        $seats->seatRows = [
            ['id' => 11, 'seat_row' => 'A', 'seat_number' => 1, 'status' => 'available'],
        ];

        $holds = new UnitFakeTicketSeatHoldRepository();
        $holds->conflicts = [
            ['seat_id' => 11, 'session_token' => str_repeat('c', 48)],
        ];

        $service = new TicketHoldService(
            $this->db,
            $showtimes,
            $seats,
            $holds,
            new TicketHoldValidator(),
            new UnitFakeTicketHoldLogger()
        );

        $result = $service->createHold([
            'showtime_id' => 501,
            'seat_ids' => [11],
        ], str_repeat('d', 48));

        $this->assertSame(409, $result['status']);
        $this->assertSame(['Seat A1 is temporarily held by another customer.'], $result['errors']['seat_ids']);
    }
}

class UnitFakeTicketHoldShowtimeRepository extends ShowtimeRepository
{
    public ?array $detail = null;

    public function __construct()
    {
    }

    public function findPublicDetail(int $showtimeId): ?array
    {
        return $this->detail;
    }
}

class UnitFakeTicketHoldSeatRepository extends SeatRepository
{
    public array $seatRows = [];
    public array $bookedSeatIds = [];

    public function __construct()
    {
    }

    public function findRoomSeatsByIds(int $roomId, array $seatIds): array
    {
        return $this->seatRows;
    }

    public function findBookedSeatIdsForShowtime(int $showtimeId, array $seatIds): array
    {
        return $this->bookedSeatIds;
    }
}

class UnitFakeTicketSeatHoldRepository extends TicketSeatHoldRepository
{
    public array $conflicts = [];
    public array $createdRows = [];
    public array $releasedArgs = [];

    public function __construct()
    {
    }

    public function purgeExpired(): int
    {
        return 0;
    }

    public function findActiveConflicts(int $showtimeId, array $seatIds, string $sessionToken): array
    {
        return $this->conflicts;
    }

    public function releaseForSessionAndShowtime(int $showtimeId, string $sessionToken): int
    {
        $this->releasedArgs = [$showtimeId, $sessionToken];

        return 0;
    }

    public function createHold(int $showtimeId, int $seatId, ?int $userId, string $sessionToken, string $holdExpiresAt): void
    {
        $this->createdRows[] = [
            'showtime_id' => $showtimeId,
            'seat_id' => $seatId,
            'session_token' => $sessionToken,
            'hold_expires_at' => $holdExpiresAt,
        ];
    }
}

class UnitFakeTicketHoldLogger extends Logger
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
