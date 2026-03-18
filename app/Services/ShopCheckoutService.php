<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\CartItemRepository;
use App\Repositories\CartRepository;
use App\Repositories\OrderDetailRepository;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ShopOrderRepository;
use App\Services\Concerns\FormatsShopOrderData;
use App\Support\AssetUrlResolver;
use App\Support\VnpayGateway;
use App\Validators\ShopCheckoutValidator;
use PDO;
use Throwable;

class ShopCheckoutService
{
    use FormatsShopOrderData;

    private PDO $db;
    private ShopCartService $cartService;
    private CartRepository $carts;
    private CartItemRepository $items;
    private ProductRepository $products;
    private ShopOrderRepository $orders;
    private OrderDetailRepository $details;
    private PaymentRepository $payments;
    private PaymentMethodRepository $methods;
    private ShopCheckoutValidator $validator;
    private ShopOrderLifecycleService $lifecycle;
    private VnpayGateway $gateway;
    private Logger $logger;
    private AssetUrlResolver $assetUrlResolver;
    private array $shopConfig;
    private array $paymentConfig;

    public function __construct(
        ?PDO $db = null,
        ?ShopCartService $cartService = null,
        ?CartRepository $carts = null,
        ?CartItemRepository $items = null,
        ?ProductRepository $products = null,
        ?ShopOrderRepository $orders = null,
        ?OrderDetailRepository $details = null,
        ?PaymentRepository $payments = null,
        ?PaymentMethodRepository $methods = null,
        ?ShopCheckoutValidator $validator = null,
        ?ShopOrderLifecycleService $lifecycle = null,
        ?VnpayGateway $gateway = null,
        ?Logger $logger = null,
        ?array $shopConfig = null,
        ?array $paymentConfig = null,
        ?AssetUrlResolver $assetUrlResolver = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->shopConfig = $shopConfig ?? require dirname(__DIR__, 2) . '/config/shop.php';
        $this->paymentConfig = $paymentConfig ?? require dirname(__DIR__, 2) . '/config/payments.php';
        $this->logger = $logger ?? new Logger();
        $this->assetUrlResolver = $assetUrlResolver ?? new AssetUrlResolver((string) (getenv('APP_URL') ?: ''));
        $this->carts = $carts ?? new CartRepository($this->db);
        $this->items = $items ?? new CartItemRepository($this->db);
        $this->products = $products ?? new ProductRepository($this->db);
        $this->orders = $orders ?? new ShopOrderRepository($this->db);
        $this->details = $details ?? new OrderDetailRepository($this->db);
        $this->payments = $payments ?? new PaymentRepository($this->db);
        $this->methods = $methods ?? new PaymentMethodRepository($this->db);
        $this->validator = $validator ?? new ShopCheckoutValidator($this->shopConfig);
        $this->lifecycle = $lifecycle ?? new ShopOrderLifecycleService(
            $this->db,
            $this->orders,
            $this->details,
            $this->products,
            $this->payments,
            $this->logger
        );
        $this->gateway = $gateway ?? new VnpayGateway($this->paymentConfig);
        $this->cartService = $cartService ?? new ShopCartService(
            $this->db,
            $this->carts,
            $this->items,
            $this->products,
            null,
            $this->logger,
            $this->shopConfig,
            $this->assetUrlResolver
        );
    }

    public function cartCookieName(): string
    {
        return $this->cartService->cartCookieName();
    }

