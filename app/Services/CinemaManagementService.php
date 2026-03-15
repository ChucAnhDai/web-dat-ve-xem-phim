<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\CinemaRepository;
use App\Repositories\RoomRepository;
use App\Repositories\SeatRepository;
use App\Validators\CinemaManagementValidator;
use PDO;
use Throwable;

class CinemaManagementService
{
    private PDO $db;
    private CinemaRepository $cinemas;
    private RoomRepository $rooms;
    private SeatRepository $seats;
    private CinemaManagementValidator $validator;
    private Logger $logger;

    public function __construct(
        ?PDO $db = null,
        ?CinemaRepository $cinemas = null,
        ?RoomRepository $rooms = null,
        ?SeatRepository $seats = null,
        ?CinemaManagementValidator $validator = null,
        ?Logger $logger = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->cinemas = $cinemas ?? new CinemaRepository($this->db);
        $this->rooms = $rooms ?? new RoomRepository($this->db);
        $this->seats = $seats ?? new SeatRepository($this->db);
        $this->validator = $validator ?? new CinemaManagementValidator();
        $this->logger = $logger ?? new Logger();
    }

    public function listCinemas(array $filters): array
    {
        $normalizedFilters = $this->validator->normalizeCinemaFilters($filters);
        $page = $this->cinemas->paginate($normalizedFilters);
        $summary = $this->cinemas->summarize($normalizedFilters);

        return $this->success([
            'items' => array_map([$this, 'mapCinema'], $page['items']),
            'meta' => $this->paginationMeta($page),
            'summary' => $summary,
            'options' => [
                'cities' => $this->cinemas->listCities($normalizedFilters),
            ],
        ]);
    }

    public function getCinema(int $id): array
    {
        $cinema = $this->cinemas->findById($id);
        if (!$cinema) {
            return $this->error(['cinema' => ['Cinema not found.']], 404);
        }

        return $this->success($this->mapCinema($cinema));
    }

