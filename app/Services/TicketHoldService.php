<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\SeatRepository;
use App\Repositories\ShowtimeRepository;
use App\Repositories\TicketOrderRepository;
use App\Repositories\TicketSeatHoldRepository;
use App\Validators\TicketHoldValidator;
use PDO;
use PDOException;
use Throwable;

class TicketHoldService
{
    private PDO $db;
    private ShowtimeRepository $showtimes;
    private SeatRepository $seats;
    private TicketSeatHoldRepository $holds;
    private TicketOrderRepository $orders;
    private TicketHoldValidator $validator;
    private Logger $logger;
    private TicketLifecycleService $lifecycle;
    private array $config;

    public function __construct(
        ?PDO $db = null,
        ?ShowtimeRepository $showtimes = null,
        ?SeatRepository $seats = null,
        ?TicketSeatHoldRepository $holds = null,
        ?TicketHoldValidator $validator = null,
        ?Logger $logger = null,
        ?TicketOrderRepository $orders = null,
        ?TicketLifecycleService $lifecycle = null,
        ?array $config = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->showtimes = $showtimes ?? new ShowtimeRepository($this->db);
        $this->seats = $seats ?? new SeatRepository($this->db);
        $this->holds = $holds ?? new TicketSeatHoldRepository($this->db);
        $this->orders = $orders ?? new TicketOrderRepository($this->db);
        $this->config = $config ?? require dirname(__DIR__, 2) . '/config/tickets.php';
        $this->validator = $validator ?? new TicketHoldValidator($this->config);
        $this->logger = $logger ?? new Logger();
        $this->lifecycle = $lifecycle ?? new TicketLifecycleService($this->db, $this->holds, $this->orders, null, $this->logger);
    }

