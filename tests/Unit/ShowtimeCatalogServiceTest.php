<?php

namespace Tests\Unit;

use App\Core\Logger;
use App\Repositories\SeatRepository;
use App\Repositories\ShowtimeRepository;
use App\Services\ShowtimeCatalogService;
use PHPUnit\Framework\TestCase;

class ShowtimeCatalogServiceTest extends TestCase
{
    public function testGetSeatMapReturnsMappedShowtimeAndSeats(): void
    {
        $showtimes = new UnitFakeSeatShowtimeRepository();
        $showtimes->detail = [
            'id' => 501,
            'movie_id' => 2,
            'room_id' => 3,
            'movie_slug' => 'toi-pham-101',
            'movie_title' => 'Toi Pham 101',
            'poster_url' => 'https://local.example.com/poster.jpg',
            'show_date' => '2026-03-14',
            'start_time' => '16:45:00',
            'price' => '18.00',
            'cinema_name' => 'CinemaX Landmark',
            'room_name' => 'Hall 1 - IMAX',
            'total_seats' => 24,
        ];

        $seats = new UnitFakeSeatRepository();
        $seats->items = [
            ['id' => 1, 'seat_row' => 'A', 'seat_number' => 1, 'seat_type' => 'normal', 'is_booked' => 0],
            ['id' => 2, 'seat_row' => 'A', 'seat_number' => 2, 'seat_type' => 'vip', 'is_booked' => 1],
        ];

        $service = new ShowtimeCatalogService($showtimes, $seats, new UnitFakeSeatLogger());
        $result = $service->getSeatMap(501);

        $this->assertSame(200, $result['status']);
        $this->assertSame('Toi Pham 101', $result['data']['showtime']['movie_title']);
        $this->assertSame('A1', $result['data']['seats'][0]['label']);
        $this->assertTrue($result['data']['seats'][1]['is_booked']);
        $this->assertSame(1, $result['data']['summary']['available_seats']);
    }

    public function testGetSeatMapReturnsNotFoundWhenShowtimeMissing(): void
    {
        $service = new ShowtimeCatalogService(
            new UnitFakeSeatShowtimeRepository(),
            new UnitFakeSeatRepository(),
            new UnitFakeSeatLogger()
        );

        $result = $service->getSeatMap(999);

        $this->assertSame(404, $result['status']);
        $this->assertSame(['Showtime not found.'], $result['errors']['showtime']);
    }
}

class UnitFakeSeatShowtimeRepository extends ShowtimeRepository
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

class UnitFakeSeatRepository extends SeatRepository
{
    public array $items = [];

    public function __construct()
    {
    }

    public function listSeatMapForShowtime(int $showtimeId): array
    {
        return $this->items;
    }
}

class UnitFakeSeatLogger extends Logger
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
