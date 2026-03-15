<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use PDO;
use Throwable;

class DemoDatasetMaintenanceService
{
    private const LEGACY_CINEMA_SLUGS = ['123123', 'test'];
    private const LEGACY_CINEMA_NAMES = ['123123', 'test'];

    private PDO $db;
    private Logger $logger;

    public function __construct(?PDO $db = null, ?Logger $logger = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->logger = $logger ?? new Logger();
    }

    public function cleanupLegacyCinemaFixtures(): array
    {
        $startedAt = microtime(true);
        $startedTransaction = !$this->db->inTransaction();

        if ($startedTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $cinemas = $this->findLegacyCinemas();
            if ($cinemas === []) {
                if ($startedTransaction && $this->db->inTransaction()) {
                    $this->db->commit();
                }

                return [
                    'legacy_cinema_ids' => [],
                    'archived_cinemas' => 0,
                    'archived_rooms' => 0,
                    'archived_seats' => 0,
                    'archived_showtimes' => 0,
                    'duration_ms' => $this->durationMs($startedAt),
                ];
            }

            $cinemaIds = array_map(static function (array $cinema): int {
                return (int) $cinema['id'];
            }, $cinemas);
            $roomIds = $this->findRoomIdsForCinemas($cinemaIds);

            $summary = [
                'legacy_cinema_ids' => $cinemaIds,
                'archived_cinemas' => $this->archiveCinemas($cinemaIds),
                'archived_rooms' => $this->archiveRooms($roomIds),
                'archived_seats' => $this->archiveSeats($roomIds),
                'archived_showtimes' => $this->archiveShowtimes($roomIds),
                'duration_ms' => 0.0,
            ];

            $this->resetRoomTotals($roomIds);

            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }

            $summary['duration_ms'] = $this->durationMs($startedAt);
            $this->logger->info('Legacy cinema demo fixtures archived', $summary);

            return $summary;
        } catch (Throwable $exception) {
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $this->logger->error('Legacy cinema demo cleanup failed', [
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function findLegacyCinemas(): array
    {
        $slugPlaceholders = $this->namedPlaceholders('slug_', self::LEGACY_CINEMA_SLUGS);
        $namePlaceholders = $this->namedPlaceholders('name_', self::LEGACY_CINEMA_NAMES);

        $params = [];
        foreach (self::LEGACY_CINEMA_SLUGS as $index => $slug) {
            $params['slug_' . $index] = $slug;
        }
        foreach (self::LEGACY_CINEMA_NAMES as $index => $name) {
            $params['name_' . $index] = $name;
        }

        $stmt = $this->db->prepare("
            SELECT id, slug, name, status
            FROM cinemas
            WHERE slug IN ({$slugPlaceholders})
               OR name IN ({$namePlaceholders})
            ORDER BY id ASC
        ");
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param int[] $cinemaIds
     * @return int[]
     */
    private function findRoomIdsForCinemas(array $cinemaIds): array
    {
        if ($cinemaIds === []) {
            return [];
        }

        $placeholders = $this->namedPlaceholders('cinema_id_', $cinemaIds);
        $params = [];
        foreach ($cinemaIds as $index => $cinemaId) {
            $params['cinema_id_' . $index] = $cinemaId;
        }

        $stmt = $this->db->prepare("
            SELECT id
            FROM rooms
            WHERE cinema_id IN ({$placeholders})
            ORDER BY id ASC
        ");
        $stmt->execute($params);

        return array_map(static function (array $row): int {
            return (int) $row['id'];
        }, $stmt->fetchAll() ?: []);
    }

    /**
     * @param int[] $cinemaIds
     */
    private function archiveCinemas(array $cinemaIds): int
    {
        if ($cinemaIds === []) {
            return 0;
        }

        $placeholders = $this->namedPlaceholders('archive_cinema_', $cinemaIds);
        $params = [];
        foreach ($cinemaIds as $index => $cinemaId) {
            $params['archive_cinema_' . $index] = $cinemaId;
        }

        $stmt = $this->db->prepare("
            UPDATE cinemas
            SET status = 'archived'
            WHERE id IN ({$placeholders})
              AND status <> 'archived'
        ");
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * @param int[] $roomIds
     */
    private function archiveRooms(array $roomIds): int
    {
        if ($roomIds === []) {
            return 0;
        }

        $placeholders = $this->namedPlaceholders('archive_room_', $roomIds);
        $params = [];
        foreach ($roomIds as $index => $roomId) {
            $params['archive_room_' . $index] = $roomId;
        }

        $stmt = $this->db->prepare("
            UPDATE rooms
            SET status = 'archived'
            WHERE id IN ({$placeholders})
              AND status <> 'archived'
        ");
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * @param int[] $roomIds
     */
    private function archiveSeats(array $roomIds): int
    {
        if ($roomIds === []) {
            return 0;
        }

        $placeholders = $this->namedPlaceholders('archive_seat_room_', $roomIds);
        $params = [];
        foreach ($roomIds as $index => $roomId) {
            $params['archive_seat_room_' . $index] = $roomId;
        }

        $stmt = $this->db->prepare("
            UPDATE seats
            SET status = 'archived'
            WHERE room_id IN ({$placeholders})
              AND status <> 'archived'
        ");
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * @param int[] $roomIds
     */
    private function archiveShowtimes(array $roomIds): int
    {
        if ($roomIds === []) {
            return 0;
        }

        $placeholders = $this->namedPlaceholders('archive_showtime_room_', $roomIds);
        $params = [];
        foreach ($roomIds as $index => $roomId) {
            $params['archive_showtime_room_' . $index] = $roomId;
        }

        $stmt = $this->db->prepare("
            UPDATE showtimes
            SET status = 'archived'
            WHERE room_id IN ({$placeholders})
              AND status <> 'archived'
        ");
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * @param int[] $roomIds
     */
    private function resetRoomTotals(array $roomIds): void
    {
        if ($roomIds === []) {
            return;
        }

        $placeholders = $this->namedPlaceholders('reset_room_', $roomIds);
        $params = [];
        foreach ($roomIds as $index => $roomId) {
            $params['reset_room_' . $index] = $roomId;
        }

        $stmt = $this->db->prepare("
            UPDATE rooms
            SET total_seats = 0
            WHERE id IN ({$placeholders})
        ");
        $stmt->execute($params);
    }

    /**
     * @param array<int, mixed> $values
     */
    private function namedPlaceholders(string $prefix, array $values): string
    {
        $placeholders = [];
        foreach (array_values($values) as $index => $value) {
            $placeholders[] = ':' . $prefix . $index;
        }

        return implode(', ', $placeholders);
    }

    private function durationMs(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 2);
    }
}
