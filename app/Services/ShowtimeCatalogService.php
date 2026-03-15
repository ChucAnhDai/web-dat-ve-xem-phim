<?php

namespace App\Services;

use App\Core\Logger;
use App\Repositories\SeatRepository;
use App\Repositories\ShowtimeRepository;
use App\Validators\ShowtimeManagementValidator;
use Throwable;

class ShowtimeCatalogService
{
    private ShowtimeRepository $showtimes;
    private SeatRepository $seats;
    private ShowtimeManagementValidator $validator;
    private Logger $logger;

    public function __construct(
        ?ShowtimeRepository $showtimes = null,
        ?SeatRepository $seats = null,
        ?ShowtimeManagementValidator $validator = null,
        ?Logger $logger = null
    ) {
        $this->showtimes = $showtimes ?? new ShowtimeRepository();
        $this->seats = $seats ?? new SeatRepository();
        $this->validator = $validator ?? new ShowtimeManagementValidator();
        $this->logger = $logger ?? new Logger();
    }

    public function listShowtimes(array $filters): array
    {
        $normalizedFilters = $this->validator->normalizePublicFilters($filters);

        try {
            $page = $this->showtimes->paginatePublicCatalog($normalizedFilters);
            $options = $this->showtimes->listPublicFilterOptions();
        } catch (Throwable $exception) {
            $this->logger->error('Public showtime catalog load failed', [
                'filters' => $normalizedFilters,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to load showtimes.']], 500);
        }

        $items = array_map([$this, 'mapCatalogShowtime'], $page['items']);

        return $this->success([
            'items' => $items,
            'meta' => $this->paginationMeta($page),
            'filters' => [
                'search' => $normalizedFilters['search'],
                'movie_id' => $normalizedFilters['movie_id'],
                'cinema_id' => $normalizedFilters['cinema_id'],
                'city' => $normalizedFilters['city'],
                'show_date' => $normalizedFilters['show_date'],
            ],
            'options' => [
                'movies' => array_map(static function (array $movie): array {
                    return [
                        'id' => (int) ($movie['id'] ?? 0),
                        'title' => $movie['title'] ?? null,
                    ];
                }, $options['movies'] ?? []),
                'cinemas' => array_map(static function (array $cinema): array {
                    return [
                        'id' => (int) ($cinema['id'] ?? 0),
                        'name' => $cinema['name'] ?? null,
                        'city' => $cinema['city'] ?? null,
                    ];
                }, $options['cinemas'] ?? []),
                'cities' => array_values($options['cities'] ?? []),
            ],
            'summary' => $this->catalogSummary($items),
        ]);
    }

    public function getSeatMap(int $showtimeId): array
    {
        return $this->getSeatMapForSession($showtimeId);
    }

    public function getSeatMapForSession(int $showtimeId, ?string $sessionToken = null): array
    {
        if ($showtimeId <= 0) {
            return $this->error(['showtime' => ['Showtime not found.']], 404);
        }

        try {
            $showtime = $this->showtimes->findPublicDetail($showtimeId);
            if ($showtime === null) {
                return $this->error(['showtime' => ['Showtime not found.']], 404);
            }

            $seats = $this->seats->listSeatMapForShowtimeSession($showtimeId, $sessionToken);
        } catch (Throwable $exception) {
            $this->logger->error('Public showtime seat map load failed', [
                'showtime_id' => $showtimeId,
                'has_session_token' => $sessionToken !== null && trim($sessionToken) !== '',
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to load seat map.']], 500);
        }

        $mappedSeats = array_map([$this, 'mapSeat'], $seats);
        $bookedCount = count(array_filter($mappedSeats, static function (array $seat): bool {
            return (bool) ($seat['is_booked'] ?? false);
        }));
        $availableCount = count(array_filter($mappedSeats, static function (array $seat): bool {
            return (bool) ($seat['is_selectable'] ?? false) && !($seat['is_booked'] ?? false);
        }));
        $heldCount = count(array_filter($mappedSeats, static function (array $seat): bool {
            return (bool) ($seat['is_held'] ?? false);
        }));
        $heldByCurrentSessionCount = count(array_filter($mappedSeats, static function (array $seat): bool {
            return (bool) ($seat['held_by_current_session'] ?? false);
        }));
        $blockedCount = count($mappedSeats) - $bookedCount - $availableCount;

        return $this->success([
            'showtime' => $this->mapShowtime($showtime),
            'seats' => $mappedSeats,
            'summary' => [
                'total_seats' => count($mappedSeats),
                'booked_seats' => $bookedCount,
                'available_seats' => max(0, $availableCount),
                'held_seats' => max(0, $heldCount),
                'held_by_current_session_seats' => max(0, $heldByCurrentSessionCount),
                'blocked_seats' => max(0, $blockedCount),
            ],
        ]);
    }

    private function mapShowtime(array $row): array
    {
        $totalSeats = isset($row['total_seats']) ? (int) $row['total_seats'] : 0;
        $bookedSeats = isset($row['booked_seats']) ? (int) $row['booked_seats'] : 0;
        $heldSeats = isset($row['held_seats']) ? (int) $row['held_seats'] : 0;
        $availableSeats = max(0, $totalSeats - $bookedSeats - $heldSeats);
        $status = $row['status'] ?? null;

        return [
            'id' => (int) ($row['id'] ?? 0),
            'movie_id' => (int) ($row['movie_id'] ?? 0),
            'movie_slug' => $row['movie_slug'] ?? null,
            'movie_title' => $row['movie_title'] ?? null,
            'poster_url' => $row['poster_url'] ?? null,
            'show_date' => $row['show_date'] ?? null,
            'start_time' => $row['start_time'] ?? null,
            'end_time' => $row['end_time'] ?? null,
            'price' => isset($row['price']) ? (float) $row['price'] : 0.0,
            'status' => $status,
            'presentation_type' => $row['presentation_type'] ?? null,
            'language_version' => $row['language_version'] ?? null,
            'cinema_name' => $row['cinema_name'] ?? null,
            'cinema_city' => $row['cinema_city'] ?? null,
            'room_name' => $row['room_name'] ?? null,
            'total_seats' => $totalSeats,
            'booked_seats' => $bookedSeats,
            'held_seats' => $heldSeats,
            'available_seats' => $availableSeats,
            'is_sold_out' => $totalSeats > 0 && $availableSeats <= 0,
            'availability_label' => $this->availabilityLabel($status, $availableSeats, $totalSeats),
        ];
    }

    private function mapSeat(array $row): array
    {
        $seatRow = strtoupper(trim((string) ($row['seat_row'] ?? '')));
        $seatNumber = (int) ($row['seat_number'] ?? 0);
        $status = $row['status'] ?? 'available';
        $isBooked = (bool) ($row['is_booked'] ?? false);
        $isHeld = (bool) ($row['is_held'] ?? false);
        $heldByCurrentSession = (bool) ($row['held_by_current_session'] ?? false);
        $isSelectable = $status === 'available' && $isBooked === false && ($isHeld === false || $heldByCurrentSession);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'row' => $seatRow,
            'number' => $seatNumber,
            'label' => trim($seatRow . $seatNumber),
            'type' => $row['seat_type'] ?? 'normal',
            'status' => $status,
            'is_booked' => $isBooked,
            'is_held' => $isHeld,
            'held_by_current_session' => $heldByCurrentSession,
            'hold_expires_at' => $row['hold_expires_at'] ?? null,
            'is_selectable' => $isSelectable,
        ];
    }

    private function mapCatalogShowtime(array $row): array
    {
        $totalSeats = (int) ($row['total_seats'] ?? 0);
        $bookedSeats = (int) ($row['booked_seats'] ?? 0);
        $heldSeats = (int) ($row['held_seats'] ?? 0);
        $availableSeats = max(0, $totalSeats - $bookedSeats - $heldSeats);
        $status = $row['status'] ?? null;

        return [
            'id' => (int) ($row['id'] ?? 0),
            'movie_id' => (int) ($row['movie_id'] ?? 0),
            'movie_slug' => $row['movie_slug'] ?? null,
            'movie_title' => $row['movie_title'] ?? null,
            'poster_url' => $row['poster_url'] ?? null,
            'cinema_id' => (int) ($row['cinema_id'] ?? 0),
            'cinema_name' => $row['cinema_name'] ?? null,
            'cinema_city' => $row['cinema_city'] ?? null,
            'room_id' => (int) ($row['room_id'] ?? 0),
            'room_name' => $row['room_name'] ?? null,
            'room_type' => $row['room_type'] ?? null,
            'screen_label' => $row['screen_label'] ?? null,
            'show_date' => $row['show_date'] ?? null,
            'start_time' => $row['start_time'] ?? null,
            'end_time' => $row['end_time'] ?? null,
            'price' => isset($row['price']) ? (float) $row['price'] : 0.0,
            'status' => $status,
            'presentation_type' => $row['presentation_type'] ?? null,
            'language_version' => $row['language_version'] ?? null,
            'total_seats' => $totalSeats,
            'booked_seats' => $bookedSeats,
            'held_seats' => $heldSeats,
            'available_seats' => $availableSeats,
            'is_sold_out' => $totalSeats > 0 && $availableSeats <= 0,
            'availability_label' => $this->availabilityLabel($status, $availableSeats, $totalSeats),
            'seat_selection_url' => $this->buildSeatSelectionUrl((int) ($row['id'] ?? 0), (string) ($row['movie_slug'] ?? '')),
        ];
    }

    private function catalogSummary(array $items): array
    {
        $summary = [
            'total_items' => count($items),
            'sold_out' => 0,
            'limited' => 0,
            'available' => 0,
        ];

        foreach ($items as $item) {
            $availableSeats = (int) ($item['available_seats'] ?? 0);
            $totalSeats = (int) ($item['total_seats'] ?? 0);

            if (!empty($item['is_sold_out'])) {
                $summary['sold_out'] += 1;
                continue;
            }

            if ($totalSeats > 0 && $availableSeats <= 10) {
                $summary['limited'] += 1;
                continue;
            }

            $summary['available'] += 1;
        }

        return $summary;
    }

    private function availabilityLabel(?string $status, int $availableSeats, int $totalSeats): string
    {
        if ($status === 'cancelled') {
            return 'Cancelled';
        }
        if ($status === 'draft') {
            return 'Draft';
        }
        if ($totalSeats > 0 && $availableSeats <= 0) {
            return 'Sold Out';
        }
        if ($totalSeats > 0 && $availableSeats <= 10) {
            return $availableSeats . ' seats left';
        }

        return 'Available';
    }

    private function buildSeatSelectionUrl(int $showtimeId, string $movieSlug): string
    {
        $params = ['showtime_id=' . rawurlencode((string) $showtimeId)];
        if (trim($movieSlug) !== '') {
            $params[] = 'slug=' . rawurlencode($movieSlug);
        }

        return '/seat-selection?' . implode('&', $params);
    }

    private function paginationMeta(array $page): array
    {
        $totalPages = (int) ceil(($page['total'] ?: 0) / max(1, $page['per_page']));

        return [
            'total' => (int) ($page['total'] ?? 0),
            'page' => (int) ($page['page'] ?? 1),
            'per_page' => (int) ($page['per_page'] ?? 20),
            'total_pages' => max(1, $totalPages),
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
