<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\AdminShowtimeRepository;
use App\Repositories\MovieRepository;
use App\Repositories\RoomRepository;
use App\Validators\ShowtimeManagementValidator;
use PDO;
use Throwable;

class ShowtimeManagementService
{
    private PDO $db;
    private AdminShowtimeRepository $showtimes;
    private MovieRepository $movies;
    private RoomRepository $rooms;
    private ShowtimeManagementValidator $validator;
    private Logger $logger;

    public function __construct(
        ?PDO $db = null,
        ?AdminShowtimeRepository $showtimes = null,
        ?MovieRepository $movies = null,
        ?RoomRepository $rooms = null,
        ?ShowtimeManagementValidator $validator = null,
        ?Logger $logger = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->showtimes = $showtimes ?? new AdminShowtimeRepository($this->db);
        $this->movies = $movies ?? new MovieRepository($this->db);
        $this->rooms = $rooms ?? new RoomRepository($this->db);
        $this->validator = $validator ?? new ShowtimeManagementValidator();
        $this->logger = $logger ?? new Logger();
    }

    public function listShowtimes(array $filters): array
    {
        $normalizedFilters = $this->validator->normalizeAdminFilters($filters);
        $page = $this->showtimes->paginate($normalizedFilters);
        $summary = $this->showtimes->summarize($normalizedFilters);

        return $this->success([
            'items' => array_map([$this, 'mapShowtime'], $page['items']),
            'meta' => $this->paginationMeta($page),
            'summary' => $summary,
        ]);
    }

    public function getShowtime(int $id): array
    {
        $showtime = $this->showtimes->findById($id);
        if (!$showtime) {
            return $this->error(['showtime' => ['Showtime not found.']], 404);
        }

        return $this->success($this->mapShowtime($showtime));
    }

