<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ShopOrderRepository;
use App\Repositories\TicketOrderRepository;
use App\Repositories\TicketSeatHoldRepository;
use App\Services\Concerns\FormatsTicketData;
use App\Support\VnpayGateway;
use App\Validators\PaymentValidator;
use PDO;
use Throwable;

class PaymentService
{
    use FormatsTicketData;

    private const CURRENCY = 'VND';

    private PDO $db;
    private TicketCheckoutContextService $context;
    private TicketSeatHoldRepository $holds;
    private TicketOrderRepository $orders;
    private ShopOrderRepository $shopOrders;
    private PaymentRepository $payments;
    private PaymentMethodRepository $methods;
    private PaymentValidator $validator;
    private TicketLifecycleService $lifecycle;
    private ShopOrderLifecycleService $shopLifecycle;
    private VnpayGateway $gateway;
    private array $config;
    private array $ticketConfig;
    private Logger $logger;

    public function __construct(
        ?PDO $db = null,
        ?TicketCheckoutContextService $context = null,
        ?TicketSeatHoldRepository $holds = null,
        ?TicketOrderRepository $orders = null,
        ?PaymentRepository $payments = null,
        ?PaymentMethodRepository $methods = null,
        ?PaymentValidator $validator = null,
        ?TicketLifecycleService $lifecycle = null,
        ?VnpayGateway $gateway = null,
        ?array $config = null,
        ?Logger $logger = null,
        ?array $ticketConfig = null,
        ?ShopOrderRepository $shopOrders = null,
        ?ShopOrderLifecycleService $shopLifecycle = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->holds = $holds ?? new TicketSeatHoldRepository($this->db);
        $this->orders = $orders ?? new TicketOrderRepository($this->db);
        $this->shopOrders = $shopOrders ?? new ShopOrderRepository($this->db);
        $this->payments = $payments ?? new PaymentRepository($this->db);
        $this->methods = $methods ?? new PaymentMethodRepository($this->db);
        $this->validator = $validator ?? new PaymentValidator();
        $this->lifecycle = $lifecycle ?? new TicketLifecycleService($this->db, $this->holds, $this->orders, $this->payments, $logger);
        $this->shopLifecycle = $shopLifecycle ?? new ShopOrderLifecycleService($this->db, $this->shopOrders, null, null, $this->payments, $logger);
        $this->context = $context ?? new TicketCheckoutContextService($this->db, null, null, $this->holds);
        $this->config = $config ?? require dirname(__DIR__, 2) . '/config/payments.php';
        $this->ticketConfig = $ticketConfig ?? require dirname(__DIR__, 2) . '/config/tickets.php';
        $this->gateway = $gateway ?? new VnpayGateway($this->config);
        $this->logger = $logger ?? new Logger();
    }

