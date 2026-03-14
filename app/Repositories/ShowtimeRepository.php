<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ShowtimeRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function listUpcomingByMovie(int $movieId, int $limitDays = 6): array
    {
        $stmt = $this->db->prepare("
            SELECT
                s.id,
                s.movie_id,
                s.show_date,
                s.start_time,
                s.price,
                r.room_name,
                c.name AS cinema_name
            FROM showtimes s
            INNER JOIN rooms r ON r.id = s.room_id
            INNER JOIN cinemas c ON c.id = r.cinema_id
            WHERE s.movie_id = :movie_id
              AND s.show_date >= CURRENT_DATE
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

            $groupedByDate[$dateKey]['venues'][$venueKey]['times'][] = [
                'id' => (int) ($row['id'] ?? 0),
                'start_time' => $row['start_time'] ?? null,
                'price' => isset($row['price']) ? (float) $row['price'] : null,
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
}