    public function createShowtime(array $payload, ?int $actorId = null): array
    {
        $startedAt = microtime(true);
        $validation = $this->validator->validatePayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $prepared = $this->prepareWritePayload($validation['data']);
        if (isset($prepared['errors'])) {
            return $prepared;
        }

        $data = $prepared['data'];
        if ($this->showtimes->hasOverlap((int) $data['room_id'], $data['show_date'], $data['start_time'], $data['end_time'])) {
            $this->logBusinessRuleBlock('Showtime create blocked by overlap', [
                'actor_id' => $actorId,
                'room_id' => $data['room_id'],
                'show_date' => $data['show_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
            ]);
            return $this->error(['showtime' => ['Room already has an overlapping showtime.']], 409);
        }

        try {
            $showtimeId = $this->showtimes->create($data);
        } catch (Throwable $exception) {
            $this->logger->error('Showtime creation failed', [
                'actor_id' => $actorId,
                'room_id' => $data['room_id'],
                'movie_id' => $data['movie_id'],
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to create showtime.']], 500);
        }

        $showtime = $this->showtimes->findById($showtimeId);
        $this->logger->info('Showtime created', [
            'actor_id' => $actorId,
            'showtime_id' => $showtimeId,
            'status' => $data['status'],
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success($this->mapShowtime($showtime ?: []), 201);
    }

    public function updateShowtime(int $id, array $payload, ?int $actorId = null): array
    {
        $startedAt = microtime(true);
        $existing = $this->showtimes->findById($id);
        if (!$existing) {
            return $this->error(['showtime' => ['Showtime not found.']], 404);
        }

        $validation = $this->validator->validatePayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $prepared = $this->prepareWritePayload($validation['data']);
        if (isset($prepared['errors'])) {
            return $prepared;
        }

        $data = $prepared['data'];
        if ($this->showtimes->hasOverlap((int) $data['room_id'], $data['show_date'], $data['start_time'], $data['end_time'], $id)) {
            $this->logBusinessRuleBlock('Showtime update blocked by overlap', [
                'actor_id' => $actorId,
                'showtime_id' => $id,
                'room_id' => $data['room_id'],
                'show_date' => $data['show_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
            ]);
            return $this->error(['showtime' => ['Room already has an overlapping showtime.']], 409);
        }

        try {
            $this->showtimes->update($id, $data);
        } catch (Throwable $exception) {
            $this->logger->error('Showtime update failed', [
                'actor_id' => $actorId,
                'showtime_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to update showtime.']], 500);
        }

        $showtime = $this->showtimes->findById($id);
        $this->logger->info('Showtime updated', [
            'actor_id' => $actorId,
            'showtime_id' => $id,
            'status' => $data['status'],
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success($this->mapShowtime($showtime ?: []));
    }

    public function archiveShowtime(int $id, ?int $actorId = null): array
    {
        $showtime = $this->showtimes->findById($id);
        if (!$showtime) {
            return $this->error(['showtime' => ['Showtime not found.']], 404);
        }

        try {
            $this->showtimes->archive($id);
        } catch (Throwable $exception) {
            $this->logger->error('Showtime archive failed', [
                'actor_id' => $actorId,
                'showtime_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to archive showtime.']], 500);
        }

        $this->logger->info('Showtime archived', [
            'actor_id' => $actorId,
            'showtime_id' => $id,
            'status' => 'archived',
        ]);

        return $this->success(['id' => $id, 'status' => 'archived']);
    }

    private function prepareWritePayload(array $data): array
    {
        $movie = $this->movies->findById((int) $data['movie_id']);
        if (!$movie || ($movie['status'] ?? null) === 'archived') {
            return $this->error(['movie_id' => ['Movie not found or archived.']], 404);
        }

        $room = $this->rooms->findById((int) $data['room_id']);
        if (!$room || ($room['status'] ?? null) === 'archived' || ($room['cinema_status'] ?? null) === 'archived') {
            return $this->error(['room_id' => ['Room not found or archived.']], 404);
        }

        if ($data['status'] === 'published') {
            if (($room['status'] ?? null) !== 'active') {
                $this->logBusinessRuleBlock('Published showtime blocked by inactive room', [
                    'room_id' => $data['room_id'],
                    'room_status' => $room['status'] ?? null,
                ]);
                return $this->error(['status' => ['Published showtimes require an active room.']], 409);
            }
            if (($room['cinema_status'] ?? null) !== 'active') {
                $this->logBusinessRuleBlock('Published showtime blocked by inactive cinema', [
                    'room_id' => $data['room_id'],
                    'cinema_status' => $room['cinema_status'] ?? null,
                ]);
                return $this->error(['status' => ['Published showtimes require an active cinema.']], 409);
            }
        }

        $durationMinutes = (int) ($movie['duration_minutes'] ?? 0);
        if ($durationMinutes <= 0) {
            return $this->error(['movie_id' => ['Movie duration is missing or invalid.']], 422);
        }

        $bufferMinutes = (int) ($room['cleaning_buffer_minutes'] ?? 0);
        $endTime = $this->calculateEndTime($data['start_time'], $durationMinutes + $bufferMinutes);
        if ($endTime === null || strcmp($endTime, $data['start_time']) <= 0) {
            return $this->error(['showtime' => ['Showtime end time must remain on the same day.']], 422);
        }

        return $this->success([
            'movie_id' => (int) $data['movie_id'],
            'room_id' => (int) $data['room_id'],
            'show_date' => $data['show_date'],
            'start_time' => $data['start_time'],
            'end_time' => $endTime,
            'price' => $data['price'],
            'status' => $data['status'],
            'presentation_type' => $data['presentation_type'],
            'language_version' => $data['language_version'],
        ]);
    }

    private function calculateEndTime(string $startTime, int $minutesToAdd): ?string
    {
        $base = strtotime('1970-01-01 ' . $startTime);
        if ($base === false) {
            return null;
        }

        $end = $base + max(0, $minutesToAdd) * 60;
        if ((int) date('d', $end) !== 1) {
            return null;
        }

        return date('H:i:s', $end);
    }

    private function mapShowtime(array $row): array
    {
        $totalSeats = (int) ($row['total_seats'] ?? 0);
        $bookedSeats = (int) ($row['booked_seats'] ?? 0);
        $availableSeats = max(0, $totalSeats - $bookedSeats);
        $isSoldOut = $totalSeats > 0 && $availableSeats <= 0;

        return [
            'id' => (int) ($row['id'] ?? 0),
            'movie_id' => (int) ($row['movie_id'] ?? 0),
            'movie_slug' => $row['movie_slug'] ?? null,
            'movie_title' => $row['movie_title'] ?? null,
            'movie_duration_minutes' => (int) ($row['duration_minutes'] ?? 0),
            'poster_url' => $row['poster_url'] ?? null,
            'room_id' => (int) ($row['room_id'] ?? 0),
            'room_name' => $row['room_name'] ?? null,
            'room_type' => $row['room_type'] ?? null,
            'screen_label' => $row['screen_label'] ?? null,
            'room_status' => $row['room_status'] ?? null,
            'cinema_id' => (int) ($row['cinema_id'] ?? 0),
            'cinema_slug' => $row['cinema_slug'] ?? null,
            'cinema_name' => $row['cinema_name'] ?? null,
            'cinema_city' => $row['cinema_city'] ?? null,
            'cinema_status' => $row['cinema_status'] ?? null,
            'show_date' => $row['show_date'] ?? null,
            'start_time' => $row['start_time'] ?? null,
            'end_time' => $row['end_time'] ?? null,
            'price' => isset($row['price']) ? (float) $row['price'] : 0.0,
            'status' => $row['status'] ?? null,
            'presentation_type' => $row['presentation_type'] ?? null,
            'language_version' => $row['language_version'] ?? null,
            'total_seats' => $totalSeats,
            'booked_seats' => $bookedSeats,
            'available_seats' => $availableSeats,
            'is_sold_out' => $isSoldOut,
            'availability_label' => $this->availabilityLabel($row['status'] ?? null, $availableSeats, $totalSeats),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
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

    private function durationMs(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 2);
    }

    private function logBusinessRuleBlock(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    private function success($data, int $status = 200): array
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
