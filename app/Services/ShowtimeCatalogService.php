<?php

namespace App\Services;

use App\Core\Logger;
use App\Repositories\SeatRepository;
use App\Repositories\ShowtimeRepository;
use Throwable;

class ShowtimeCatalogService
{
    private ShowtimeRepository $showtimes;
    private SeatRepository $seats;
    private Logger $logger;

    public function __construct(
        ?ShowtimeRepository $showtimes = null,
        ?SeatRepository $seats = null,
        ?Logger $logger = null
    ) {
        $this->showtimes = $showtimes ?? new ShowtimeRepository();
        $this->seats = $seats ?? new SeatRepository();
        $this->logger = $logger ?? new Logger();
    }

    public function getSeatMap(int $showtimeId): array
    {
        if ($showtimeId <= 0) {
            return $this->error(['showtime' => ['Showtime not found.']], 404);
        }

        try {
            $showtime = $this->showtimes->findPublicDetail($showtimeId);
            if ($showtime === null) {
                return $this->error(['showtime' => ['Showtime not found.']], 404);
            }

            $seats = $this->seats->listSeatMapForShowtime($showtimeId);
        } catch (Throwable $exception) {
            $this->logger->error('Public showtime seat map load failed', [
                'showtime_id' => $showtimeId,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to load seat map.']], 500);
        }

        $mappedSeats = array_map([$this, 'mapSeat'], $seats);
        $bookedCount = count(array_filter($mappedSeats, static function (array $seat): bool {
            return (bool) ($seat['is_booked'] ?? false);
        }));

        return $this->success([
            'showtime' => $this->mapShowtime($showtime),
            'seats' => $mappedSeats,
            'summary' => [
                'total_seats' => count($mappedSeats),
                'booked_seats' => $bookedCount,
                'available_seats' => max(0, count($mappedSeats) - $bookedCount),
            ],
        ]);
    }

    private function mapShowtime(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'movie_id' => (int) ($row['movie_id'] ?? 0),
            'movie_slug' => $row['movie_slug'] ?? null,
            'movie_title' => $row['movie_title'] ?? null,
            'poster_url' => $row['poster_url'] ?? null,
            'show_date' => $row['show_date'] ?? null,
            'start_time' => $row['start_time'] ?? null,
            'price' => isset($row['price']) ? (float) $row['price'] : 0.0,
            'cinema_name' => $row['cinema_name'] ?? null,
            'room_name' => $row['room_name'] ?? null,
            'total_seats' => isset($row['total_seats']) ? (int) $row['total_seats'] : 0,
        ];
    }

    private function mapSeat(array $row): array
    {
        $seatRow = strtoupper(trim((string) ($row['seat_row'] ?? '')));
        $seatNumber = (int) ($row['seat_number'] ?? 0);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'row' => $seatRow,
            'number' => $seatNumber,
            'label' => trim($seatRow . $seatNumber),
            'type' => $row['seat_type'] ?? 'normal',
            'is_booked' => (bool) ($row['is_booked'] ?? false),
        ];
    }

    private function success(array $data, int $status = 200): array
    {
        return [
            'status' => $status,
            'data' => $data,
        ];
    }

    private function error(array $errors, int $status): array
    {
        return [
            'status' => $status,
            'errors' => $errors,
        ];
    }
}