    public function createTicketVnpayIntent(array $payload, string $sessionToken, ?int $userId, array $requestContext): array
    {
        $startedAt = microtime(true);
        $validation = $this->validator->validateTicketIntentPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $data = $validation['data'];
        $showtimeId = (int) ($data['showtime_id'] ?? 0);

        try {
            $this->assertVnpayReady();
            $this->lifecycle->runMaintenance();
            $this->assertPendingCheckoutCapacity($sessionToken, $userId);
            $baseUrl = $this->resolveBaseUrl($requestContext);
            $result = $this->transactional(function () use ($data, $sessionToken, $userId, $requestContext, $baseUrl, $showtimeId): array {
                $context = $this->context->buildContext($showtimeId, $data['seat_ids'] ?? [], $sessionToken);
                $createdAt = date('Y-m-d H:i:s');
                $holdExpiresAt = $this->resolvePendingExpiry($createdAt);
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
                    'status' => 'pending',
                    'hold_expires_at' => $holdExpiresAt,
                    'paid_at' => null,
                ]);

                $ticketRows = [];
                foreach ($context['seats'] as $seat) {
                    $ticketCode = $this->generateTicketCode();
                    $ticketRows[] = [
                        'order_id' => $orderId,
                        'showtime_id' => $showtimeId,
                        'seat_id' => (int) ($seat['id'] ?? 0),
                        'ticket_code' => $ticketCode,
                        'status' => 'pending',
                        'base_price' => $context['base_price'],
                        'surcharge_amount' => (float) ($seat['surcharge_amount'] ?? 0),
                        'discount_amount' => 0.0,
                        'price' => (float) ($seat['price'] ?? 0),
                        'qr_payload' => 'ticket:' . $ticketCode,
                    ];
                }
                $this->orders->createTicketDetails($ticketRows);

                $transactionCode = $this->generateTransactionCode();
                $paymentId = $this->payments->createTicketPayment([
                    'ticket_order_id' => $orderId,
                    'payment_method' => 'vnpay',
                    'payment_status' => 'pending',
                    'amount' => $context['total_price'],
                    'currency' => self::CURRENCY,
                    'transaction_code' => $transactionCode,
                    'provider_order_ref' => $orderCode,
                    'provider_message' => 'Pending redirect to VNPay.',
                    'idempotency_key' => 'vnpay:' . $orderCode,
                    'initiated_at' => $createdAt,
                    'payment_date' => $createdAt,
                ]);

                $checkout = $this->gateway->buildCheckoutUrl($this->buildVnpayPayload(
                    $orderCode,
                    $context['total_price'],
                    $data['bank_code'] ?? null,
                    $requestContext,
                    $baseUrl,
                    $holdExpiresAt
                ));

                $this->payments->updateGatewayCheckout($paymentId, [
                    'checkout_url' => $checkout['checkout_url'],
                    'request_payload' => json_encode($checkout['query'], JSON_UNESCAPED_UNICODE),
                    'provider_order_ref' => $orderCode,
                    'idempotency_key' => 'vnpay:' . $orderCode,
                ]);

                $this->holds->releaseForSessionAndShowtime($showtimeId, $sessionToken);

                return [
                    'order_id' => $orderId,
                    'payment_id' => $paymentId,
                    'redirect_url' => $checkout['checkout_url'],
                    'redirect_expires_at' => $holdExpiresAt,
                ];
            });
        } catch (TicketOrderDomainException $exception) {
            $this->logger->info('VNPay intent blocked by checkout rule', [
                'showtime_id' => $showtimeId,
                'user_id' => $userId,
                'errors' => $exception->errors(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error($exception->errors(), $exception->status());
        } catch (PaymentDomainException $exception) {
            $this->logger->info('VNPay intent blocked by payment rule', [
                'showtime_id' => $showtimeId,
                'user_id' => $userId,
                'errors' => $exception->errors(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error($exception->errors(), $exception->status());
        } catch (Throwable $exception) {
            $this->logger->error('VNPay intent creation failed', [
                'showtime_id' => $showtimeId,
                'user_id' => $userId,
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to create VNPay payment intent.']], 500);
        }

        $header = $this->orders->findOrderHeaderById((int) $result['order_id']);
        $detailRows = $this->orders->listOrderContextRowsByOrderIds([(int) $result['order_id']]);
        $order = $this->formatOrderDetail($header ?: [], $detailRows);
        $payment = $this->payments->findLatestTicketPaymentByOrderId((int) $result['order_id']) ?: [];

        $this->logger->info('VNPay intent created', [
            'order_id' => $order['id'] ?? null,
            'order_code' => $order['order_code'] ?? null,
            'payment_id' => $payment['id'] ?? null,
            'payment_status' => $payment['payment_status'] ?? null,
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success([
            'order' => $order,
            'payment' => $this->formatPayment($payment),
            'redirect_url' => $result['redirect_url'],
            'redirect_expires_at' => $result['redirect_expires_at'],
        ], 201);
    }

    public function handleVnpayReturn(array $payload): array
    {
        return $this->handleVnpayGatewayResponse($payload, 'return');
    }

    public function handleVnpayIpn(array $payload): array
    {
        return $this->handleVnpayGatewayResponse($payload, 'ipn');
    }

    private function handleVnpayGatewayResponse(array $payload, string $source): array
    {
        $startedAt = microtime(true);
        $validation = $this->validator->validateVnpayCallbackPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->paymentError(
                $validation['errors'],
                422,
                $source,
                '97',
                'Invalid Signature'
            );
        }

        $data = $validation['data'];

        try {
            $this->assertVnpayReady();
            if (!$this->gateway->validateSignature($payload)) {
                throw new PaymentDomainException(
                    ['signature' => ['VNPay secure hash is invalid.']],
                    422,
                    '97',
                    'Invalid Signature'
                );
            }

            $this->lifecycle->runMaintenance();
            $this->shopLifecycle->runMaintenance();
            $payment = $this->payments->findTicketPaymentByProviderOrderRef((string) $data['provider_order_ref'], 'vnpay');
            $paymentTarget = 'ticket';
            if ($payment === null) {
                $payment = $this->payments->findShopPaymentByProviderOrderRef((string) $data['provider_order_ref'], 'vnpay');
                $paymentTarget = 'shop';
            }
            if ($payment === null) {
                throw new PaymentDomainException(
                    ['payment' => ['Payment reference was not found.']],
                    404,
                    '01',
                    'Order not Found'
                );
            }

            $expectedAmount = (int) round(((float) ($payment['amount'] ?? 0)) * 100);
            if ($expectedAmount !== (int) ($data['amount'] ?? 0)) {
                throw new PaymentDomainException(
                    ['amount' => ['Payment amount does not match the order total.']],
                    409,
                    '04',
                    'Invalid Amount'
                );
            }

            $currentStatus = strtolower(trim((string) ($payment['payment_status'] ?? 'pending')));
            if ($currentStatus === 'success') {
                return $this->paymentSuccessResponse($payment, $source, '02', 'Order already confirmed');
            }
            if (in_array($currentStatus, ['failed', 'cancelled', 'expired', 'refunded'], true)) {
                return $this->paymentSuccessResponse($payment, $source, '02', 'Order already confirmed');
            }

            $resolved = $this->transactional(function () use ($payment, $data, $paymentTarget): array {
                $paymentId = (int) ($payment['id'] ?? 0);
                $now = date('Y-m-d H:i:s');
                $callbackPayload = json_encode($data['raw_payload'] ?? [], JSON_UNESCAPED_UNICODE);

                if ($paymentTarget === 'shop') {
                    $orderId = (int) ($payment['shop_order_id'] ?? 0);
                    if ($this->validator->isSuccessfulVnpayResponse($data)) {
                        $this->payments->markPaymentSuccess($paymentId, [
                            'payment_status' => 'success',
                            'provider_transaction_code' => $data['provider_transaction_code'] ?? null,
                            'provider_response_code' => $data['response_code'] ?? null,
                            'provider_message' => 'VNPay payment confirmed.',
                            'callback_payload' => $callbackPayload,
                            'completed_at' => $now,
                            'payment_date' => $now,
                        ]);
                        $this->shopOrders->markOrdersConfirmed([$orderId], $now);
                    } else {
                        $issue = $this->mapFailureState((string) ($data['response_code'] ?? ''));
                        $this->payments->markPaymentIssue($paymentId, [
                            'payment_status' => $issue['payment_status'],
                            'provider_transaction_code' => $data['provider_transaction_code'] ?? null,
                            'provider_response_code' => $data['response_code'] ?? null,
                            'provider_message' => $issue['message'],
                            'callback_payload' => $callbackPayload,
                            'failed_at' => $now,
                        ]);
                        $this->shopLifecycle->releaseInventoryAndMarkIssue($orderId, $issue['order_status'], $now);
                    }

                    return $this->payments->findShopPaymentByProviderOrderRef((string) ($payment['provider_order_ref'] ?? ''), 'vnpay') ?: $payment;
                }

                $orderId = (int) ($payment['ticket_order_id'] ?? 0);
                if ($this->validator->isSuccessfulVnpayResponse($data)) {
                    $this->payments->markPaymentSuccess($paymentId, [
                        'payment_status' => 'success',
                        'provider_transaction_code' => $data['provider_transaction_code'] ?? null,
                        'provider_response_code' => $data['response_code'] ?? null,
                        'provider_message' => 'VNPay payment confirmed.',
                        'callback_payload' => $callbackPayload,
                        'completed_at' => $now,
                        'payment_date' => $now,
                    ]);
                    $this->orders->markOrdersPaid([$orderId], $now);
                    $this->orders->markTicketDetailsStatusForOrderIds([$orderId], 'paid');
                } else {
                    $issue = $this->mapFailureState((string) ($data['response_code'] ?? ''));
                    $this->payments->markPaymentIssue($paymentId, [
                        'payment_status' => $issue['payment_status'],
                        'provider_transaction_code' => $data['provider_transaction_code'] ?? null,
                        'provider_response_code' => $data['response_code'] ?? null,
                        'provider_message' => $issue['message'],
                        'callback_payload' => $callbackPayload,
                        'failed_at' => $now,
                    ]);
                    $this->orders->markOrdersIssue([$orderId], $issue['order_status'], $now);
                    $this->orders->markTicketDetailsStatusForOrderIds([$orderId], $issue['ticket_status']);
                }

                return $this->payments->findTicketPaymentByProviderOrderRef((string) ($payment['provider_order_ref'] ?? ''), 'vnpay') ?: $payment;
            });
        } catch (PaymentDomainException $exception) {
            $this->logger->info('VNPay callback rejected', [
                'source' => $source,
                'errors' => $exception->errors(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->paymentError(
                $exception->errors(),
                $exception->status(),
                $source,
                $exception->ipnCode(),
                $exception->ipnMessage()
            );
        } catch (Throwable $exception) {
            $this->logger->error('VNPay callback failed', [
                'source' => $source,
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->paymentError(
                ['server' => ['Failed to process VNPay callback.']],
                500,
                $source,
                '99',
                'Unknown error'
            );
        }

        $this->logger->info('VNPay callback processed', [
            'source' => $source,
            'provider_order_ref' => $data['provider_order_ref'] ?? null,
            'payment_status' => $resolved['payment_status'] ?? null,
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->paymentSuccessResponse($resolved, $source, '00', 'Confirm Success');
    }

    private function assertVnpayReady(): void
    {
        $vnpayConfig = $this->config['vnpay'] ?? [];
        if (empty($vnpayConfig['enabled'])) {
            throw new PaymentDomainException(['payment' => ['VNPay is disabled.']], 409);
        }

        $method = $this->methods->findByCode('vnpay');
        if ($method === null || strtolower((string) ($method['status'] ?? 'disabled')) !== 'active') {
            throw new PaymentDomainException(['payment' => ['VNPay is not available right now.']], 409);
        }

        if (!$this->gateway->isConfigured()) {
            throw new PaymentDomainException(['payment' => ['VNPay credentials are not configured.']], 503);
        }
    }

    private function buildVnpayPayload(
        string $orderCode,
        float $totalPrice,
        ?string $bankCode,
        array $requestContext,
        string $baseUrl,
        string $holdExpiresAt
    ): array {
        $createdAt = $this->formatGatewayTimestamp(date('Y-m-d H:i:s'));
        $expireAt = $this->formatGatewayTimestamp($holdExpiresAt);
        $returnUrl = trim((string) $this->gateway->config('return_url', ''));

        if ($returnUrl === '') {
            $returnUrl = rtrim($baseUrl, '/') . '/api/payments/vnpay/return';
        }

        $payload = [
            'vnp_Version' => (string) $this->gateway->config('version', '2.1.0'),
            'vnp_TmnCode' => (string) $this->gateway->config('tmn_code', ''),
            'vnp_Amount' => (string) ((int) round($totalPrice * 100)),
            'vnp_Command' => (string) $this->gateway->config('command', 'pay'),
            'vnp_CreateDate' => $createdAt,
            'vnp_CurrCode' => (string) $this->gateway->config('curr_code', 'VND'),
            'vnp_IpAddr' => $this->resolveClientIp($requestContext),
            'vnp_Locale' => (string) $this->gateway->config('locale', 'vn'),
            'vnp_OrderInfo' => 'Thanh toan ve xem phim ' . $orderCode,
            'vnp_OrderType' => (string) $this->gateway->config('order_type', 'other'),
            'vnp_ReturnUrl' => $returnUrl,
            'vnp_TxnRef' => $orderCode,
            'vnp_ExpireDate' => $expireAt,
        ];

        if ($bankCode !== null && $bankCode !== '') {
            $payload['vnp_BankCode'] = $bankCode;
        }

        return $payload;
    }

    private function resolvePendingExpiry(string $fallback): string
    {
        $minutes = (int) (($this->ticketConfig['pending_payment_ttl_minutes'] ?? 5) ?: 5);

        return date('Y-m-d H:i:s', strtotime('+' . max(1, $minutes) . ' minutes', strtotime($fallback)));
    }

    private function assertPendingCheckoutCapacity(string $sessionToken, ?int $userId): void
    {
        $maxPerSession = max(1, (int) ($this->ticketConfig['max_active_pending_orders_per_session'] ?? 1));
        if ($maxPerSession <= 1 && $this->orders->findActivePendingOrderBySession($sessionToken) !== null) {
            throw new PaymentDomainException([
                'checkout' => ['You already have a checkout waiting for payment in this session.'],
            ], 409);
        }

        $maxPerUser = max(1, (int) ($this->ticketConfig['max_active_pending_orders_per_user'] ?? 1));
        if ($userId !== null && $userId > 0 && $maxPerUser <= 1 && $this->orders->findActivePendingOrderByUser($userId) !== null) {
            throw new PaymentDomainException([
                'checkout' => ['Your account already has a checkout waiting for payment.'],
            ], 409);
        }
    }

    private function resolveBaseUrl(array $requestContext): string
    {
        $baseUrl = trim((string) ($requestContext['base_url'] ?? ''));
        if ($baseUrl !== '') {
            return rtrim($baseUrl, '/');
        }

        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host;
    }

    private function resolveClientIp(array $requestContext): string
    {
        $clientIp = trim((string) ($requestContext['client_ip'] ?? ''));
        if ($clientIp !== '') {
            return $clientIp;
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    private function formatGatewayTimestamp(string $dateTime): string
    {
        return date('YmdHis', strtotime($dateTime));
    }

    private function mapFailureState(string $responseCode): array
    {
        $normalized = trim($responseCode);
        if ($normalized === '24') {
            return [
                'payment_status' => 'cancelled',
                'order_status' => 'cancelled',
                'ticket_status' => 'cancelled',
                'message' => 'VNPay payment was cancelled by the customer.',
            ];
        }
        if ($normalized === '11') {
            return [
                'payment_status' => 'expired',
                'order_status' => 'expired',
                'ticket_status' => 'expired',
                'message' => 'VNPay payment expired before confirmation.',
            ];
        }

        return [
            'payment_status' => 'failed',
            'order_status' => 'cancelled',
            'ticket_status' => 'cancelled',
            'message' => 'VNPay payment failed or was declined.',
        ];
    }

    private function formatPayment(array $payment): array
    {
        return [
            'id' => isset($payment['id']) ? (int) $payment['id'] : null,
            'ticket_order_id' => isset($payment['ticket_order_id']) ? (int) $payment['ticket_order_id'] : null,
            'payment_method' => $payment['payment_method'] ?? null,
            'payment_status' => $payment['payment_status'] ?? null,
            'amount' => isset($payment['amount']) ? (float) $payment['amount'] : 0.0,
            'currency' => $payment['currency'] ?? self::CURRENCY,
            'transaction_code' => $payment['transaction_code'] ?? null,
            'provider_transaction_code' => $payment['provider_transaction_code'] ?? null,
            'provider_order_ref' => $payment['provider_order_ref'] ?? null,
            'provider_response_code' => $payment['provider_response_code'] ?? null,
            'provider_message' => $payment['provider_message'] ?? null,
            'checkout_url' => $payment['checkout_url'] ?? null,
            'initiated_at' => $payment['initiated_at'] ?? null,
            'completed_at' => $payment['completed_at'] ?? null,
            'failed_at' => $payment['failed_at'] ?? null,
            'payment_date' => $payment['payment_date'] ?? null,
        ];
    }

    private function paymentSuccessResponse(array $payment, string $source, string $ipnCode, string $ipnMessage): array
    {
        $orderType = isset($payment['shop_order_id']) && (int) $payment['shop_order_id'] > 0 ? 'shop' : 'ticket';
        $data = [
            'status' => strtolower((string) ($payment['payment_status'] ?? 'pending')) === 'success' ? 'success' : 'issue',
            'order_type' => $orderType,
            'order_code' => $payment['order_code'] ?? $payment['provider_order_ref'] ?? null,
            'payment_status' => $payment['payment_status'] ?? null,
            'transaction_code' => $payment['transaction_code'] ?? null,
            'provider_transaction_code' => $payment['provider_transaction_code'] ?? null,
            'message' => $payment['provider_message'] ?? $ipnMessage,
        ];

        if ($source === 'ipn') {
            return [
                'status' => 200,
                'data' => [
                    'RspCode' => $ipnCode,
                    'Message' => $ipnMessage,
                ],
            ];
        }

        return $this->success($data, 200);
    }

    private function paymentError(array $errors, int $status, string $source, ?string $ipnCode, ?string $ipnMessage): array
    {
        if ($source === 'ipn') {
            return [
                'status' => 200,
                'data' => [
                    'RspCode' => $ipnCode ?: '99',
                    'Message' => $ipnMessage ?: 'Unknown error',
                ],
            ];
        }

        return [
            'status' => $status,
            'errors' => $errors,
        ];
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
