<?php

namespace Tests\Unit;

use App\Core\Logger;
use App\Repositories\SeatRepository;
use App\Repositories\ShowtimeRepository;
use App\Services\ShowtimeCatalogService;
use App\Validators\ShowtimeManagementValidator;
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
            'end_time' => '18:30:00',
            'price' => '18.00',
            'status' => 'published',
            'presentation_type' => 'imax',
            'language_version' => 'subtitled',
            'cinema_name' => 'CinemaX Landmark',
            'room_name' => 'Hall 1 - IMAX',
            'cinema_city' => 'Ho Chi Minh City',
            'total_seats' => 24,
            'booked_seats' => 1,
        ];

        $seats = new UnitFakeSeatRepository();
        $seats->items = [
            ['id' => 1, 'seat_row' => 'A', 'seat_number' => 1, 'seat_type' => 'normal', 'status' => 'available', 'is_booked' => 0],
            ['id' => 2, 'seat_row' => 'A', 'seat_number' => 2, 'seat_type' => 'vip', 'status' => 'available', 'is_booked' => 1],
            ['id' => 3, 'seat_row' => 'B', 'seat_number' => 1, 'seat_type' => 'normal', 'status' => 'maintenance', 'is_booked' => 0],
        ];

        $service = new ShowtimeCatalogService($showtimes, $seats, new ShowtimeManagementValidator(), new UnitFakeSeatLogger());
        $result = $service->getSeatMap(501);

        $this->assertSame(200, $result['status']);
        $this->assertSame('Toi Pham 101', $result['data']['showtime']['movie_title']);
        $this->assertSame('A1', $result['data']['seats'][0]['label']);
        $this->assertTrue($result['data']['seats'][1]['is_booked']);
        $this->assertSame('maintenance', $result['data']['seats'][2]['status']);
        $this->assertFalse($result['data']['seats'][2]['is_selectable']);
        $this->assertSame(1, $result['data']['summary']['available_seats']);
        $this->assertSame(1, $result['data']['summary']['blocked_seats']);
    }

    public function testGetSeatMapReturnsNotFoundWhenShowtimeMissing(): void
    {
        $service = new ShowtimeCatalogService(
            new UnitFakeSeatShowtimeRepository(),
            new UnitFakeSeatRepository(),
            new ShowtimeManagementValidator(),
            new UnitFakeSeatLogger()
        );

        $result = $service->getSeatMap(999);

        $this->assertSame(404, $result['status']);
        $this->assertSame(['Showtime not found.'], $result['errors']['showtime']);
    }

    public function testListShowtimesReturnsCatalogItemsOptionsAndSummary(): void
    {
        $showtimes = new UnitFakeSeatShowtimeRepository();
        $showtimes->paginatedCatalog = [
            'items' => [[
                'id' => 700,
                'movie_id' => 4,
                'room_id' => 11,
                'show_date' => '2026-03-20',
                'start_time' => '19:00:00',
                'end_time' => '21:15:00',
                'price' => '9.50',
                'status' => 'published',
                'presentation_type' => '3d',
                'language_version' => 'dubbed',
                'movie_slug' => 'movie-seven-hundred',
                'movie_title' => 'Movie 700',
                'poster_url' => 'https://local.example.com/poster-700.jpg',
                'cinema_id' => 2,
                'cinema_name' => 'Downtown Cinema',
                'cinema_city' => 'Da Nang',
                'room_name' => 'Hall 5',
                'room_type' => 'premium_3d',
                'screen_label' => 'Screen 5',
                'total_seats' => 120,
                'booked_seats' => 115,
            ]],
            'page' => 1,
            'per_page' => 20,
            'total' => 1,
        ];
        $showtimes->publicOptions = [
            'movies' => [['id' => 4, 'title' => 'Movie 700']],
            'cinemas' => [['id' => 2, 'name' => 'Downtown Cinema', 'city' => 'Da Nang']],
            'cities' => ['Da Nang'],
        ];

        $service = new ShowtimeCatalogService(
            $showtimes,
            new UnitFakeSeatRepository(),
            new ShowtimeManagementValidator(),
            new UnitFakeSeatLogger()
        );

        $result = $service->listShowtimes([
            'movie_id' => '4',
            'city' => 'Da Nang',
            'page' => '1',
            'per_page' => '20',
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertSame('Movie 700', $result['data']['items'][0]['movie_title']);
        $this->assertSame('5 seats left', $result['data']['items'][0]['availability_label']);
        $this->assertSame('/seat-selection?showtime_id=700&slug=movie-seven-hundred', $result['data']['items'][0]['seat_selection_url']);
        $this->assertSame(['Da Nang'], $result['data']['options']['cities']);
        $this->assertSame(1, $result['data']['summary']['limited']);
    }
}

class UnitFakeSeatShowtimeRepository extends ShowtimeRepository
{
    public ?array $detail = null;
    public array $paginatedCatalog = [
        'items' => [],
        'page' => 1,
        'per_page' => 20,
        'total' => 0,
    ];
    public array $publicOptions = [
        'movies' => [],
        'cinemas' => [],
        'cities' => [],
    ];

    public function __construct()
    {
    }

    public function findPublicDetail(int $showtimeId): ?array
    {
        return $this->detail;
    }

    public function paginatePublicCatalog(array $filters): array
    {
        return $this->paginatedCatalog;
    }

    public function listPublicFilterOptions(): array
    {
        return $this->publicOptions;
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
