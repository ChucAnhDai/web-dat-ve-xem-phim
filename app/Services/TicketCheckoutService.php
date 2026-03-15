<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\PaymentRepository;
use App\Repositories\SeatRepository;
use App\Repositories\ShowtimeRepository;
use App\Repositories\TicketOrderRepository;
use App\Repositories\TicketSeatHoldRepository;
use App\Services\Concerns\FormatsTicketData;
use App\Validators\TicketOrderValidator;
use PDO;
use Throwable;

class TicketCheckoutService
{
    use FormatsTicketData;

    private const CURRENCY = 'VND';
    private const STATUS_PAID = 'paid';
    private const PAYMENT_SUCCESS = 'success';

    private PDO $db;
    private ShowtimeRepository $showtimes;
    private SeatRepository $seats;
    private TicketSeatHoldRepository $holds;
    private TicketOrderRepository $orders;
    private PaymentRepository $payments;
    private TicketOrderValidator $validator;
    private TicketLifecycleService $lifecycle;
    private Logger $logger;

    public function __construct(
        ?PDO $db = null,
        ?ShowtimeRepository $showtimes = null,
        ?SeatRepository $seats = null,
        ?TicketSeatHoldRepository $holds = null,
        ?TicketOrderRepository $orders = null,
        ?PaymentRepository $payments = null,
        ?TicketOrderValidator $validator = null,
        ?TicketLifecycleService $lifecycle = null,
        ?Logger $logger = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->showtimes = $showtimes ?? new ShowtimeRepository($this->db);
        $this->seats = $seats ?? new SeatRepository($this->db);
        $this->holds = $holds ?? new TicketSeatHoldRepository($this->db);
        $this->orders = $orders ?? new TicketOrderRepository($this->db);
        $this->payments = $payments ?? new PaymentRepository($this->db);
        $this->validator = $validator ?? new TicketOrderValidator();
        $this->lifecycle = $lifecycle ?? new TicketLifecycleService($this->db, $this->holds, $this->orders, $this->payments, $logger);
        $this->logger = $logger ?? new Logger();
    }

