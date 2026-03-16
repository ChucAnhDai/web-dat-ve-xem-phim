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
use App\Support\VnpayGateway;
use App\Validators\TicketOrderValidator;
use PDO;
use Throwable;

class TicketCheckoutService
{
    use FormatsTicketData;

    private const CURRENCY = 'VND';
    private const STATUS_PAID = 'paid';
    private const STATUS_PENDING = 'pending';
    private const PAYMENT_SUCCESS = 'success';
    private const PAYMENT_PENDING = 'pending';
    private const VNPAY_METHOD = 'vnpay';

    private PDO $db;
    private TicketSeatHoldRepository $holds;
    private TicketOrderRepository $orders;
    private PaymentRepository $payments;
    private TicketOrderValidator $validator;
    private TicketLifecycleService $lifecycle;
    private TicketCheckoutContextService $context;
    private Logger $logger;
    private VnpayGateway $gateway;

    public function __construct(
        ?PDO $db = null,
        ?ShowtimeRepository $showtimes = null,
        ?SeatRepository $seats = null,
        ?TicketSeatHoldRepository $holds = null,
        ?TicketOrderRepository $orders = null,
        ?PaymentRepository $payments = null,
        ?TicketOrderValidator $validator = null,
        ?TicketLifecycleService $lifecycle = null,
        ?TicketCheckoutContextService $context = null,
        ?Logger $logger = null,
        ?VnpayGateway $gateway = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->holds = $holds ?? new TicketSeatHoldRepository($this->db);
        $this->orders = $orders ?? new TicketOrderRepository($this->db);
        $this->payments = $payments ?? new PaymentRepository($this->db);
        $this->validator = $validator ?? new TicketOrderValidator();
        $this->lifecycle = $lifecycle ?? new TicketLifecycleService($this->db, $this->holds, $this->orders, $this->payments, $logger);
        $this->context = $context ?? new TicketCheckoutContextService(
            $this->db,
            $showtimes ?? new ShowtimeRepository($this->db),
            $seats ?? new SeatRepository($this->db),
            $this->holds
        );
        $this->logger = $logger ?? new Logger();
        $this->gateway = $gateway ?? new VnpayGateway();
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
            $context = $this->context->buildContext($showtimeId, $data['seat_ids'] ?? [], $sessionToken);
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
            'showtime' => $context['showtime_summary'],
            'seats' => $context['seats'],
            'order' => [
                'seat_count' => count($context['seats']),
                'status' => $data['payment_method'] === self::VNPAY_METHOD ? self::STATUS_PENDING : self::STATUS_PAID,
                'payment_status' => $data['payment_method'] === self::VNPAY_METHOD ? self::PAYMENT_PENDING : self::PAYMENT_SUCCESS,
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
        if (($data['payment_method'] ?? '') === self::VNPAY_METHOD) {
            return $this->error([
                'payment_method' => ['VNPay checkout must be started from the payment intent endpoint.'],
            ], 409);
        }

        try {
            $this->lifecycle->runMaintenance();
            $result = $this->transactional(function () use ($data, $showtimeId, $sessionToken, $userId): array {
                $context = $this->context->buildContext($showtimeId, $data['seat_ids'] ?? [], $sessionToken);
                $paidAt = date('Y-m-d H:i:s');
                $orderCode = $this->generateOrderCode();
                $orderId = $this->orders->createOrder([
                    'order_code' => $orderCode,
                    'user_id' => $userId,
                    'session_token' => $sessionToken,
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
                    'amount' => $context['total_price'],
                    'currency' => self::CURRENCY,
                    'transaction_code' => $transactionCode,
                    'provider_order_ref' => $orderCode,
                    'provider_message' => 'Ticket payment snapshot recorded from checkout.',
                    'idempotency_key' => 'ticket-order:' . $orderCode,
                    'initiated_at' => $paidAt,
                    'completed_at' => $paidAt,
                    'payment_date' => $paidAt,
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

    public function activeCheckout(?string $sessionToken, ?int $userId = null): array
    {
        $startedAt = microtime(true);

        try {
            $this->lifecycle->runMaintenance();
            $header = $this->findActiveCheckoutHeader($sessionToken, $userId);
            if ($header === null) {
                return $this->success([
                    'resume_available' => false,
                ]);
            }

            $detailRows = $this->orders->listOrderContextRowsByOrderIds([(int) ($header['id'] ?? 0)]);
            $order = $this->formatOrderDetail($header, $detailRows);
            $payment = $this->payments->findLatestTicketPaymentByOrderId((int) ($header['id'] ?? 0)) ?: [];
            $resume = $this->buildResumePayload($order, $detailRows, $payment);

            $this->logger->info('Active checkout resolved', [
                'order_id' => $order['id'] ?? null,
                'order_code' => $order['order_code'] ?? null,
                'user_id' => $userId,
                'has_session_token' => is_string($sessionToken) && trim($sessionToken) !== '',
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->success($resume);
        } catch (Throwable $exception) {
            $this->logger->error('Active checkout lookup failed', [
                'user_id' => $userId,
                'has_session_token' => is_string($sessionToken) && trim($sessionToken) !== '',
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to load the active checkout.']], 500);
        }
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

    private function findActiveCheckoutHeader(?string $sessionToken, ?int $userId): ?array
    {
        $normalizedSessionToken = trim((string) ($sessionToken ?? ''));
        if ($normalizedSessionToken !== '') {
            $sessionOrder = $this->orders->findActivePendingOrderBySession($normalizedSessionToken);
            if ($sessionOrder !== null) {
                return $this->orders->findOrderHeaderById((int) ($sessionOrder['id'] ?? 0));
            }
        }

        if ($userId !== null && $userId > 0) {
            $userOrder = $this->orders->findActivePendingOrderByUser($userId);
            if ($userOrder !== null) {
                return $this->orders->findOrderHeaderById((int) ($userOrder['id'] ?? 0));
            }
        }

        return null;
    }

    private function buildResumePayload(array $order, array $detailRows, array $payment): array
    {
        $redirectUrl = $this->resolveResumeRedirectUrl($payment);
        $showtime = [
            'id' => (int) ($order['showtime_id'] ?? 0),
            'movie_id' => (int) ($order['movie_id'] ?? 0),
            'movie_slug' => $order['movie_slug'] ?? null,
            'movie_title' => $order['movie_title'] ?? null,
            'poster_url' => $order['poster_url'] ?? null,
            'show_date' => $order['show_date'] ?? null,
            'start_time' => $order['start_time'] ?? null,
            'end_time' => $order['end_time'] ?? null,
            'price' => isset($detailRows[0]['base_price']) ? (float) $detailRows[0]['base_price'] : 0.0,
            'status' => $order['status'] ?? self::STATUS_PENDING,
            'presentation_type' => $order['presentation_type'] ?? null,
            'language_version' => $order['language_version'] ?? null,
            'cinema_name' => $order['cinema_name'] ?? null,
            'cinema_city' => $order['cinema_city'] ?? null,
            'room_name' => $order['room_name'] ?? null,
        ];

        $seats = array_map(function (array $row): array {
            return [
                'id' => (int) ($row['seat_id'] ?? 0),
                'label' => $this->seatLabel($row),
                'type' => $row['seat_type'] ?? 'normal',
                'status' => $row['ticket_status'] ?? self::STATUS_PENDING,
                'base_price' => isset($row['base_price']) ? (float) $row['base_price'] : 0.0,
                'surcharge_amount' => isset($row['surcharge_amount']) ? (float) $row['surcharge_amount'] : 0.0,
                'price' => isset($row['price']) ? (float) $row['price'] : 0.0,
            ];
        }, $detailRows);

        $paymentSnapshot = [
            'id' => isset($payment['id']) ? (int) $payment['id'] : null,
            'payment_method' => $payment['payment_method'] ?? ($order['payment_method'] ?? null),
            'payment_status' => $payment['payment_status'] ?? ($order['payment_status'] ?? null),
            'amount' => isset($payment['amount']) ? (float) $payment['amount'] : (float) ($order['total_price'] ?? 0),
            'currency' => $payment['currency'] ?? ($order['currency'] ?? self::CURRENCY),
            'transaction_code' => $payment['transaction_code'] ?? ($order['transaction_code'] ?? null),
            'checkout_url' => $redirectUrl,
        ];

        $resumeTarget = ($paymentSnapshot['payment_method'] ?? null) === self::VNPAY_METHOD
            && ($paymentSnapshot['payment_status'] ?? null) === self::PAYMENT_PENDING
            && trim((string) ($redirectUrl ?? '')) !== ''
            ? 'payment'
            : 'order';

        return [
            'resume_available' => true,
            'showtime' => $showtime,
            'seats' => $seats,
            'order' => $order,
            'payment' => $paymentSnapshot,
            'resume_target' => $resumeTarget,
            'redirect_url' => $resumeTarget === 'payment' ? $redirectUrl : null,
            'resume_expires_at' => $order['hold_expires_at'] ?? null,
        ];
    }

    private function resolveResumeRedirectUrl(array $payment): ?string
    {
        $method = strtolower(trim((string) ($payment['payment_method'] ?? '')));
        $status = strtolower(trim((string) ($payment['payment_status'] ?? '')));
        if ($method !== self::VNPAY_METHOD || $status !== self::PAYMENT_PENDING) {
            return null;
        }

        $requestPayload = $this->decodeGatewayRequestPayload($payment['request_payload'] ?? null);
        if ($requestPayload !== null && $this->gateway->isConfigured()) {
            $rebuilt = $this->gateway->buildCheckoutUrl($requestPayload);
            $checkoutUrl = (string) ($rebuilt['checkout_url'] ?? '');
            if ($checkoutUrl !== '') {
                $this->repairStoredCheckoutUrl($payment, $checkoutUrl);

                return $checkoutUrl;
            }
        }

        $stored = trim((string) ($payment['checkout_url'] ?? ''));

        return $stored !== '' ? $stored : null;
    }

    private function decodeGatewayRequestPayload($requestPayload): ?array
    {
        if (!is_string($requestPayload) || trim($requestPayload) === '') {
            return null;
        }

        $decoded = json_decode($requestPayload, true);
        if (!is_array($decoded) || $decoded === []) {
            return null;
        }

        $payload = [];
        foreach ($decoded as $key => $value) {
            if (strpos((string) $key, 'vnp_') !== 0) {
                continue;
            }
            $payload[(string) $key] = (string) $value;
        }

        return $payload !== [] ? $payload : null;
    }

    private function repairStoredCheckoutUrl(array $payment, string $checkoutUrl): void
    {
        $paymentId = (int) ($payment['id'] ?? 0);
        if ($paymentId <= 0) {
            return;
        }

        $stored = trim((string) ($payment['checkout_url'] ?? ''));
        if ($stored === $checkoutUrl) {
            return;
        }

        try {
            $this->payments->updateCheckoutUrl($paymentId, $checkoutUrl);
            $this->logger->info('Pending VNPay checkout URL repaired', [
                'payment_id' => $paymentId,
            ]);
        } catch (Throwable $exception) {
            $this->logger->info('Pending VNPay checkout URL repair skipped', [
                'payment_id' => $paymentId,
                'error' => $exception->getMessage(),
            ]);
        }
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
