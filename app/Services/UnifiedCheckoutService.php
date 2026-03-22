<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\OrderDetailRepository;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ShopOrderRepository;
use App\Repositories\TicketOrderRepository;
use App\Repositories\TicketSeatHoldRepository;
use App\Services\Concerns\FormatsShopOrderData;
use App\Services\Concerns\FormatsTicketData;
use App\Support\VnpayGateway;
use App\Validators\UnifiedCheckoutValidator;
use PDO;
use Throwable;

class UnifiedCheckoutService extends ShopCheckoutService
{
    use FormatsShopOrderData;
    use FormatsTicketData;

    private PDO $db;
    private UnifiedCartService $cartService;
    private TicketSeatHoldRepository $holds;
    private TicketCheckoutContextService $ticketContext;
    private TicketOrderRepository $ticketOrders;
    private ShopOrderRepository $shopOrders;
    private OrderDetailRepository $details;
    private ProductRepository $products;
    private PaymentRepository $payments;
    private PaymentMethodRepository $methods;
    private UnifiedCheckoutValidator $validator;
    private ShopOrderLifecycleService $shopLifecycle;
    private TicketLifecycleService $ticketLifecycle;
    private VnpayGateway $gateway;
    private Logger $logger;
    private array $shopConfig;
    private array $paymentConfig;

    public function __construct(
        ?PDO $db = null,
        ?UnifiedCartService $cartService = null,
        ?TicketSeatHoldRepository $holds = null,
        ?TicketCheckoutContextService $ticketContext = null,
        ?TicketOrderRepository $ticketOrders = null,
        ?ShopOrderRepository $shopOrders = null,
        ?OrderDetailRepository $details = null,
        ?ProductRepository $products = null,
        ?PaymentRepository $payments = null,
        ?PaymentMethodRepository $methods = null,
        ?UnifiedCheckoutValidator $validator = null,
        ?ShopOrderLifecycleService $shopLifecycle = null,
        ?TicketLifecycleService $ticketLifecycle = null,
        ?VnpayGateway $gateway = null,
        ?Logger $logger = null,
        ?array $shopConfig = null,
        ?array $paymentConfig = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->logger = $logger ?? new Logger();
        $this->shopConfig = $shopConfig ?? require dirname(__DIR__, 2) . '/config/shop.php';
        $this->paymentConfig = $paymentConfig ?? require dirname(__DIR__, 2) . '/config/payments.php';
        $this->ticketOrders = $ticketOrders ?? new TicketOrderRepository($this->db);
        $this->shopOrders = $shopOrders ?? new ShopOrderRepository($this->db);
        $this->details = $details ?? new OrderDetailRepository($this->db);
        $this->products = $products ?? new ProductRepository($this->db);
        $this->payments = $payments ?? new PaymentRepository($this->db);
        $this->methods = $methods ?? new PaymentMethodRepository($this->db);
        $this->holds = $holds ?? new TicketSeatHoldRepository($this->db);
        $this->ticketContext = $ticketContext ?? new TicketCheckoutContextService($this->db, null, null, $this->holds);
        $this->validator = $validator ?? new UnifiedCheckoutValidator($this->shopConfig);
        $this->shopLifecycle = $shopLifecycle ?? new ShopOrderLifecycleService(
            $this->db,
            $this->shopOrders,
            $this->details,
            $this->products,
            $this->payments,
            $this->logger
        );
        $this->ticketLifecycle = $ticketLifecycle ?? new TicketLifecycleService(
            $this->db,
            $this->holds,
            $this->ticketOrders,
            $this->payments,
            $this->logger
        );
        $this->gateway = $gateway ?? new VnpayGateway($this->paymentConfig);
        $this->cartService = $cartService ?? new UnifiedCartService($this->db, null, $this->holds, $this->ticketContext, $this->logger);
    }

    public function cartCookieName(): string
    {
        return $this->cartService->cartCookieName();
    }

    public function getCheckout(?int $userId = null, ?string $sessionToken = null): array
    {
        return $this->getCheckoutWithTickets($userId, $sessionToken, null);
    }