    public function previewOrder(array $payload, string $sessionToken, ?int $userId = null): array
    {
        $startedAt = microtime(true);
        $validation = $this->validator->validatePreviewPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $data = $validation['data'];
        $showtimeId = (int) ($data['showtime_id'] ?? 0);

        try {
            $this->lifecycle->runMaintenance();
            $context = $this->buildCheckoutContext($showtimeId, $data['seat_ids'] ?? [], $sessionToken);
        } catch (TicketOrderDomainException $exception) {
            $this->logger->info('Ticket checkout preview blocked by business rule', [
                'showtime_id' => $showtimeId,
                'session_token' => $this->sessionTokenPreview($sessionToken),
                'user_id' => $userId,
                'errors' => $exception->errors(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error($exception->errors(), $exception->status());
        } catch (Throwable $exception) {
            $this->logger->error('Ticket checkout preview failed', [
                'showtime_id' => $showtimeId,
                'session_token' => $this->sessionTokenPreview($sessionToken),
                'user_id' => $userId,
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to load checkout preview.']], 500);
        }

        return $this->success([
            'showtime' => $this->mapShowtime($context['showtime']),
            'seats' => $context['seats'],
            'order' => [
                'seat_count' => count($context['seats']),
                'status' => self::STATUS_PAID,
                'payment_status' => self::PAYMENT_SUCCESS,
                'fulfillment_method' => $data['fulfillment_method'] ?? 'e_ticket',
                'payment_method' => $data['payment_method'] ?? 'momo',
                'hold_expires_at' => $context['hold_expires_at'],
                'subtotal_price' => $context['subtotal_price'],
                'discount_amount' => 0.0,
                'fee_amount' => 0.0,
                'total_price' => $context['total_price'],
                'currency' => self::CURRENCY,
            ],
        ]);
    }

    public function createOrder(array $payload, string $sessionToken, ?int $userId = null): array
    {
        $startedAt = microtime(true);
        $validation = $this->validator->validateCreatePayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $data = $validation['data'];
        $showtimeId = (int) ($data['showtime_id'] ?? 0);

        try {
            $this->lifecycle->runMaintenance();
            $result = $this->transactional(function () use ($data, $showtimeId, $sessionToken, $userId): array {
                $context = $this->buildCheckoutContext($showtimeId, $data['seat_ids'] ?? [], $sessionToken);
                $paidAt = date('Y-m-d H:i:s');
                $orderCode = $this->generateOrderCode();
                $orderId = $this->orders->createOrder([
                    'order_code' => $orderCode,
                    'user_id' => $userId,
                    'contact_name' => $data['contact_name'],
                    'contact_email' => $data['contact_email'],
                    'contact_phone' => $data['contact_phone'],
                    'fulfillment_method' => $data['fulfillment_method'],
                    'seat_count' => count($context['seats']),
                    'subtotal_price' => $context['subtotal_price'],
                    'discount_amount' => 0.0,
                    'fee_amount' => 0.0,
                    'total_price' => $context['total_price'],
                    'currency' => self::CURRENCY,
                    'status' => self::STATUS_PAID,
                    'hold_expires_at' => null,
                    'paid_at' => $paidAt,
                ]);

                $ticketRows = [];
                foreach ($context['seats'] as $seat) {
                    $ticketCode = $this->generateTicketCode();
                    $ticketRows[] = [
                        'order_id' => $orderId,
                        'showtime_id' => $showtimeId,
                        'seat_id' => (int) ($seat['id'] ?? 0),
                        'ticket_code' => $ticketCode,
                        'status' => self::STATUS_PAID,
                        'base_price' => $context['base_price'],
                        'surcharge_amount' => (float) ($seat['surcharge_amount'] ?? 0),
                        'discount_amount' => 0.0,
                        'price' => (float) ($seat['price'] ?? 0),
                        'qr_payload' => 'ticket:' . $ticketCode,
                    ];
                }

                $this->orders->createTicketDetails($ticketRows);

                $transactionCode = $this->generateTransactionCode();
                $this->payments->createTicketPayment([
                    'ticket_order_id' => $orderId,
                    'payment_method' => $data['payment_method'],
                    'payment_status' => self::PAYMENT_SUCCESS,
                    'transaction_code' => $transactionCode,
                ]);

                $this->holds->releaseForSessionAndShowtime($showtimeId, $sessionToken);

                return [
                    'order_id' => $orderId,
                ];
            });
        } catch (TicketOrderDomainException $exception) {
            $this->logger->info('Ticket order creation blocked by business rule', [
                'showtime_id' => $showtimeId,
                'session_token' => $this->sessionTokenPreview($sessionToken),
                'user_id' => $userId,
                'errors' => $exception->errors(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error($exception->errors(), $exception->status());
        } catch (Throwable $exception) {
            $this->logger->error('Ticket order creation failed', [
                'showtime_id' => $showtimeId,
                'session_token' => $this->sessionTokenPreview($sessionToken),
                'user_id' => $userId,
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to create ticket order.']], 500);
        }

        $header = $this->orders->findOrderHeaderById((int) $result['order_id']);
        $detailRows = $this->orders->listOrderContextRowsByOrderIds([(int) $result['order_id']]);
        $order = $this->formatOrderDetail($header ?: [], $detailRows);

        $this->logger->info('Ticket order created', [
            'order_id' => $order['id'] ?? null,
            'order_code' => $order['order_code'] ?? null,
            'showtime_id' => $showtimeId,
            'seat_ids' => array_map(static function (array $seat): int {
                return (int) ($seat['seat_id'] ?? 0);
            }, $order['tickets'] ?? []),
            'seat_count' => (int) ($order['seat_count'] ?? 0),
            'user_id' => $userId,
            'session_token' => $this->sessionTokenPreview($sessionToken),
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success([
            'order' => $order,
            'payment' => $this->formatPaymentSnapshotFromHeader($header ?: []),
            'tickets' => $order['tickets'] ?? [],
        ], 201);
    }

    private function buildCheckoutContext(int $showtimeId, array $requestedSeatIds, string $sessionToken): array
    {
        $showtime = $this->showtimes->findPublicDetail($showtimeId);
        if ($showtime === null) {
            throw new TicketOrderDomainException(['showtime' => ['Showtime not found.']], 404);
        }

        $holdRows = $this->holds->listActiveRowsForSessionAndShowtime($showtimeId, $sessionToken);
        if ($holdRows === []) {
            throw new TicketOrderDomainException(['hold' => ['Seat hold is missing or expired.']], 409);
        }

        $heldSeatIds = array_map(static function (array $row): int {
            return (int) ($row['seat_id'] ?? 0);
        }, $holdRows);
        sort($heldSeatIds);

        $requested = array_values(array_filter(array_map('intval', $requestedSeatIds), static function (int $seatId): bool {
            return $seatId > 0;
        }));
        if ($requested === []) {
            $requested = $heldSeatIds;
        }
        sort($requested);

        if ($requested !== $heldSeatIds) {
            throw new TicketOrderDomainException(['seat_ids' => ['Held seats no longer match the checkout selection.']], 409);
        }

        $seatRows = $this->seats->findRoomSeatsByIds((int) ($showtime['room_id'] ?? 0), $requested);
        if (count($seatRows) !== count($requested)) {
            throw new TicketOrderDomainException(['seat_ids' => ['One or more seats are invalid for this showtime.']], 404);
        }

        $seatLabels = [];
        foreach ($seatRows as $seatRow) {
            $seatLabels[(int) ($seatRow['id'] ?? 0)] = $this->seatLabel($seatRow);
            if (($seatRow['status'] ?? 'available') !== 'available') {
                throw new TicketOrderDomainException([
                    'seat_ids' => ['One or more seats are no longer available.'],
                ], 409);
            }
        }

        $bookedSeatIds = $this->seats->findBookedSeatIdsForShowtime($showtimeId, $requested);
        if ($bookedSeatIds !== []) {
            $messages = array_map(function (int $seatId) use ($seatLabels): string {
                $label = $seatLabels[$seatId] ?? ('Seat #' . $seatId);

                return "Seat {$label} has already been booked.";
            }, $bookedSeatIds);

            throw new TicketOrderDomainException(['seat_ids' => $messages], 409);
        }

        $holdLookup = [];
        foreach ($holdRows as $holdRow) {
            $holdLookup[(int) ($holdRow['seat_id'] ?? 0)] = $holdRow['hold_expires_at'] ?? null;
        }

        $basePrice = isset($showtime['price']) ? (float) $showtime['price'] : 0.0;
        $subtotalPrice = round($basePrice * count($seatRows), 2);
        $surchargeTotal = 0.0;
        $mappedSeats = [];

        foreach ($seatRows as $seatRow) {
            $surchargeAmount = $this->seatSurcharge((string) ($seatRow['seat_type'] ?? 'normal'));
            $price = round($basePrice + $surchargeAmount, 2);
            $surchargeTotal += $surchargeAmount;
            $mappedSeats[] = [
                'id' => (int) ($seatRow['id'] ?? 0),
                'label' => $this->seatLabel($seatRow),
                'type' => $seatRow['seat_type'] ?? 'normal',
                'status' => $seatRow['status'] ?? 'available',
                'base_price' => $basePrice,
                'surcharge_amount' => $surchargeAmount,
                'price' => $price,
                'hold_expires_at' => $holdLookup[(int) ($seatRow['id'] ?? 0)] ?? null,
            ];
        }

        $holdExpiresAt = null;
        foreach ($mappedSeats as $seat) {
            $candidate = trim((string) ($seat['hold_expires_at'] ?? ''));
            if ($candidate === '') {
                continue;
            }
            if ($holdExpiresAt === null || strcmp($candidate, $holdExpiresAt) < 0) {
                $holdExpiresAt = $candidate;
            }
        }

        return [
            'showtime' => $showtime,
            'seats' => $mappedSeats,
            'base_price' => $basePrice,
            'subtotal_price' => $subtotalPrice,
            'surcharge_total' => round($surchargeTotal, 2),
            'total_price' => round($subtotalPrice + $surchargeTotal, 2),
            'hold_expires_at' => $holdExpiresAt,
        ];
    }

    private function mapShowtime(array $row): array
    {
        $totalSeats = isset($row['total_seats']) ? (int) $row['total_seats'] : 0;
        $bookedSeats = isset($row['booked_seats']) ? (int) $row['booked_seats'] : 0;
        $heldSeats = isset($row['held_seats']) ? (int) $row['held_seats'] : 0;
        $availableSeats = max(0, $totalSeats - $bookedSeats - $heldSeats);

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
            'status' => $row['status'] ?? null,
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
        ];
    }

    private function seatSurcharge(string $seatType): float
    {
        $normalized = strtolower(trim($seatType));
        if ($normalized === 'vip') {
            return 15000.0;
        }
        if ($normalized === 'couple') {
            return 30000.0;
        }

        return 0.0;
    }

    private function generateOrderCode(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = 'TKT-' . strtoupper(bin2hex(random_bytes(5)));
            if (!$this->orders->orderCodeExists($code)) {
                return $code;
            }
        }

        throw new \RuntimeException('Failed to generate unique order code.');
    }

    private function generateTicketCode(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = 'TIC-' . strtoupper(bin2hex(random_bytes(6)));
            if (!$this->orders->ticketCodeExists($code)) {
                return $code;
            }
        }

        throw new \RuntimeException('Failed to generate unique ticket code.');
    }

    private function generateTransactionCode(): string
    {
        return 'PAY-' . strtoupper(bin2hex(random_bytes(6)));
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

    private function sessionTokenPreview(string $sessionToken): string
    {
        return substr($sessionToken, 0, 12);
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

class TicketOrderDomainException extends \RuntimeException
{
    private array $errors;
    private int $status;

    public function __construct(array $errors, int $status)
    {
        parent::__construct('Ticket order domain exception.');
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