    public function getCheckout(?int $userId = null, ?string $sessionToken = null): array
    {
        $startedAt = microtime(true);

        try {
            $this->lifecycle->runMaintenance();
            $context = $this->loadCheckoutContext($userId, $sessionToken);
            $paymentMethods = $this->formatAvailablePaymentMethods();

            $payload = [
                'cart' => $context['cart_payload'],
                'checkout_ready' => !($context['cart_payload']['is_empty'] ?? true),
                'defaults' => [
                    'fulfillment_method' => $this->validator->fulfillmentMethods()[0] ?? 'pickup',
                    'payment_method' => $this->defaultPaymentMethod($paymentMethods),
                ],
                'fulfillment_methods' => $this->formatFulfillmentMethods(),
                'payment_methods' => $paymentMethods,
                'active_order' => $context['active_order'],
            ];
        } catch (Throwable $exception) {
            $this->logger->error('Shop checkout load failed', [
                'user_id' => $userId,
                'session_token' => $this->sessionTokenPreview($sessionToken),
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to load shop checkout.']], 500);
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
        $startedAt = microtime(true);
        $validation = $this->validator->validateCreatePayload($payload, $idempotencyKey);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $data = $validation['data'];

        try {
            $this->lifecycle->runMaintenance();
            $context = $this->loadCheckoutContext($userId, $sessionToken);
            $snapshot = $this->commitCheckout($context, $data, $requestContext, $userId, $startedAt);
        } catch (ShopCheckoutDomainException $exception) {
            $this->logger->info('Shop checkout blocked by business rule', [
                'user_id' => $userId,
                'session_token' => $this->sessionTokenPreview($sessionToken),
                'errors' => $exception->errors(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error($exception->errors(), $exception->status());
        } catch (Throwable $exception) {
            $this->logger->error('Shop checkout creation failed', [
                'user_id' => $userId,
                'session_token' => $this->sessionTokenPreview($sessionToken),
                'payment_method' => $data['payment_method'] ?? null,
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to create shop checkout.']], 500);
        }

        return $this->success(
            $snapshot['payload'],
            $snapshot['status'],
            $context['session_token'],
            $context['session_cookie_expires_at'],
            [
                'clear_session_cookie' => $userId === null,
            ]
        );
    }

    protected function resolveShopAssetUrl(?string $value): ?string
    {
        return $this->assetUrlResolver->resolve($value);
    }

    private function commitCheckout(
        array $context,
        array $data,
        array $requestContext,
        ?int $userId,
        float $startedAt
    ): array {
        $existingPayment = $this->payments->findByIdempotencyKey((string) $data['idempotency_key']);
        if ($existingPayment !== null) {
            if ((int) ($existingPayment['shop_order_id'] ?? 0) <= 0) {
                throw new ShopCheckoutDomainException([
                    'idempotency_key' => ['Idempotency key is already in use.'],
                ], 409);
            }

            $snapshot = $this->hydrateCheckoutResult((int) $existingPayment['shop_order_id']);
            $this->logger->info('Shop checkout replayed from idempotency key', [
                'user_id' => $userId,
                'session_token' => $this->sessionTokenPreview($context['session_token']),
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
            throw new ShopCheckoutDomainException([
                'checkout' => ['An unfinished shop checkout is already waiting for payment.'],
            ], 409);
        }

        if (($context['cart_payload']['is_empty'] ?? true) || empty($context['cart_payload']['items'])) {
            throw new ShopCheckoutDomainException([
                'cart' => ['Your cart is empty.'],
            ], 409);
        }

        if ($context['cart'] === null) {
            throw new \RuntimeException('Checkout context lost the active cart record.');
        }

        $availableMethods = $this->availablePaymentMethodRowsByCode();
        if (!isset($availableMethods[(string) $data['payment_method']])) {
            throw new ShopCheckoutDomainException([
                'payment_method' => ['Selected payment method is not available right now.'],
            ], 409);
        }

        if ($data['payment_method'] === 'vnpay') {
            $this->assertVnpayReady($availableMethods);
        }

        $lineItems = $this->buildOrderLineItems($context['cart_payload']['items']);
        $result = $this->transactional(function () use ($context, $data, $lineItems, $requestContext): array {
            $createdAt = date('Y-m-d H:i:s');
            $paymentDueAt = $this->resolvePendingExpiry($createdAt);
            $shippingAmount = $data['fulfillment_method'] === 'delivery'
                ? $this->validator->defaultShippingAmount()
                : 0.0;
            $subtotal = $this->sumLineTotals($lineItems);
            $discountAmount = 0.0;
            $feeAmount = 0.0;
            $totalPrice = round($subtotal - $discountAmount + $feeAmount + $shippingAmount, 2);
            $orderCode = $this->generateOrderCode();

            $orderId = $this->orders->createOrder([
                'order_code' => $orderCode,
                'user_id' => $context['cart_payload']['user_id'] ?? null,
                'session_token' => $context['session_token'],
                'address_id' => null,
                'contact_name' => $data['contact_name'],
                'contact_email' => $data['contact_email'],
                'contact_phone' => $data['contact_phone'],
                'fulfillment_method' => $data['fulfillment_method'],
                'shipping_address_text' => $data['shipping_address_text'],
                'shipping_city' => $data['shipping_city'],
                'shipping_district' => $data['shipping_district'],
                'item_count' => $this->sumItemCount($lineItems),
                'subtotal_price' => $subtotal,
                'discount_amount' => $discountAmount,
                'fee_amount' => $feeAmount,
                'shipping_amount' => $shippingAmount,
                'total_price' => $totalPrice,
                'currency' => $this->currency(),
                'status' => 'pending',
                'payment_due_at' => $paymentDueAt,
                'confirmed_at' => null,
                'fulfilled_at' => null,
                'cancelled_at' => null,
            ]);

            $this->details->createMany($this->buildOrderDetailRows($orderId, $lineItems));
            $this->reserveInventory($lineItems);

            $paymentId = $this->payments->createShopPayment($this->buildPaymentPayload(
                $orderId,
                $orderCode,
                $data,
                $totalPrice,
                $createdAt
            ));

            $redirectUrl = null;
            if ($data['payment_method'] === 'vnpay') {
                $checkout = $this->gateway->buildCheckoutUrl($this->buildVnpayPayload(
                    $orderCode,
                    $totalPrice,
                    $requestContext,
                    $paymentDueAt
                ));
                $redirectUrl = $checkout['checkout_url'];
                $this->payments->updateGatewayCheckout($paymentId, [
                    'checkout_url' => $redirectUrl,
                    'request_payload' => json_encode($checkout['query'], JSON_UNESCAPED_UNICODE),
                    'provider_order_ref' => $orderCode,
                    'idempotency_key' => $data['idempotency_key'],
                ]);
            }

            $this->items->deleteByCartId((int) $context['cart']['id']);
            $this->carts->updateStatus((int) $context['cart']['id'], 'converted');

            return [
                'order_id' => $orderId,
                'redirect_url' => $redirectUrl,
            ];
        });

        $snapshot = $this->hydrateCheckoutResult((int) $result['order_id']);
        $this->logger->info('Shop checkout created', [
            'user_id' => $userId,
            'session_token' => $this->sessionTokenPreview($context['session_token']),
            'cart_id' => $context['cart']['id'] ?? null,
            'order_code' => $snapshot['order']['order_code'] ?? null,
            'payment_method' => $snapshot['payment']['payment_method'] ?? null,
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return [
            'status' => 201,
            'payload' => array_merge($snapshot, ['idempotent_replay' => false]),
        ];
    }

    private function loadCheckoutContext(?int $userId, ?string $sessionToken): array
    {
        $cartResult = $this->cartService->getCart($userId, $sessionToken);
        if (isset($cartResult['errors'])) {
            throw new \RuntimeException('Cart context could not be resolved.');
        }

        $cartPayload = $cartResult['data']['cart'] ?? $this->emptyCartPayload($userId);
        $resolvedSessionToken = $cartResult['session_token'] ?? $sessionToken;

        return [
            'cart_payload' => $cartPayload,
            'cart' => $this->resolveActiveCartRecord($cartPayload),
            'active_order' => $this->buildActiveOrderPayload($userId, $resolvedSessionToken),
            'session_token' => $resolvedSessionToken,
            'session_cookie_expires_at' => (int) ($cartResult['session_cookie_expires_at'] ?? 0),
        ];
    }

    private function resolveActiveCartRecord(array $cartPayload): ?array
    {
        $cartId = (int) ($cartPayload['id'] ?? 0);
        if ($cartId <= 0) {
            return null;
        }

        $cart = $this->carts->findById($cartId);
        if ($cart === null || strtolower((string) ($cart['status'] ?? '')) !== 'active') {
            return null;
        }

        return $cart;
    }

    private function buildActiveOrderPayload(?int $userId, ?string $sessionToken): ?array
    {
        $pendingOrder = null;
        if ($userId !== null && $userId > 0) {
            $pendingOrder = $this->orders->findActivePendingOrderByUser($userId);
        }
        if ($pendingOrder === null && is_string($sessionToken) && trim($sessionToken) !== '') {
            $pendingOrder = $this->orders->findActivePendingOrderBySession($sessionToken);
        }
        if ($pendingOrder === null) {
            return null;
        }

        $snapshot = $this->hydrateCheckoutResult((int) ($pendingOrder['id'] ?? 0));
        if (($snapshot['order']['status'] ?? null) !== 'pending') {
            return null;
        }

        return [
            'resume_available' => true,
            'resume_target' => $snapshot['next_step'] === 'redirect' ? 'payment' : 'counter',
            'resume_expires_at' => $snapshot['order']['payment_due_at'] ?? null,
            'redirect_url' => $snapshot['redirect_url'] ?? null,
            'order' => $snapshot['order'],
            'payment' => $snapshot['payment'],
        ];
    }

    private function hydrateCheckoutResult(int $orderId): array
    {
        $header = $this->orders->findOrderHeaderById($orderId);
        if ($header === null) {
            throw new \RuntimeException('Checkout order was not found.');
        }

        $detailRows = $this->details->listByOrderIds([$orderId]);
        $payment = $this->payments->findLatestShopPaymentByOrderId($orderId) ?: [];

        return [
            'order' => $this->formatShopOrderDetail($header, $detailRows),
            'payment' => $this->formatPayment($payment),
            'redirect_url' => $payment['checkout_url'] ?? null,
            'redirect_expires_at' => $header['payment_due_at'] ?? null,
            'next_step' => strtolower((string) ($payment['payment_method'] ?? '')) === 'vnpay' ? 'redirect' : 'counter',
        ];
    }

    private function buildOrderLineItems(array $items): array
    {
        $lineItems = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            $unitPrice = round((float) ($item['unit_price'] ?? 0), 2);
            $currency = strtoupper(trim((string) ($item['currency'] ?? $this->currency())));
            $name = trim((string) ($item['name'] ?? ''));

            if ($productId <= 0 || $quantity <= 0 || $unitPrice < 0 || $name === '') {
                throw new ShopCheckoutDomainException([
                    'cart' => ['Cart contains invalid product data. Please refresh the cart and try again.'],
                ], 409);
            }

            if ($currency !== $this->currency()) {
                throw new ShopCheckoutDomainException([
                    'currency' => ['Shop checkout only supports a single checkout currency.'],
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

        if ($lineItems === []) {
            throw new ShopCheckoutDomainException([
                'cart' => ['Your cart is empty.'],
            ], 409);
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

    private function buildPaymentPayload(
        int $orderId,
        string $orderCode,
        array $data,
        float $totalPrice,
        string $createdAt
    ): array {
        return [
            'shop_order_id' => $orderId,
            'payment_method' => $data['payment_method'],
            'payment_status' => 'pending',
            'amount' => $totalPrice,
            'currency' => $this->currency(),
            'transaction_code' => $this->generateTransactionCode(),
            'provider_order_ref' => $orderCode,
            'provider_message' => $data['payment_method'] === 'cash'
                ? 'Awaiting payment confirmation from the shop counter.'
                : 'Pending redirect to VNPay.',
            'idempotency_key' => $data['idempotency_key'],
            'initiated_at' => $createdAt,
            'payment_date' => $createdAt,
        ];
    }

    private function reserveInventory(array $lineItems): void
    {
        foreach ($lineItems as $lineItem) {
            if ((int) ($lineItem['track_inventory'] ?? 0) !== 1) {
                continue;
            }

            if (!$this->products->decrementTrackedInventory((int) $lineItem['product_id'], (int) $lineItem['quantity'])) {
                throw new ShopCheckoutDomainException([
                    'stock' => [
                        sprintf('%s no longer has enough stock to complete checkout.', $lineItem['product_name_snapshot']),
                    ],
                ], 409);
            }
        }
    }

    private function formatFulfillmentMethods(): array
    {
        $labels = [
            'pickup' => [
                'label' => 'Counter Pickup',
                'description' => 'Receive your order at the cinema shop counter.',
            ],
            'delivery' => [
                'label' => 'Delivery',
                'description' => 'Ship the order to the delivery address entered below.',
            ],
        ];

        return array_map(function (string $code) use ($labels): array {
            $meta = $labels[$code] ?? [
                'label' => ucfirst($code),
                'description' => 'Shop fulfillment option.',
            ];

            return [
                'code' => $code,
                'label' => $meta['label'],
                'description' => $meta['description'],
            ];
        }, $this->validator->fulfillmentMethods());
    }

    private function formatAvailablePaymentMethods(): array
    {
        $allowedMap = $this->validator->paymentMethodAllowedFulfillmentMap();

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
        foreach ($paymentMethods as $method) {
            if (in_array('pickup', $method['allowed_fulfillment_methods'] ?? [], true)) {
                return $method['code'] ?? null;
            }
        }

        return $paymentMethods[0]['code'] ?? null;
    }

    private function assertVnpayReady(array $availableMethods): void
    {
        $vnpayConfig = $this->paymentConfig['vnpay'] ?? [];
        if (empty($vnpayConfig['enabled'])) {
            throw new ShopCheckoutDomainException([
                'payment_method' => ['VNPay is disabled.'],
            ], 409);
        }

        if (!isset($availableMethods['vnpay'])) {
            throw new ShopCheckoutDomainException([
                'payment_method' => ['VNPay is not available right now.'],
            ], 409);
        }

        if (!$this->gateway->isConfigured()) {
            throw new ShopCheckoutDomainException([
                'payment_method' => ['VNPay credentials are not configured.'],
            ], 503);
        }
    }

    private function buildVnpayPayload(
        string $orderCode,
        float $totalPrice,
        array $requestContext,
        string $paymentDueAt
    ): array {
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
            'vnp_OrderInfo' => 'Thanh toan don hang shop ' . $orderCode,
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

    private function generateOrderCode(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = 'SHP-' . strtoupper(bin2hex(random_bytes(5)));
            if (!$this->orders->orderCodeExists($code)) {
                return $code;
            }
        }

        throw new \RuntimeException('Failed to generate unique shop order code.');
    }

    private function generateTransactionCode(): string
    {
        return 'PAY-' . strtoupper(bin2hex(random_bytes(6)));
    }

    private function formatPayment(array $payment): array
    {
        return [
            'id' => isset($payment['id']) ? (int) $payment['id'] : null,
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

    private function emptyCartPayload(?int $userId): array
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

    private function sessionTokenPreview(?string $value): ?string
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
    ): array
    {
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
