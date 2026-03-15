<?php

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\Concerns\PaginatesQueries;
use PDO;

class ShowtimeRepository
{
    use PaginatesQueries;

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function listUpcomingByMovie(int $movieId, int $limitDays = 6): array
    {
        $bookedSubquery = $this->bookedSeatsSubquery();
        $heldSubquery = $this->heldSeatsSubquery();

        $stmt = $this->db->prepare("
            SELECT
                s.id,
                s.movie_id,
                s.show_date,
                s.start_time,
                s.end_time,
                s.price,
                s.status,
                s.presentation_type,
                s.language_version,
                r.room_name,
                r.total_seats,
                c.name AS cinema_name,
                COALESCE(bookings.booked_seats, 0) AS booked_seats,
                COALESCE(holds.held_seats, 0) AS held_seats
            FROM showtimes s
            INNER JOIN movies m ON m.id = s.movie_id
            INNER JOIN rooms r ON r.id = s.room_id
            INNER JOIN cinemas c ON c.id = r.cinema_id
            LEFT JOIN ({$bookedSubquery}) bookings ON bookings.showtime_id = s.id
            LEFT JOIN ({$heldSubquery}) holds ON holds.showtime_id = s.id
            WHERE s.movie_id = :movie_id
              AND s.show_date >= CURRENT_DATE
              AND s.status = 'published'
              AND " . $this->publicMovieStatusCondition('m') . "
              AND r.status = 'active'
              AND c.status = 'active'
            ORDER BY s.show_date ASC, s.start_time ASC, s.id ASC
        ");
        $stmt->execute(['movie_id' => $movieId]);

        $rows = $stmt->fetchAll() ?: [];
        if (empty($rows)) {
            return [];
        }

        $groupedByDate = [];
        foreach ($rows as $row) {
            $dateKey = (string) ($row['show_date'] ?? '');
            if ($dateKey === '') {
                continue;
            }

            if (!isset($groupedByDate[$dateKey])) {
                if (count($groupedByDate) >= $limitDays) {
                    continue;
                }

                $groupedByDate[$dateKey] = [
                    'date' => $dateKey,
                    'venues' => [],
                ];
            }

            $venueKey = sprintf(
                '%s::%s',
                (string) ($row['cinema_name'] ?? ''),
                (string) ($row['room_name'] ?? '')
            );

            if (!isset($groupedByDate[$dateKey]['venues'][$venueKey])) {
                $groupedByDate[$dateKey]['venues'][$venueKey] = [
                    'cinema_name' => $row['cinema_name'] ?? null,
                    'room_name' => $row['room_name'] ?? null,
                    'times' => [],
                ];
            }

            $totalSeats = (int) ($row['total_seats'] ?? 0);
            $bookedSeats = (int) ($row['booked_seats'] ?? 0);
            $heldSeats = (int) ($row['held_seats'] ?? 0);
            $availableSeats = max(0, $totalSeats - $bookedSeats - $heldSeats);

            $groupedByDate[$dateKey]['venues'][$venueKey]['times'][] = [
                'id' => (int) ($row['id'] ?? 0),
                'start_time' => $row['start_time'] ?? null,
                'end_time' => $row['end_time'] ?? null,
                'price' => isset($row['price']) ? (float) $row['price'] : null,
                'status' => $row['status'] ?? null,
                'presentation_type' => $row['presentation_type'] ?? null,
                'language_version' => $row['language_version'] ?? null,
                'booked_seats' => $bookedSeats,
                'held_seats' => $heldSeats,
                'available_seats' => $availableSeats,
                'total_seats' => $totalSeats,
                'is_sold_out' => $totalSeats > 0 && $availableSeats <= 0,
            ];
        }

        $result = [];
        foreach ($groupedByDate as $dateGroup) {
            $result[] = [
                'date' => $dateGroup['date'],
                'venues' => array_values($dateGroup['venues']),
            ];
        }

        return $result;
    }

    public function paginatePublicCatalog(array $filters): array
    {
        ['where' => $where, 'params' => $params] = $this->buildPublicFilterParts($filters);

        $bookedSubquery = $this->bookedSeatsSubquery();
        $heldSubquery = $this->heldSeatsSubquery();

        $selectSql = "
            SELECT
                s.id,
                s.movie_id,
                s.room_id,
                s.show_date,
                s.start_time,
                s.end_time,
                s.price,
                s.status,
                s.presentation_type,
                s.language_version,
                m.slug AS movie_slug,
                m.title AS movie_title,
                m.poster_url,
                m.status AS movie_status,
                c.id AS cinema_id,
                c.name AS cinema_name,
                c.city AS cinema_city,
                r.room_name,
                r.room_type,
                r.screen_label,
                r.total_seats,
                COALESCE(bookings.booked_seats, 0) AS booked_seats,
                COALESCE(holds.held_seats, 0) AS held_seats
            FROM showtimes s
            INNER JOIN movies m ON m.id = s.movie_id
            INNER JOIN rooms r ON r.id = s.room_id
            INNER JOIN cinemas c ON c.id = r.cinema_id
            LEFT JOIN ({$bookedSubquery}) bookings ON bookings.showtime_id = s.id
            LEFT JOIN ({$heldSubquery}) holds ON holds.showtime_id = s.id
            {$where}
            ORDER BY s.show_date ASC, s.start_time ASC, m.title ASC, c.name ASC, r.room_name ASC
        ";

        $countSql = "
            SELECT COUNT(*)
            FROM showtimes s
            INNER JOIN movies m ON m.id = s.movie_id
            INNER JOIN rooms r ON r.id = s.room_id
            INNER JOIN cinemas c ON c.id = r.cinema_id
            {$where}
        ";

        return $this->paginateQuery($this->db, $selectSql, $countSql, $params, $filters['page'], $filters['per_page']);
    }

    public function listPublicFilterOptions(): array
    {
        $rows = $this->db->query("
            SELECT
                m.id AS movie_id,
                m.title AS movie_title,
                c.id AS cinema_id,
                c.name AS cinema_name,
                c.city AS cinema_city
            FROM showtimes s
            INNER JOIN movies m ON m.id = s.movie_id
            INNER JOIN rooms r ON r.id = s.room_id
            INNER JOIN cinemas c ON c.id = r.cinema_id
            WHERE s.status = 'published'
              AND s.show_date >= CURRENT_DATE
              AND " . $this->publicMovieStatusCondition('m') . "
              AND r.status = 'active'
              AND c.status = 'active'
            ORDER BY m.title ASC, c.name ASC
        ")->fetchAll() ?: [];

        $movies = [];
        $cinemas = [];
        $cities = [];

        foreach ($rows as $row) {
            $movieId = (int) ($row['movie_id'] ?? 0);
            if ($movieId > 0 && !isset($movies[$movieId])) {
                $movies[$movieId] = [
                    'id' => $movieId,
                    'title' => $row['movie_title'] ?? null,
                ];
            }

            $cinemaId = (int) ($row['cinema_id'] ?? 0);
            if ($cinemaId > 0 && !isset($cinemas[$cinemaId])) {
                $cinemas[$cinemaId] = [
                    'id' => $cinemaId,
                    'name' => $row['cinema_name'] ?? null,
                    'city' => $row['cinema_city'] ?? null,
                ];
            }

            $city = trim((string) ($row['cinema_city'] ?? ''));
            if ($city !== '') {
                $cities[$city] = $city;
            }
        }

        ksort($cities);

        return [
            'movies' => array_values($movies),
            'cinemas' => array_values($cinemas),
            'cities' => array_values($cities),
        ];
    }

    public function findPublicDetail(int $showtimeId): ?array
    {
        $bookedSubquery = $this->bookedSeatsSubquery();
        $heldSubquery = $this->heldSeatsSubquery();

        $stmt = $this->db->prepare("
            SELECT
                s.id,
                s.movie_id,
                s.room_id,
                s.show_date,
                s.start_time,
                s.end_time,
                s.price,
                s.status,
                s.presentation_type,
                s.language_version,
                m.slug AS movie_slug,
                m.title AS movie_title,
                m.poster_url,
                c.name AS cinema_name,
                c.city AS cinema_city,
                c.status AS cinema_status,
                r.room_name,
                r.total_seats,
                r.status AS room_status,
                COALESCE(bookings.booked_seats, 0) AS booked_seats,
                COALESCE(holds.held_seats, 0) AS held_seats
            FROM showtimes s
            INNER JOIN movies m ON m.id = s.movie_id
            INNER JOIN rooms r ON r.id = s.room_id
            INNER JOIN cinemas c ON c.id = r.cinema_id
            LEFT JOIN ({$bookedSubquery}) bookings ON bookings.showtime_id = s.id
            LEFT JOIN ({$heldSubquery}) holds ON holds.showtime_id = s.id
            WHERE s.id = :id
              AND s.status = 'published'
              AND " . $this->publicMovieStatusCondition('m') . "
              AND r.status = 'active'
              AND c.status = 'active'
            LIMIT 1
        ");
        $stmt->execute(['id' => $showtimeId]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function buildPublicFilterParts(array $filters): array
    {
        $conditions = [
            "s.status = 'published'",
            "s.show_date >= CURRENT_DATE",
            $this->publicMovieStatusCondition('m'),
            "r.status = 'active'",
            "c.status = 'active'",
        ];
        $params = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(m.title LIKE :search OR c.name LIKE :search OR c.city LIKE :search OR r.room_name LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['movie_id'])) {
            $conditions[] = 'm.id = :movie_id';
            $params['movie_id'] = (int) $filters['movie_id'];
        }
        if (!empty($filters['cinema_id'])) {
            $conditions[] = 'c.id = :cinema_id';
            $params['cinema_id'] = (int) $filters['cinema_id'];
        }
        if (!empty($filters['city'])) {
            $conditions[] = 'c.city = :city';
            $params['city'] = $filters['city'];
        }
        if (!empty($filters['show_date'])) {
            $conditions[] = 's.show_date = :show_date';
            $params['show_date'] = $filters['show_date'];
        }

        return [
            'where' => ' WHERE ' . implode(' AND ', $conditions),
            'params' => $params,
        ];
    }

    private function publicMovieStatusCondition(string $movieAlias = 'm'): string
    {
        return sprintf("%s.status IN ('now_showing', 'coming_soon')", $movieAlias);
    }

    private function bookedSeatsSubquery(): string
    {
        return "
            SELECT td.showtime_id, COUNT(DISTINCT td.seat_id) AS booked_seats
            FROM ticket_details td
            INNER JOIN ticket_orders o ON o.id = td.order_id
            WHERE o.status IN ('pending', 'paid')
            GROUP BY td.showtime_id
        ";
    }

    private function heldSeatsSubquery(): string
    {
        return "
            SELECT showtime_id, COUNT(DISTINCT seat_id) AS held_seats
            FROM ticket_seat_holds
            WHERE hold_expires_at > CURRENT_TIMESTAMP
            GROUP BY showtime_id
        ";
    }
}