    public function getCheckoutWithTickets(
        ?int $userId = null,
        ?string $sessionToken = null,
        ?string $ticketSessionToken = null
    ): array {
        $startedAt = microtime(true);

        try {
            $this->shopLifecycle->runMaintenance();
            $this->ticketLifecycle->runMaintenance();
            $context = $this->loadCheckoutContext($userId, $sessionToken, $ticketSessionToken);
            $paymentMethods = $this->formatAvailablePaymentMethods($context);

            $payload = [
                'cart' => $context['product_cart'],
                'ticket_selection' => $context['ticket_selection'],
                'summary' => $context['summary'],
                'sync' => $context['sync'],
                'checkout_ready' => !($context['summary']['is_empty'] ?? true),
                'requirements' => [
                    'contains_products' => $context['contains_products'],
                    'contains_tickets' => $context['contains_tickets'],
                    'requires_fulfillment_selection' => $context['contains_products'],
                ],
                'defaults' => [
                    'fulfillment_method' => $context['contains_products'] ? 'pickup' : 'e_ticket',
                    'payment_method' => $this->defaultPaymentMethod($paymentMethods),
                ],
                'fulfillment_methods' => $this->formatFulfillmentMethods($context),
                'payment_methods' => $paymentMethods,
                'pricing' => [
                    'currency' => $context['summary']['currency'] ?? $this->currency(),
                    'pickup_shipping_amount' => 0.0,
                    'delivery_shipping_amount' => $context['contains_products']
                        ? $this->validator->defaultShippingAmount()
                        : 0.0,
                ],
                'active_order' => $context['active_order'],
            ];
        } catch (Throwable $exception) {
            $this->logger->error('Unified checkout load failed', [
                'user_id' => $userId,
                'session_token' => $this->sessionTokenPreview($sessionToken),
                'ticket_session_token' => $this->ticketSessionPreview($ticketSessionToken),
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to load checkout.']], 500);
        }

        return $this->success(
            $payload,
            200,
            $context['session_token'],
            $context['session_cookie_expires_at']
        );
    }

    public function createCheckout(
        array $payload,
        ?string $idempotencyKey,
        ?int $userId = null,
        ?string $sessionToken = null,
        array $requestContext = []
    ): array {
        return $this->createCheckoutWithTickets(
            $payload,
            $idempotencyKey,
            $userId,
            $sessionToken,
            isset($requestContext['ticket_session_token']) ? (string) $requestContext['ticket_session_token'] : null,
            $requestContext
        );
    }

    public function createCheckoutWithTickets(
        array $payload,
        ?string $idempotencyKey,
        ?int $userId = null,
        ?string $sessionToken = null,
        ?string $ticketSessionToken = null,
        array $requestContext = []
    ): array {
        $startedAt = microtime(true);

        try {
            $this->shopLifecycle->runMaintenance();
            $this->ticketLifecycle->runMaintenance();
            $context = $this->loadCheckoutContext($userId, $sessionToken, $ticketSessionToken);
            $validation = $this->validator->validateCreatePayload($payload, $idempotencyKey, $context);
            if (!empty($validation['errors'])) {
                return $this->error($validation['errors'], 422);
            }

            $this->assertCartStableForCheckout($context['sync'], $context['contains_products']);
            $snapshot = $this->commitCheckout($context, $validation['data'], $requestContext, $userId, $startedAt);
        } catch (UnifiedCheckoutDomainException $exception) {
            $this->logger->info('Unified checkout blocked by business rule', [
                'user_id' => $userId,
                'session_token' => $this->sessionTokenPreview($sessionToken),
                'ticket_session_token' => $this->ticketSessionPreview($ticketSessionToken),
                'errors' => $exception->errors(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error($exception->errors(), $exception->status());
        } catch (Throwable $exception) {
            $this->logger->error('Unified checkout creation failed', [
                'user_id' => $userId,
                'session_token' => $this->sessionTokenPreview($sessionToken),
                'ticket_session_token' => $this->ticketSessionPreview($ticketSessionToken),
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to create checkout.']], 500);
        }

        return $this->success(
            $snapshot['payload'],
            $snapshot['status'],
            $context['session_token'],
            $context['session_cookie_expires_at'],
            [
                'clear_session_cookie' => $userId === null && $context['contains_products'],
            ]
        );
    }

    private function loadCheckoutContext(?int $userId, ?string $sessionToken, ?string $ticketSessionToken): array
    {
        $cartResult = $this->cartService->getCart($userId, $sessionToken, $ticketSessionToken);
        if (isset($cartResult['errors'])) {
            throw new \RuntimeException('Cart context could not be resolved.');
        }

        $data = $cartResult['data'] ?? [];
        $productCart = $data['cart'] ?? $this->emptyProductCartPayload($userId);
        $ticketSelection = $data['ticket_selection'] ?? $this->emptyTicketSelection();
        $summary = $data['summary'] ?? $this->emptySummary();

        return [
            'product_cart' => $productCart,
            'ticket_selection' => $ticketSelection,
            'summary' => $summary,
            'sync' => $data['sync'] ?? [
                'merged_guest_cart' => 0,
                'adjusted_items' => 0,
                'removed_items' => 0,
            ],
            'contains_products' => !empty($summary['contains_products']),
            'contains_tickets' => !empty($summary['contains_tickets']),
            'active_order' => $this->buildActiveOrderPayload(
                $userId,
                $cartResult['session_token'] ?? $sessionToken,
                $ticketSessionToken
            ),
            'session_token' => $cartResult['session_token'] ?? $sessionToken,
            'ticket_session_token' => $ticketSessionToken,
            'session_cookie_expires_at' => (int) ($cartResult['session_cookie_expires_at'] ?? 0),
        ];
    }

    private function commitCheckout(array $context, array $data, array $requestContext, ?int $userId, float $startedAt): array
    {
        $existingPayment = $this->payments->findByIdempotencyKey((string) $data['idempotency_key']);
        if ($existingPayment !== null) {
            $snapshot = $this->hydrateCheckoutResult(
                (int) ($existingPayment['ticket_order_id'] ?? 0),
                (int) ($existingPayment['shop_order_id'] ?? 0),
                $existingPayment
            );

            $this->logger->info('Unified checkout replayed from idempotency key', [
                'user_id' => $userId,
                'order_code' => $snapshot['order']['order_code'] ?? null,
                'payment_method' => $snapshot['payment']['payment_method'] ?? null,
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return [
                'status' => 200,
                'payload' => array_merge($snapshot, ['idempotent_replay' => true]),
            ];
        }

        if ($context['active_order'] !== null) {
            throw new UnifiedCheckoutDomainException([
                'checkout' => ['An unfinished checkout is already waiting for payment.'],
            ], 409);
        }

        if ($context['summary']['is_empty'] ?? true) {
            throw new UnifiedCheckoutDomainException([
                'checkout' => ['Your cart is empty.'],
            ], 409);
        }

        $availableMethods = $this->availablePaymentMethodRowsByCode();
        if (!isset($availableMethods[(string) $data['payment_method']])) {
            throw new UnifiedCheckoutDomainException([
                'payment_method' => ['Selected payment method is not available right now.'],
            ], 409);
        }

        if ($data['payment_method'] === 'vnpay') {
            $this->assertVnpayReady($availableMethods);
        }

        $productLineItems = $context['contains_products']
            ? $this->buildProductLineItems($context['product_cart']['items'] ?? [])
            : [];
        $ticketPricing = $context['contains_tickets']
            ? $this->ticketContext->buildContext(
                (int) ($context['ticket_selection']['showtime_id'] ?? 0),
                [],
                (string) ($context['ticket_session_token'] ?? '')
            )
            : null;

        $result = $this->transactional(function () use ($context, $data, $productLineItems, $ticketPricing, $requestContext): array {
            $createdAt = date('Y-m-d H:i:s');
            $paymentDueAt = $this->resolvePendingExpiry($createdAt);
            $orderCode = $this->generateCheckoutCode();
            $shopOrderId = null;
            $ticketOrderId = null;
            $productTotal = 0.0;
            $ticketTotal = 0.0;

            if ($context['contains_products']) {
                $shippingAmount = $data['fulfillment_method'] === 'delivery'
                    ? $this->validator->defaultShippingAmount()
                    : 0.0;
                $productSubtotal = $this->sumLineTotals($productLineItems);
                $productTotal = round($productSubtotal + $shippingAmount, 2);

                $shopOrderId = $this->shopOrders->createOrder([
                    'order_code' => $orderCode,
                    'user_id' => $context['product_cart']['user_id'] ?? null,
                    'session_token' => $context['session_token'],
                    'address_id' => null,
                    'contact_name' => $data['contact_name'],
                    'contact_email' => $data['contact_email'],
                    'contact_phone' => $data['contact_phone'],
                    'fulfillment_method' => $data['fulfillment_method'],
                    'shipping_address_text' => $data['shipping_address_text'],
                    'shipping_city' => $data['shipping_city'],
                    'shipping_district' => $data['shipping_district'],
                    'item_count' => $this->sumItemCount($productLineItems),
                    'subtotal_price' => $productSubtotal,
                    'discount_amount' => 0.0,
                    'fee_amount' => 0.0,
                    'shipping_amount' => $shippingAmount,
                    'total_price' => $productTotal,
                    'currency' => $this->currency(),
                    'status' => 'pending',
                    'payment_due_at' => $paymentDueAt,
                    'confirmed_at' => null,
                    'fulfilled_at' => null,
                    'cancelled_at' => null,
                ]);

                $this->details->createMany($this->buildOrderDetailRows($shopOrderId, $productLineItems));
                $this->reserveInventory($productLineItems);
            }

            if ($context['contains_tickets']) {
                if ($ticketPricing === null) {
                    throw new UnifiedCheckoutDomainException([
                        'ticket_selection' => ['Held seats are missing or expired.'],
                    ], 409);
                }

                $isTicketPaidImmediately = $data['payment_method'] === 'cash' && !$context['contains_products'];
                $ticketStatus = $isTicketPaidImmediately ? 'paid' : 'pending';
                $ticketPaidAt = $isTicketPaidImmediately ? $createdAt : null;
                $ticketHoldExpiresAt = $isTicketPaidImmediately ? null : $paymentDueAt;
                $ticketTotal = round((float) ($ticketPricing['total_price'] ?? 0), 2);

                $ticketOrderId = $this->ticketOrders->createOrder([
                    'order_code' => $orderCode,
                    'user_id' => $context['product_cart']['user_id'] ?? null,
                    'session_token' => $context['ticket_session_token'],
                    'contact_name' => $data['contact_name'],
                    'contact_email' => $data['contact_email'],
                    'contact_phone' => $data['contact_phone'],
                    'fulfillment_method' => 'e_ticket',
                    'seat_count' => count($ticketPricing['seats'] ?? []),
                    'subtotal_price' => $ticketPricing['subtotal_price'] ?? 0.0,
                    'discount_amount' => 0.0,
                    'fee_amount' => 0.0,
                    'total_price' => $ticketTotal,
                    'currency' => $this->currency(),
                    'status' => $ticketStatus,
                    'hold_expires_at' => $ticketHoldExpiresAt,
                    'paid_at' => $ticketPaidAt,
                ]);

                $this->ticketOrders->createTicketDetails($this->buildTicketRows(
                    $ticketOrderId,
                    (int) ($ticketPricing['showtime']['id'] ?? 0),
                    $ticketPricing,
                    $ticketStatus
                ));
            }

            $paymentStatus = ($data['payment_method'] === 'cash' && !$context['contains_products'] && $context['contains_tickets'])
                ? 'success'
                : 'pending';
            $combinedTotal = round($productTotal + $ticketTotal, 2);
            $paymentId = $this->payments->createCombinedPayment([
                'ticket_order_id' => $ticketOrderId,
                'shop_order_id' => $shopOrderId,
                'payment_method' => $data['payment_method'],
                'payment_status' => $paymentStatus,
                'amount' => $combinedTotal,
                'currency' => $this->currency(),
                'transaction_code' => $this->generateTransactionCode(),
                'provider_order_ref' => $orderCode,
                'provider_message' => $this->initialProviderMessage($data['payment_method'], $context, $paymentStatus),
                'idempotency_key' => $data['idempotency_key'],
                'initiated_at' => $createdAt,
                'completed_at' => $paymentStatus === 'success' ? $createdAt : null,
                'payment_date' => $createdAt,
            ]);

            if ($data['payment_method'] === 'vnpay') {
                $checkout = $this->gateway->buildCheckoutUrl($this->buildVnpayPayload(
                    $orderCode,
                    $combinedTotal,
                    $requestContext,
                    $paymentDueAt
                ));
                $this->payments->updateGatewayCheckout($paymentId, [
                    'checkout_url' => $checkout['checkout_url'] ?? null,
                    'request_payload' => json_encode($checkout['query'], JSON_UNESCAPED_UNICODE),
                    'provider_order_ref' => $orderCode,
                    'idempotency_key' => $data['idempotency_key'],
                ]);
            }

            if ($context['contains_products']) {
                $clearResult = $this->cartService->clearCart(
                    isset($context['product_cart']['user_id']) ? (int) $context['product_cart']['user_id'] : null,
                    $context['session_token'],
                    null
                );
                if (isset($clearResult['errors'])) {
                    throw new UnifiedCheckoutDomainException([
                        'cart' => ['Product cart could not be finalized.'],
                    ], 500);
                }
            }

            if ($context['contains_tickets'] && trim((string) ($context['ticket_session_token'] ?? '')) !== '') {
                $this->holds->releaseForSession((string) $context['ticket_session_token']);
            }

            return [
                'ticket_order_id' => $ticketOrderId,
                'shop_order_id' => $shopOrderId,
            ];
        });

        $snapshot = $this->hydrateCheckoutResult(
            (int) ($result['ticket_order_id'] ?? 0),
            (int) ($result['shop_order_id'] ?? 0)
        );

        $this->logger->info('Unified checkout created', [
            'user_id' => $userId,
            'order_code' => $snapshot['order']['order_code'] ?? null,
            'order_scope' => $snapshot['order']['order_scope'] ?? null,
            'payment_method' => $snapshot['payment']['payment_method'] ?? null,
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return [
            'status' => 201,
            'payload' => array_merge($snapshot, ['idempotent_replay' => false]),
        ];
    }

    private function hydrateCheckoutResult(int $ticketOrderId, int $shopOrderId, ?array $payment = null): array
    {
        $ticketOrder = null;
        if ($ticketOrderId > 0) {
            $ticketHeader = $this->ticketOrders->findOrderHeaderById($ticketOrderId);
            if ($ticketHeader !== null) {
                $ticketDetails = $this->ticketOrders->listOrderContextRowsByOrderIds([$ticketOrderId]);
                $ticketOrder = $this->formatOrderDetail($ticketHeader, $ticketDetails);
            }
        }

        $shopOrder = null;
        if ($shopOrderId > 0) {
            $shopHeader = $this->shopOrders->findOrderHeaderById($shopOrderId);
            if ($shopHeader !== null) {
                $shopDetails = $this->details->listByOrderIds([$shopOrderId]);
                $shopOrder = $this->formatShopOrderDetail($shopHeader, $shopDetails);
            }
        }

        $paymentRow = $payment ?? $this->payments->findLatestByOrderIds(
            $ticketOrder['id'] ?? null,
            $shopOrder['id'] ?? null
        ) ?? [];
        $aggregateOrder = $this->buildAggregateOrder($ticketOrder, $shopOrder, $paymentRow);

        return [
            'order' => $aggregateOrder,
            'payment' => $this->formatPayment($paymentRow),
            'redirect_url' => $paymentRow['checkout_url'] ?? null,
            'redirect_expires_at' => $aggregateOrder['payment_due_at'] ?? null,
            'next_step' => strtolower((string) ($paymentRow['payment_method'] ?? '')) === 'vnpay'
                && strtolower((string) ($paymentRow['payment_status'] ?? 'pending')) === 'pending'
                ? 'redirect'
                : 'review',
        ];
    }

    private function buildActiveOrderPayload(?int $userId, ?string $sessionToken, ?string $ticketSessionToken): ?array
    {
        $ticketOrderId = $this->findPendingTicketOrderId($ticketSessionToken, $userId);
        $shopOrderId = $this->findPendingShopOrderId($sessionToken, $userId);
        if ($ticketOrderId === null && $shopOrderId === null) {
            return null;
        }

        $snapshot = $this->hydrateCheckoutResult($ticketOrderId ?? 0, $shopOrderId ?? 0);
        if (strtolower((string) ($snapshot['order']['status'] ?? '')) !== 'pending') {
            return null;
        }

        return [
            'resume_available' => true,
            'resume_target' => $snapshot['next_step'] === 'redirect' ? 'payment' : 'review',
            'resume_expires_at' => $snapshot['order']['payment_due_at'] ?? null,
            'redirect_url' => $snapshot['redirect_url'] ?? null,
            'order' => $snapshot['order'],
            'payment' => $snapshot['payment'],
        ];
    }

    private function buildAggregateOrder(?array $ticketOrder, ?array $shopOrder, array $payment): array
    {
        $containsTickets = $ticketOrder !== null;
        $containsProducts = $shopOrder !== null;
        $orderScope = $containsTickets && $containsProducts
            ? 'mixed'
            : ($containsTickets ? 'ticket' : 'shop');
        $orderCode = $shopOrder['order_code'] ?? $ticketOrder['order_code'] ?? ($payment['provider_order_ref'] ?? null);
        $status = $this->resolveAggregateStatus($ticketOrder, $shopOrder, $payment);
        $paymentDueAt = $shopOrder['payment_due_at'] ?? $ticketOrder['hold_expires_at'] ?? null;
        $paymentMethod = $payment['payment_method'] ?? $shopOrder['payment_method'] ?? $ticketOrder['payment_method'] ?? null;
        $paymentStatus = $payment['payment_status'] ?? $shopOrder['payment_status'] ?? $ticketOrder['payment_status'] ?? null;
        $shippingSummary = $containsProducts ? $this->shippingSummary($shopOrder ?? []) : 'E-ticket delivery';

        return [
            'id' => $shopOrder['id'] ?? $ticketOrder['id'] ?? 0,
            'order_code' => $orderCode,
            'order_scope' => $orderScope,
            'user_id' => $shopOrder['user_id'] ?? $ticketOrder['user_id'] ?? null,
            'contact_name' => $shopOrder['contact_name'] ?? $ticketOrder['contact_name'] ?? null,
            'contact_email' => $shopOrder['contact_email'] ?? $ticketOrder['contact_email'] ?? null,
            'contact_phone' => $shopOrder['contact_phone'] ?? $ticketOrder['contact_phone'] ?? null,
            'fulfillment_method' => $containsProducts ? ($shopOrder['fulfillment_method'] ?? 'pickup') : 'e_ticket',
            'shipping_address' => $shopOrder['shipping_address'] ?? [
                'address_text' => null,
                'city' => null,
                'district' => null,
            ],
            'item_count' => isset($shopOrder['item_count']) ? (int) $shopOrder['item_count'] : 0,
            'seat_count' => isset($ticketOrder['seat_count']) ? (int) $ticketOrder['seat_count'] : 0,
            'subtotal_price' => round((float) ($shopOrder['subtotal_price'] ?? 0) + (float) ($ticketOrder['subtotal_price'] ?? 0), 2),
            'discount_amount' => round((float) ($shopOrder['discount_amount'] ?? 0) + (float) ($ticketOrder['discount_amount'] ?? 0), 2),
            'fee_amount' => round((float) ($shopOrder['fee_amount'] ?? 0) + (float) ($ticketOrder['fee_amount'] ?? 0), 2),
            'shipping_amount' => isset($shopOrder['shipping_amount']) ? (float) $shopOrder['shipping_amount'] : 0.0,
            'total_price' => round((float) ($shopOrder['total_price'] ?? 0) + (float) ($ticketOrder['total_price'] ?? 0), 2),
            'currency' => $shopOrder['currency'] ?? $ticketOrder['currency'] ?? $this->currency(),
            'status' => $status,
            'status_group' => $this->resolveAggregateStatusGroup($orderScope, $status, $paymentStatus),
            'payment_due_at' => $paymentDueAt,
            'hold_expires_at' => $ticketOrder['hold_expires_at'] ?? null,
            'confirmed_at' => $shopOrder['confirmed_at'] ?? null,
            'fulfilled_at' => $shopOrder['fulfilled_at'] ?? null,
            'cancelled_at' => $shopOrder['cancelled_at'] ?? null,
            'paid_at' => $ticketOrder['paid_at'] ?? null,
            'order_date' => $shopOrder['order_date'] ?? $ticketOrder['order_date'] ?? null,
            'updated_at' => $shopOrder['updated_at'] ?? $ticketOrder['updated_at'] ?? null,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'transaction_code' => $payment['transaction_code'] ?? $shopOrder['transaction_code'] ?? $ticketOrder['transaction_code'] ?? null,
            'redirect_url' => $payment['checkout_url'] ?? null,
            'contains_products' => $containsProducts,
            'contains_tickets' => $containsTickets,
            'is_guest_order' => (int) ($shopOrder['user_id'] ?? $ticketOrder['user_id'] ?? 0) <= 0,
            'shipping_summary' => $shippingSummary,
            'requires_payment_resume' => strtolower((string) $paymentMethod) === 'vnpay'
                && strtolower((string) $paymentStatus) === 'pending'
                && trim((string) ($payment['checkout_url'] ?? '')) !== ''
                && $this->expiresAtFuture($paymentDueAt),
            'expires_in_seconds' => $this->remainingSeconds($paymentDueAt),
            'can_cancel' => $this->canCancelAggregateOrder($orderScope, $status, $paymentStatus),
            'product_order_id' => $shopOrder['id'] ?? null,
            'ticket_order_id' => $ticketOrder['id'] ?? null,
            'items' => $shopOrder['items'] ?? [],
            'tickets' => $ticketOrder['tickets'] ?? [],
            'preview_items' => $this->buildPreviewItems($ticketOrder, $shopOrder),
            'shop_order' => $shopOrder,
            'ticket_order' => $ticketOrder,
        ];
    }

    private function buildPreviewItems(?array $ticketOrder, ?array $shopOrder): array
    {
        $previewItems = [];

        foreach (array_slice($shopOrder['items'] ?? [], 0, 2) as $item) {
            $item['preview_type'] = 'product';
            $previewItems[] = $item;
        }

        if ($ticketOrder !== null) {
            $previewItems[] = [
                'preview_type' => 'ticket',
                'label' => $ticketOrder['movie_title'] ?? 'Movie tickets',
                'summary' => implode(', ', array_slice($ticketOrder['seats'] ?? [], 0, 3)),
                'line_total' => (float) ($ticketOrder['total_price'] ?? 0),
                'currency' => $ticketOrder['currency'] ?? $this->currency(),
                'primary_image_url' => $ticketOrder['poster_url'] ?? null,
                'primary_image_alt' => $ticketOrder['movie_title'] ?? 'Movie poster',
            ];
        }

        return array_slice($previewItems, 0, 3);
    }

    private function buildProductLineItems(array $items): array
    {
        $lineItems = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            $unitPrice = round((float) ($item['unit_price'] ?? 0), 2);
            $name = trim((string) ($item['name'] ?? ''));

            if ($productId <= 0 || $quantity <= 0 || $unitPrice < 0 || $name === '') {
                throw new UnifiedCheckoutDomainException([
                    'cart' => ['Cart contains invalid product data. Please refresh the cart and try again.'],
                ], 409);
            }

            $lineItems[] = [
                'product_id' => $productId,
                'product_name_snapshot' => $name,
                'product_sku_snapshot' => $item['sku'] ?? null,
                'quantity' => $quantity,
                'price' => $unitPrice,
                'discount_amount' => 0.0,
                'line_total' => round($unitPrice * $quantity, 2),
                'track_inventory' => (int) ($item['track_inventory'] ?? 0),
            ];
        }

        return $lineItems;
    }

    private function buildOrderDetailRows(int $orderId, array $lineItems): array
    {
        $rows = [];
        foreach ($lineItems as $lineItem) {
            $rows[] = [
                'order_id' => $orderId,
                'product_id' => $lineItem['product_id'],
                'product_name_snapshot' => $lineItem['product_name_snapshot'],
                'product_sku_snapshot' => $lineItem['product_sku_snapshot'],
                'quantity' => $lineItem['quantity'],
                'price' => $lineItem['price'],
                'discount_amount' => $lineItem['discount_amount'],
                'line_total' => $lineItem['line_total'],
            ];
        }

        return $rows;
    }

    private function buildTicketRows(int $orderId, int $showtimeId, array $ticketPricing, string $status): array
    {
        $rows = [];
        foreach ($ticketPricing['seats'] ?? [] as $seat) {
            $ticketCode = $this->generateTicketCode();
            $rows[] = [
                'order_id' => $orderId,
                'showtime_id' => $showtimeId,
                'seat_id' => (int) ($seat['id'] ?? 0),
                'ticket_code' => $ticketCode,
                'status' => $status,
                'base_price' => $ticketPricing['base_price'] ?? 0.0,
                'surcharge_amount' => (float) ($seat['surcharge_amount'] ?? 0),
                'discount_amount' => 0.0,
                'price' => (float) ($seat['price'] ?? 0),
                'qr_payload' => 'ticket:' . $ticketCode,
            ];
        }

        return $rows;
    }

    private function reserveInventory(array $lineItems): void
    {
        foreach ($lineItems as $lineItem) {
            if ((int) ($lineItem['track_inventory'] ?? 0) !== 1) {
                continue;
            }

            if (!$this->products->decrementTrackedInventory((int) $lineItem['product_id'], (int) $lineItem['quantity'])) {
                throw new UnifiedCheckoutDomainException([
                    'stock' => [
                        sprintf('%s no longer has enough stock to complete checkout.', $lineItem['product_name_snapshot']),
                    ],
                ], 409);
            }
        }
    }

    private function formatAvailablePaymentMethods(array $context): array
    {
        $allowedMap = $this->validator->paymentMethodAllowedFulfillmentMap(
            $context['contains_products'],
            $context['contains_tickets']
        );

        return array_map(function (array $row) use ($allowedMap): array {
            $code = strtolower(trim((string) ($row['code'] ?? '')));

            return [
                'code' => $code,
                'name' => $row['name'] ?? ucfirst($code),
                'provider' => $row['provider'] ?? null,
                'channel_type' => $row['channel_type'] ?? null,
                'supports_redirect' => (int) ($row['supports_redirect'] ?? 0),
                'fee_rate_percent' => isset($row['fee_rate_percent']) ? (float) $row['fee_rate_percent'] : 0.0,
                'fixed_fee_amount' => isset($row['fixed_fee_amount']) ? (float) $row['fixed_fee_amount'] : 0.0,
                'description' => $row['description'] ?? null,
                'allowed_fulfillment_methods' => $allowedMap[$code] ?? [],
            ];
        }, array_values($this->availablePaymentMethodRowsByCode()));
    }

    private function formatFulfillmentMethods(array $context): array
    {
        if (!$context['contains_products']) {
            return [[
                'code' => 'e_ticket',
                'label' => 'E-ticket Delivery',
                'description' => 'Your movie tickets are issued digitally after payment is confirmed.',
            ]];
        }

        return [
            [
                'code' => 'pickup',
                'label' => 'Counter Pickup',
                'description' => 'Receive your order at the cinema shop counter.',
            ],
            [
                'code' => 'delivery',
                'label' => 'Delivery',
                'description' => 'Ship the order to the delivery address entered below.',
            ],
        ];
    }

    private function availablePaymentMethodRowsByCode(): array
    {
        $rows = $this->methods->listActiveByCodes($this->validator->supportedPaymentMethods());
        $mapped = [];
        foreach ($rows as $row) {
            $code = strtolower(trim((string) ($row['code'] ?? '')));
            if ($code === '') {
                continue;
            }
            $mapped[$code] = $row;
        }

        return $mapped;
    }

    private function defaultPaymentMethod(array $paymentMethods): ?string
    {
        return $paymentMethods[0]['code'] ?? null;
    }

    private function assertVnpayReady(array $availableMethods): void
    {
        $vnpayConfig = $this->paymentConfig['vnpay'] ?? [];
        if (empty($vnpayConfig['enabled'])) {
            throw new UnifiedCheckoutDomainException([
                'payment_method' => ['VNPay is disabled.'],
            ], 409);
        }

        if (!isset($availableMethods['vnpay'])) {
            throw new UnifiedCheckoutDomainException([
                'payment_method' => ['VNPay is not available right now.'],
            ], 409);
        }

        if (!$this->gateway->isConfigured()) {
            throw new UnifiedCheckoutDomainException([
                'payment_method' => ['VNPay credentials are not configured.'],
            ], 503);
        }
    }

    private function buildVnpayPayload(string $orderCode, float $totalPrice, array $requestContext, string $paymentDueAt): array
    {
        $createdAt = $this->formatGatewayTimestamp(date('Y-m-d H:i:s'));
        $expireAt = $this->formatGatewayTimestamp($paymentDueAt);
        $baseUrl = $this->resolveBaseUrl($requestContext);
        $returnUrl = trim((string) $this->gateway->config('return_url', ''));

        if ($returnUrl === '') {
            $returnUrl = rtrim($baseUrl, '/') . '/api/payments/vnpay/return';
        }

        return [
            'vnp_Version' => (string) $this->gateway->config('version', '2.1.0'),
            'vnp_TmnCode' => (string) $this->gateway->config('tmn_code', ''),
            'vnp_Amount' => (string) ((int) round($totalPrice * 100)),
            'vnp_Command' => (string) $this->gateway->config('command', 'pay'),
            'vnp_CreateDate' => $createdAt,
            'vnp_CurrCode' => (string) $this->gateway->config('curr_code', 'VND'),
            'vnp_IpAddr' => $this->resolveClientIp($requestContext),
            'vnp_Locale' => (string) $this->gateway->config('locale', 'vn'),
            'vnp_OrderInfo' => 'Thanh toan don hang CinemaX ' . $orderCode,
            'vnp_OrderType' => (string) $this->gateway->config('order_type', 'other'),
            'vnp_ReturnUrl' => $returnUrl,
            'vnp_TxnRef' => $orderCode,
            'vnp_ExpireDate' => $expireAt,
        ];
    }

    private function resolvePendingExpiry(string $createdAt): string
    {
        return date(
            'Y-m-d H:i:s',
            strtotime('+' . $this->validator->pendingPaymentTtlMinutes() . ' minutes', strtotime($createdAt))
        );
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

    private function generateCheckoutCode(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = 'ORD-' . strtoupper(bin2hex(random_bytes(5)));
            if (!$this->ticketOrders->orderCodeExists($code) && !$this->shopOrders->orderCodeExists($code)) {
                return $code;
            }
        }

        throw new \RuntimeException('Failed to generate unique checkout code.');
    }

    private function generateTicketCode(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = 'TIC-' . strtoupper(bin2hex(random_bytes(6)));
            if (!$this->ticketOrders->ticketCodeExists($code)) {
                return $code;
            }
        }

        throw new \RuntimeException('Failed to generate unique ticket code.');
    }

    private function generateTransactionCode(): string
    {
        return 'PAY-' . strtoupper(bin2hex(random_bytes(6)));
    }

    private function formatPayment(array $payment): array
    {
        return [
            'id' => isset($payment['id']) ? (int) $payment['id'] : null,
            'ticket_order_id' => isset($payment['ticket_order_id']) ? (int) $payment['ticket_order_id'] : null,
            'shop_order_id' => isset($payment['shop_order_id']) ? (int) $payment['shop_order_id'] : null,
            'payment_method' => $payment['payment_method'] ?? null,
            'payment_status' => $payment['payment_status'] ?? null,
            'amount' => isset($payment['amount']) ? (float) $payment['amount'] : 0.0,
            'currency' => $payment['currency'] ?? $this->currency(),
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

    private function resolveAggregateStatus(?array $ticketOrder, ?array $shopOrder, array $payment): string
    {
        $paymentStatus = strtolower(trim((string) ($payment['payment_status'] ?? '')));
        if (in_array($paymentStatus, ['failed', 'cancelled', 'expired', 'refunded'], true)) {
            return $shopOrder['status'] ?? $ticketOrder['status'] ?? 'cancelled';
        }

        if ($shopOrder !== null) {
            return strtolower(trim((string) ($shopOrder['status'] ?? 'pending')));
        }

        return strtolower(trim((string) ($ticketOrder['status'] ?? 'pending')));
    }

    private function resolveAggregateStatusGroup(string $orderScope, string $status, ?string $paymentStatus): string
    {
        $normalizedStatus = strtolower(trim($status));
        $normalizedPaymentStatus = strtolower(trim((string) ($paymentStatus ?? '')));

        if (in_array($normalizedStatus, ['cancelled', 'expired', 'refunded'], true)
            || in_array($normalizedPaymentStatus, ['failed', 'cancelled', 'expired', 'refunded'], true)) {
            return 'issue';
        }

        if ($normalizedStatus === 'pending') {
            return 'pending';
        }

        if ($orderScope === 'ticket' && $normalizedStatus === 'paid') {
            return 'completed';
        }

        if (in_array($normalizedStatus, ['confirmed', 'preparing', 'ready', 'shipping'], true)) {
            return 'active';
        }

        if (in_array($normalizedStatus, ['completed', 'paid'], true)) {
            return 'completed';
        }

        return 'active';
    }

    private function shippingSummary(array $shopOrder): string
    {
        if (strtolower((string) ($shopOrder['fulfillment_method'] ?? 'pickup')) === 'pickup') {
            return 'Pickup at cinema counter';
        }

        $parts = array_filter([
            trim((string) ($shopOrder['shipping_address']['address_text'] ?? '')),
            trim((string) ($shopOrder['shipping_address']['district'] ?? '')),
            trim((string) ($shopOrder['shipping_address']['city'] ?? '')),
        ], static function (string $value): bool {
            return $value !== '';
        });

        return $parts !== [] ? implode(', ', $parts) : 'Delivery address pending';
    }

    private function canCancelAggregateOrder(string $orderScope, string $status, ?string $paymentStatus): bool
    {
        if ($orderScope !== 'shop') {
            return false;
        }

        return strtolower($status) === 'pending'
            && !in_array(strtolower((string) ($paymentStatus ?? 'pending')), ['success', 'processing', 'refunded'], true);
    }

    private function findPendingTicketOrderId(?string $ticketSessionToken, ?int $userId): ?int
    {
        $normalizedSessionToken = trim((string) ($ticketSessionToken ?? ''));
        if ($normalizedSessionToken !== '') {
            $header = $this->ticketOrders->findActivePendingOrderBySession($normalizedSessionToken);
            if ($header !== null) {
                return (int) ($header['id'] ?? 0) ?: null;
            }
        }

        if ($userId !== null && $userId > 0) {
            $header = $this->ticketOrders->findActivePendingOrderByUser($userId);
            if ($header !== null) {
                return (int) ($header['id'] ?? 0) ?: null;
            }
        }

        return null;
    }

    private function findPendingShopOrderId(?string $sessionToken, ?int $userId): ?int
    {
        $normalizedSessionToken = trim((string) ($sessionToken ?? ''));
        if ($normalizedSessionToken !== '') {
            $header = $this->shopOrders->findActivePendingOrderBySession($normalizedSessionToken);
            if ($header !== null) {
                return (int) ($header['id'] ?? 0) ?: null;
            }
        }

        if ($userId !== null && $userId > 0) {
            $header = $this->shopOrders->findActivePendingOrderByUser($userId);
            if ($header !== null) {
                return (int) ($header['id'] ?? 0) ?: null;
            }
        }

        return null;
    }

    private function initialProviderMessage(string $paymentMethod, array $context, string $paymentStatus): string
    {
        if ($paymentMethod === 'vnpay') {
            return 'Pending redirect to VNPay.';
        }

        if ($paymentStatus === 'success') {
            return 'Payment was captured during checkout.';
        }

        if ($context['contains_products']) {
            return 'Awaiting payment confirmation at the counter.';
        }

        return 'Payment is waiting for confirmation.';
    }

    private function assertCartStableForCheckout(array $sync, bool $containsProducts): void
    {
        if (!$containsProducts) {
            return;
        }

        $adjustedItems = max(0, (int) ($sync['adjusted_items'] ?? 0));
        $removedItems = max(0, (int) ($sync['removed_items'] ?? 0));
        if ($adjustedItems === 0 && $removedItems === 0) {
            return;
        }

        throw new UnifiedCheckoutDomainException([
            'cart_sync' => ['Your cart was updated to match current stock or pricing. Please review it and submit again.'],
        ], 409);
    }

    private function expiresAtFuture(?string $value): bool
    {
        $timestamp = $this->toTimestamp($value);

        return $timestamp !== null && $timestamp > time();
    }

    private function remainingSeconds(?string $value): int
    {
        $timestamp = $this->toTimestamp($value);
        if ($timestamp === null) {
            return 0;
        }

        return max(0, $timestamp - time());
    }

    private function toTimestamp(?string $value): ?int
    {
        $normalized = trim((string) ($value ?? ''));
        if ($normalized === '') {
            return null;
        }

        $timestamp = strtotime($normalized);

        return $timestamp !== false ? $timestamp : null;
    }

    private function emptyProductCartPayload(?int $userId): array
    {
        return [
            'id' => null,
            'user_id' => $userId,
            'currency' => $this->currency(),
            'status' => 'active',
            'expires_at' => null,
            'line_count' => 0,
            'item_count' => 0,
            'subtotal_price' => 0.0,
            'discount_amount' => 0.0,
            'fee_amount' => 0.0,
            'total_price' => 0.0,
            'is_empty' => true,
            'items' => [],
        ];
    }

    private function emptyTicketSelection(): array
    {
        return [
            'is_empty' => true,
            'showtime_id' => null,
            'showtime' => null,
            'seats' => [],
            'seat_count' => 0,
            'subtotal_price' => 0.0,
            'surcharge_total' => 0.0,
            'total_price' => 0.0,
            'currency' => $this->currency(),
            'hold_expires_at' => null,
        ];
    }

    private function emptySummary(): array
    {
        return [
            'currency' => $this->currency(),
            'product_line_count' => 0,
            'product_item_count' => 0,
            'ticket_line_count' => 0,
            'ticket_item_count' => 0,
            'line_count' => 0,
            'item_count' => 0,
            'product_subtotal_price' => 0.0,
            'ticket_total_price' => 0.0,
            'subtotal_price' => 0.0,
            'discount_amount' => 0.0,
            'fee_amount' => 0.0,
            'total_price' => 0.0,
            'contains_products' => false,
            'contains_tickets' => false,
            'is_empty' => true,
        ];
    }

    private function sumItemCount(array $lineItems): int
    {
        return array_sum(array_map(static function (array $lineItem): int {
            return (int) ($lineItem['quantity'] ?? 0);
        }, $lineItems));
    }

    private function sumLineTotals(array $lineItems): float
    {
        return round(array_sum(array_map(static function (array $lineItem): float {
            return (float) ($lineItem['line_total'] ?? 0);
        }, $lineItems)), 2);
    }

    private function currency(): string
    {
        return strtoupper(trim((string) ($this->shopConfig['currency'] ?? 'VND')));
    }

    private function sessionTokenPreview(?string $value): ?string
    {
        $normalized = strtolower(trim((string) ($value ?? '')));
        if ($normalized === '') {
            return null;
        }

        return substr($normalized, 0, 12);
    }

    private function ticketSessionPreview(?string $value): ?string
    {
        $normalized = strtolower(trim((string) ($value ?? '')));
        if ($normalized === '') {
            return null;
        }

        return substr($normalized, 0, 12);
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

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function success(
        array $data,
        int $status = 200,
        ?string $sessionToken = null,
        ?int $expiresAt = null,
        array $options = []
    ): array {
        return [
            'status' => $status,
            'data' => $data,
            'session_token' => $sessionToken,
            'session_cookie_expires_at' => $expiresAt,
            'clear_session_cookie' => (bool) ($options['clear_session_cookie'] ?? false),
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

class UnifiedCheckoutDomainException extends \RuntimeException
{
    private array $errors;
    private int $status;

    public function __construct(array $errors, int $status)
    {
        parent::__construct('Unified checkout domain exception.');
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