    public function createHold(array $payload, string $sessionToken, ?int $userId = null): array
    {
        $startedAt = microtime(true);
        $validation = $this->validator->validateCreatePayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $data = $validation['data'];
        $showtimeId = (int) ($data['showtime_id'] ?? 0);
        $seatIds = $data['seat_ids'] ?? [];

        $showtime = $this->showtimes->findPublicDetail($showtimeId);
        if ($showtime === null) {
            return $this->error(['showtime' => ['Showtime not found.']], 404);
        }

        try {
            $this->lifecycle->runMaintenance();
            $blockingOrder = $this->findBlockingPendingOrder($sessionToken, $userId);
            if ($blockingOrder !== null) {
                throw new TicketHoldDomainException([
                    'checkout' => [$this->pendingOrderMessage($blockingOrder)],
                ], 409);
            }

            $holdExpiresAt = $this->transactional(function () use ($showtime, $showtimeId, $seatIds, $sessionToken, $userId): string {
                $seatRows = $this->seats->findRoomSeatsByIds((int) ($showtime['room_id'] ?? 0), $seatIds);
                if (count($seatRows) !== count($seatIds)) {
                    throw new TicketHoldDomainException(['seat_ids' => ['One or more seats are invalid for this showtime.']], 404);
                }

                $seatLabels = [];
                foreach ($seatRows as $seatRow) {
                    $status = (string) ($seatRow['status'] ?? 'available');
                    $label = $this->seatLabel($seatRow);
                    $seatLabels[(int) $seatRow['id']] = $label;

                    if ($status !== 'available') {
                        throw new TicketHoldDomainException([
                            'seat_ids' => ["Seat {$label} is not available for booking."],
                        ], 409);
                    }
                }

                $bookedSeatIds = $this->seats->findBookedSeatIdsForShowtime($showtimeId, $seatIds);
                if ($bookedSeatIds !== []) {
                    $messages = array_map(function (int $seatId) use ($seatLabels): string {
                        $label = $seatLabels[$seatId] ?? ('Seat #' . $seatId);

                        return "Seat {$label} has already been booked.";
                    }, $bookedSeatIds);

                    throw new TicketHoldDomainException(['seat_ids' => $messages], 409);
                }

                $conflicts = $this->holds->findActiveConflicts($showtimeId, $seatIds, $sessionToken);
                if ($conflicts !== []) {
                    $messages = array_map(function (array $row) use ($seatLabels): string {
                        $seatId = (int) ($row['seat_id'] ?? 0);
                        $label = $seatLabels[$seatId] ?? ('Seat #' . $seatId);

                        return "Seat {$label} is temporarily held by another customer.";
                    }, $conflicts);

                    throw new TicketHoldDomainException(['seat_ids' => $messages], 409);
                }

                $this->holds->releaseForSession($sessionToken);

                $holdExpiresAt = date('Y-m-d H:i:s', strtotime('+' . $this->holdTtlMinutes() . ' minutes'));
                foreach ($seatIds as $seatId) {
                    $this->holds->createHold($showtimeId, (int) $seatId, $userId, $sessionToken, $holdExpiresAt);
                }

                return $holdExpiresAt;
            });
        } catch (TicketHoldDomainException $exception) {
            $this->logger->info('Ticket hold blocked by business rule', [
                'showtime_id' => $showtimeId,
                'session_token' => $this->sessionTokenPreview($sessionToken),
                'user_id' => $userId,
                'errors' => $exception->errors(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error($exception->errors(), $exception->status());
        } catch (Throwable $exception) {
            $status = $this->isUniqueConstraintViolation($exception) ? 409 : 500;
            $errors = $status === 409
                ? ['seat_ids' => ['One or more seats were just held by another customer.']]
                : ['server' => ['Failed to hold seats.']];

            $this->logger->error('Ticket hold creation failed', [
                'showtime_id' => $showtimeId,
                'session_token' => $this->sessionTokenPreview($sessionToken),
                'user_id' => $userId,
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error($errors, $status);
        }

        $heldSeats = $this->seats->findRoomSeatsByIds((int) ($showtime['room_id'] ?? 0), $seatIds);
        $this->logger->info('Ticket seats held', [
            'showtime_id' => $showtimeId,
            'seat_ids' => $seatIds,
            'seat_count' => count($seatIds),
            'session_token' => $this->sessionTokenPreview($sessionToken),
            'user_id' => $userId,
            'hold_expires_at' => $holdExpiresAt,
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success([
            'showtime_id' => $showtimeId,
            'seat_ids' => array_values($seatIds),
            'seat_labels' => array_values(array_map([$this, 'seatLabel'], $heldSeats)),
            'seat_count' => count($seatIds),
            'hold_expires_at' => $holdExpiresAt,
            'ttl_minutes' => $this->holdTtlMinutes(),
        ]);
    }

    public function releaseHold(int $showtimeId, string $sessionToken, ?int $userId = null): array
    {
        $startedAt = microtime(true);
        $validation = $this->validator->validateReleaseShowtimeId($showtimeId);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 404);
        }

        try {
            $this->holds->purgeExpired();
            $releasedCount = $this->holds->releaseForSessionAndShowtime($showtimeId, $sessionToken);
        } catch (Throwable $exception) {
            $this->logger->error('Ticket hold release failed', [
                'showtime_id' => $showtimeId,
                'session_token' => $this->sessionTokenPreview($sessionToken),
                'user_id' => $userId,
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to release held seats.']], 500);
        }

        $this->logger->info('Ticket seats released', [
            'showtime_id' => $showtimeId,
            'released_count' => $releasedCount,
            'session_token' => $this->sessionTokenPreview($sessionToken),
            'user_id' => $userId,
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success([
            'showtime_id' => $showtimeId,
            'released_count' => $releasedCount,
        ]);
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

    private function isUniqueConstraintViolation(Throwable $exception): bool
    {
        if (!$exception instanceof PDOException) {
            return false;
        }

        return ($exception->getCode() ?? '') === '23000';
    }

    private function seatLabel(array $row): string
    {
        return strtoupper(trim((string) ($row['seat_row'] ?? ''))) . (int) ($row['seat_number'] ?? 0);
    }

    private function sessionTokenPreview(string $sessionToken): string
    {
        return substr($sessionToken, 0, 12);
    }

    private function holdTtlMinutes(): int
    {
        return max(1, (int) ($this->config['hold_ttl_minutes'] ?? 5));
    }

    private function findBlockingPendingOrder(string $sessionToken, ?int $userId): ?array
    {
        $bySession = $this->orders->findActivePendingOrderBySession($sessionToken);
        if ($bySession !== null) {
            return $bySession;
        }

        if ($userId !== null && $userId > 0) {
            return $this->orders->findActivePendingOrderByUser($userId);
        }

        return null;
    }

    private function pendingOrderMessage(array $order): string
    {
        $orderCode = trim((string) ($order['order_code'] ?? ''));
        $expiry = trim((string) ($order['hold_expires_at'] ?? ''));
        $suffix = $expiry !== '' ? ' It will be released at ' . $expiry . '.' : '';

        if ($orderCode !== '') {
            return 'You already have a checkout waiting for payment (' . $orderCode . ').' . $suffix;
        }

        return 'You already have a checkout waiting for payment.' . $suffix;
    }

    private function durationMs(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 2);
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

class TicketHoldDomainException extends \RuntimeException
{
    private array $errors;
    private int $status;

    public function __construct(array $errors, int $status)
    {
        parent::__construct('Ticket hold domain exception.');
        $this->errors = $errors;
        $this->status = $status;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function status(): int
    {
        return $this->status;
    }
}