    public function createCinema(array $payload, ?int $actorId = null): array
    {
        $startedAt = microtime(true);
        $validation = $this->validator->validateCinemaPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $data = $validation['data'];
        if ($this->cinemas->findBySlug($data['slug'])) {
            $this->logBusinessRuleBlock('Cinema create blocked by duplicate slug', [
                'actor_id' => $actorId,
                'slug' => $data['slug'],
            ]);
            return $this->error(['slug' => ['Cinema slug already exists.']], 409);
        }

        try {
            $cinemaId = $this->cinemas->create($data);
        } catch (Throwable $exception) {
            $this->logger->error('Cinema creation failed', [
                'actor_id' => $actorId,
                'slug' => $data['slug'],
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to create cinema.']], 500);
        }

        $cinema = $this->cinemas->findById($cinemaId);
        $this->logger->info('Cinema created', [
            'actor_id' => $actorId,
            'cinema_id' => $cinemaId,
            'status' => $data['status'],
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success($this->mapCinema($cinema ?: []), 201);
    }

    public function updateCinema(int $id, array $payload, ?int $actorId = null): array
    {
        $startedAt = microtime(true);
        $existing = $this->cinemas->findById($id);
        if (!$existing) {
            return $this->error(['cinema' => ['Cinema not found.']], 404);
        }

        $validation = $this->validator->validateCinemaPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $data = $validation['data'];
        if ($this->cinemas->findBySlug($data['slug'], $id)) {
            $this->logBusinessRuleBlock('Cinema update blocked by duplicate slug', [
                'actor_id' => $actorId,
                'cinema_id' => $id,
                'slug' => $data['slug'],
            ]);
            return $this->error(['slug' => ['Cinema slug already exists.']], 409);
        }

        $transitionError = $this->validateCinemaStatusTransition($id, $data['status']);
        if ($transitionError !== null) {
            return $transitionError;
        }

        try {
            $this->cinemas->update($id, $data);
        } catch (Throwable $exception) {
            $this->logger->error('Cinema update failed', [
                'actor_id' => $actorId,
                'cinema_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to update cinema.']], 500);
        }

        $cinema = $this->cinemas->findById($id);
        $this->logger->info('Cinema updated', [
            'actor_id' => $actorId,
            'cinema_id' => $id,
            'status' => $data['status'],
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success($this->mapCinema($cinema ?: []));
    }

    public function archiveCinema(int $id, ?int $actorId = null): array
    {
        $cinema = $this->cinemas->findById($id);
        if (!$cinema) {
            return $this->error(['cinema' => ['Cinema not found.']], 404);
        }

        if ($this->cinemas->hasActiveRooms($id)) {
            $this->logBusinessRuleBlock('Cinema archive blocked by active rooms', [
                'actor_id' => $actorId,
                'cinema_id' => $id,
            ]);
            return $this->error(['cinema' => ['Cannot archive cinema while active rooms still exist.']], 409);
        }
        if ($this->cinemas->hasFuturePublishedShowtimes($id)) {
            $this->logBusinessRuleBlock('Cinema archive blocked by future published showtimes', [
                'actor_id' => $actorId,
                'cinema_id' => $id,
            ]);
            return $this->error(['cinema' => ['Cannot archive cinema while published future showtimes exist.']], 409);
        }

        try {
            $this->cinemas->archive($id);
        } catch (Throwable $exception) {
            $this->logger->error('Cinema archive failed', [
                'actor_id' => $actorId,
                'cinema_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to archive cinema.']], 500);
        }

        $this->logger->info('Cinema archived', [
            'actor_id' => $actorId,
            'cinema_id' => $id,
            'status' => 'archived',
        ]);

        return $this->success(['id' => $id, 'status' => 'archived']);
    }

    public function listRooms(array $filters): array
    {
        $normalizedFilters = $this->validator->normalizeRoomFilters($filters);
        $page = $this->rooms->paginate($normalizedFilters);
        $summary = $this->rooms->summarize($normalizedFilters);

        return $this->success([
            'items' => array_map([$this, 'mapRoom'], $page['items']),
            'meta' => $this->paginationMeta($page),
            'summary' => $summary,
            'options' => [
                'cinemas' => array_map(
                    [$this, 'mapCinemaOption'],
                    $this->cinemas->listOptions(null, ($normalizedFilters['scope'] ?? 'active') === 'archived' ? 'all' : 'active')
                ),
            ],
        ]);
    }

    public function getRoom(int $id): array
    {
        $room = $this->rooms->findById($id);
        if (!$room) {
            return $this->error(['room' => ['Room not found.']], 404);
        }

        return $this->success($this->mapRoom($room));
    }

    public function createRoom(array $payload, ?int $actorId = null): array
    {
        $startedAt = microtime(true);
        $validation = $this->validator->validateRoomPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $data = $validation['data'];
        $cinema = $this->cinemas->findById((int) $data['cinema_id']);
        if (!$cinema || ($cinema['status'] ?? null) === 'archived') {
            return $this->error(['cinema_id' => ['Cinema not found or archived.']], 404);
        }
        if ($this->rooms->findDuplicateName((int) $data['cinema_id'], $data['room_name'])) {
            $this->logBusinessRuleBlock('Room create blocked by duplicate room name', [
                'actor_id' => $actorId,
                'cinema_id' => $data['cinema_id'],
                'room_name' => $data['room_name'],
            ]);
            return $this->error(['room_name' => ['Room name already exists in this cinema.']], 409);
        }
        $transitionError = $this->validateRoomStatusTransition(0, $data['status'], false);
        if ($transitionError !== null) {
            return $transitionError;
        }

        try {
            $roomId = $this->rooms->create($data);
        } catch (Throwable $exception) {
            $this->logger->error('Room creation failed', [
                'actor_id' => $actorId,
                'cinema_id' => $data['cinema_id'],
                'room_name' => $data['room_name'],
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to create room.']], 500);
        }

        $room = $this->rooms->findById($roomId);
        $this->logger->info('Room created', [
            'actor_id' => $actorId,
            'room_id' => $roomId,
            'status' => $data['status'],
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success($this->mapRoom($room ?: []), 201);
    }

    public function updateRoom(int $id, array $payload, ?int $actorId = null): array
    {
        $startedAt = microtime(true);
        $existing = $this->rooms->findById($id);
        if (!$existing) {
            return $this->error(['room' => ['Room not found.']], 404);
        }

        $validation = $this->validator->validateRoomPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $data = $validation['data'];
        $cinema = $this->cinemas->findById((int) $data['cinema_id']);
        if (!$cinema || ($cinema['status'] ?? null) === 'archived') {
            return $this->error(['cinema_id' => ['Cinema not found or archived.']], 404);
        }
        if ($this->rooms->findDuplicateName((int) $data['cinema_id'], $data['room_name'], $id)) {
            $this->logBusinessRuleBlock('Room update blocked by duplicate room name', [
                'actor_id' => $actorId,
                'room_id' => $id,
                'cinema_id' => $data['cinema_id'],
                'room_name' => $data['room_name'],
            ]);
            return $this->error(['room_name' => ['Room name already exists in this cinema.']], 409);
        }
        $transitionError = $this->validateRoomStatusTransition($id, $data['status'], true);
        if ($transitionError !== null) {
            return $transitionError;
        }

        try {
            $this->rooms->update($id, $data);
        } catch (Throwable $exception) {
            $this->logger->error('Room update failed', [
                'actor_id' => $actorId,
                'room_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to update room.']], 500);
        }

        $room = $this->rooms->findById($id);
        $this->logger->info('Room updated', [
            'actor_id' => $actorId,
            'room_id' => $id,
            'status' => $data['status'],
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success($this->mapRoom($room ?: []));
    }

    public function archiveRoom(int $id, ?int $actorId = null): array
    {
        $room = $this->rooms->findById($id);
        if (!$room) {
            return $this->error(['room' => ['Room not found.']], 404);
        }

        if ($this->rooms->hasFuturePublishedShowtimes($id)) {
            $this->logBusinessRuleBlock('Room archive blocked by future published showtimes', [
                'actor_id' => $actorId,
                'room_id' => $id,
            ]);
            return $this->error(['room' => ['Cannot archive room while published future showtimes exist.']], 409);
        }

        try {
            $this->rooms->archive($id);
        } catch (Throwable $exception) {
            $this->logger->error('Room archive failed', [
                'actor_id' => $actorId,
                'room_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to archive room.']], 500);
        }

        $this->logger->info('Room archived', [
            'actor_id' => $actorId,
            'room_id' => $id,
            'status' => 'archived',
        ]);

        return $this->success(['id' => $id, 'status' => 'archived']);
    }

    public function getRoomSeats(int $roomId): array
    {
        $room = $this->rooms->findById($roomId);
        if (!$room) {
            return $this->error(['room' => ['Room not found.']], 404);
        }

        $seats = $this->seats->listRoomLayout($roomId);
        $summary = $this->seats->summarizeRoomLayout($roomId);

        return $this->success([
            'room' => $this->mapRoom($room),
            'seats' => array_map([$this, 'mapSeat'], $seats),
            'summary' => $summary,
        ]);
    }

    public function replaceRoomSeats(int $roomId, array $payload, ?int $actorId = null): array
    {
        $startedAt = microtime(true);
        $room = $this->rooms->findById($roomId);
        if (!$room) {
            return $this->error(['room' => ['Room not found.']], 404);
        }

        $validation = $this->validator->validateSeatLayoutPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        if ($this->rooms->hasFuturePublishedShowtimes($roomId)) {
            $this->logBusinessRuleBlock('Seat layout replace blocked by future published showtimes', [
                'actor_id' => $actorId,
                'room_id' => $roomId,
            ]);
            return $this->error(['room' => ['Cannot replace seat layout while published future showtimes exist.']], 409);
        }
        if ($this->rooms->hasBookedTickets($roomId) || $this->seats->hasBookedTicketsForRoom($roomId)) {
            $this->logBusinessRuleBlock('Seat layout replace blocked by booked tickets', [
                'actor_id' => $actorId,
                'room_id' => $roomId,
            ]);
            return $this->error(['room' => ['Cannot replace seat layout for a room with pending or paid tickets.']], 409);
        }

        $seatPayload = $validation['data']['seats'];

        try {
            $this->transactional(function () use ($roomId, $seatPayload) {
                $this->seats->replaceRoomLayout($roomId, $seatPayload);
                $this->rooms->updateTotalSeats($roomId, count(array_filter($seatPayload, static function (array $seat): bool {
                    return ($seat['status'] ?? 'available') !== 'archived';
                })));
            });
        } catch (Throwable $exception) {
            $this->logger->error('Room seat layout replace failed', [
                'actor_id' => $actorId,
                'room_id' => $roomId,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to replace seat layout.']], 500);
        }

        $updatedRoom = $this->rooms->findById($roomId);
        $updatedSeats = $this->seats->listRoomLayout($roomId);
        $summary = $this->seats->summarizeRoomLayout($roomId);

        $this->logger->info('Room seat layout replaced', [
            'actor_id' => $actorId,
            'room_id' => $roomId,
            'total_seats' => $updatedRoom['total_seats'] ?? count($updatedSeats),
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success([
            'room' => $this->mapRoom($updatedRoom ?: []),
            'seats' => array_map([$this, 'mapSeat'], $updatedSeats),
            'summary' => $summary,
        ]);
    }

    private function validateCinemaStatusTransition(int $cinemaId, string $nextStatus): ?array
    {
        if (!in_array($nextStatus, ['closed', 'archived'], true)) {
            return null;
        }

        if ($this->cinemas->hasActiveRooms($cinemaId)) {
            $this->logBusinessRuleBlock('Cinema status transition blocked by active rooms', [
                'cinema_id' => $cinemaId,
                'next_status' => $nextStatus,
            ]);
            return $this->error(['status' => ['Cannot close or archive cinema while active rooms still exist.']], 409);
        }
        if ($this->cinemas->hasFuturePublishedShowtimes($cinemaId)) {
            $this->logBusinessRuleBlock('Cinema status transition blocked by future published showtimes', [
                'cinema_id' => $cinemaId,
                'next_status' => $nextStatus,
            ]);
            return $this->error(['status' => ['Cannot close or archive cinema while published future showtimes exist.']], 409);
        }

        return null;
    }

    private function validateRoomStatusTransition(int $roomId, string $nextStatus, bool $enforceShowtimeGuards): ?array
    {
        if (!in_array($nextStatus, ['closed', 'archived'], true)) {
            return null;
        }

        if ($enforceShowtimeGuards && $this->rooms->hasFuturePublishedShowtimes($roomId)) {
            $this->logBusinessRuleBlock('Room status transition blocked by future published showtimes', [
                'room_id' => $roomId,
                'next_status' => $nextStatus,
            ]);
            return $this->error(['status' => ['Cannot close or archive room while published future showtimes exist.']], 409);
        }

        return null;
    }

    private function mapCinema(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'slug' => $row['slug'] ?? null,
            'name' => $row['name'] ?? null,
            'city' => $row['city'] ?? null,
            'address' => $row['address'] ?? null,
            'manager_name' => $row['manager_name'] ?? null,
            'support_phone' => $row['support_phone'] ?? null,
            'status' => $row['status'] ?? null,
            'opening_time' => $row['opening_time'] ?? null,
            'closing_time' => $row['closing_time'] ?? null,
            'latitude' => isset($row['latitude']) ? (float) $row['latitude'] : null,
            'longitude' => isset($row['longitude']) ? (float) $row['longitude'] : null,
            'description' => $row['description'] ?? null,
            'room_count' => (int) ($row['room_count'] ?? 0),
            'total_seats' => (int) ($row['total_seats'] ?? 0),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function mapRoom(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'cinema_id' => (int) ($row['cinema_id'] ?? 0),
            'cinema_slug' => $row['cinema_slug'] ?? null,
            'cinema_name' => $row['cinema_name'] ?? null,
            'cinema_city' => $row['cinema_city'] ?? null,
            'cinema_status' => $row['cinema_status'] ?? null,
            'room_name' => $row['room_name'] ?? null,
            'room_type' => $row['room_type'] ?? null,
            'screen_label' => $row['screen_label'] ?? null,
            'projection_type' => $row['projection_type'] ?? null,
            'sound_profile' => $row['sound_profile'] ?? null,
            'cleaning_buffer_minutes' => (int) ($row['cleaning_buffer_minutes'] ?? 0),
            'total_seats' => (int) ($row['total_seats'] ?? 0),
            'status' => $row['status'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function mapSeat(array $row): array
    {
        $seatRow = strtoupper(trim((string) ($row['seat_row'] ?? '')));
        $seatNumber = (int) ($row['seat_number'] ?? 0);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'room_id' => (int) ($row['room_id'] ?? 0),
            'seat_row' => $seatRow,
            'seat_number' => $seatNumber,
            'label' => trim($seatRow . $seatNumber),
            'seat_type' => $row['seat_type'] ?? 'normal',
            'status' => $row['status'] ?? 'available',
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function mapCinemaOption(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'slug' => $row['slug'] ?? null,
            'name' => $row['name'] ?? null,
            'city' => $row['city'] ?? null,
            'status' => $row['status'] ?? null,
        ];
    }
    private function transactional(callable $callback)
    {
        $startedTransaction = !$this->db->inTransaction();
        if ($startedTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $result = $callback();
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }

            return $result;
        } catch (Throwable $exception) {
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
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
