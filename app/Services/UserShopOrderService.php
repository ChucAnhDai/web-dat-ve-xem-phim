<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\OrderDetailRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ShopOrderRepository;
use App\Services\Concerns\FormatsShopOrderData;
use App\Support\AssetUrlResolver;
use App\Validators\ShopOrderAccessValidator;
use PDO;
use Throwable;

class UserShopOrderService
{
    use FormatsShopOrderData;

    private PDO $db;
    private ShopOrderRepository $orders;
    private OrderDetailRepository $details;
    private PaymentRepository $payments;
    private ShopOrderAccessValidator $validator;
    private ShopOrderLifecycleService $lifecycle;
    private Logger $logger;
    private AssetUrlResolver $assetUrlResolver;

    public function __construct(
        ?PDO $db = null,
        ?ShopOrderRepository $orders = null,
        ?OrderDetailRepository $details = null,
        ?PaymentRepository $payments = null,
        ?ShopOrderAccessValidator $validator = null,
        ?ShopOrderLifecycleService $lifecycle = null,
        ?Logger $logger = null,
        ?AssetUrlResolver $assetUrlResolver = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->orders = $orders ?? new ShopOrderRepository($this->db);
        $this->details = $details ?? new OrderDetailRepository($this->db);
        $this->payments = $payments ?? new PaymentRepository($this->db);
        $this->validator = $validator ?? new ShopOrderAccessValidator();
        $this->logger = $logger ?? new Logger();
        $this->assetUrlResolver = $assetUrlResolver ?? new AssetUrlResolver((string) (getenv('APP_URL') ?: ''));
        $this->lifecycle = $lifecycle ?? new ShopOrderLifecycleService(
            $this->db,
            $this->orders,
            $this->details,
            null,
            $this->payments,
            $this->logger
        );
    }

    public function cartCookieName(): string
    {
        return $this->validator->cartCookieName();
    }

    public function listMyOrders(int $userId, array $filters): array
    {
        if ($userId <= 0) {
            return $this->error(['auth' => ['Authentication is required.']], 401);
        }

        $normalized = $this->validator->normalizeOrderFilters($filters);

        try {
            $this->lifecycle->runMaintenance();
            $page = $this->orders->paginateMemberOrders($userId, $normalized);
            $summary = $this->orders->summarizeMemberOrders($userId, $normalized);
            $detailLookup = $this->detailLookup($page['items']);
        } catch (Throwable $exception) {
            $this->logger->error('Member shop order history load failed', [
                'user_id' => $userId,
                'filters' => $normalized,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to load your shop orders.']], 500);
        }

        return $this->success([
            'source' => 'member',
            'items' => array_map(function (array $header) use ($detailLookup): array {
                return $this->decorateSummaryOrder(
                    $this->formatShopOrderSummary($header),
                    $detailLookup[(int) ($header['id'] ?? 0)] ?? []
                );
            }, $page['items']),
            'meta' => $this->paginationMeta($page),
            'summary' => $summary,
            'filters' => $normalized,
        ]);
    }

    public function getMyOrder(int $userId, int $orderId): array
    {
        if ($userId <= 0) {
            return $this->error(['auth' => ['Authentication is required.']], 401);
        }

        try {
            $this->lifecycle->runMaintenance();
            $header = $this->orders->findOrderHeaderById($orderId);
            if (!$this->isMemberOwner($header, $userId)) {
                return $this->error(['order' => ['Shop order was not found.']], 404);
            }

            $detailRows = $this->details->listByOrderIds([$orderId]);
        } catch (Throwable $exception) {
            $this->logger->error('Member shop order detail load failed', [
                'user_id' => $userId,
                'order_id' => $orderId,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to load your shop order.']], 500);
        }

        return $this->success([
            'source' => 'member',
            'order' => $this->decorateDetailOrder($this->formatShopOrderDetail($header, $detailRows)),
        ]);
    }

    public function cancelMyOrder(int $userId, int $orderId): array
    {
        if ($userId <= 0) {
            return $this->error(['auth' => ['Authentication is required.']], 401);
        }

        try {
            $this->lifecycle->runMaintenance();
            $header = $this->orders->findOrderHeaderById($orderId);
            if (!$this->isMemberOwner($header, $userId)) {
                return $this->error(['order' => ['Shop order was not found.']], 404);
            }

            return $this->performCancellation($header, 'member', [
                'actor_user_id' => $userId,
            ]);
        } catch (UserShopOrderAccessException $exception) {
            $this->logger->info('Member shop order cancellation blocked', [
                'user_id' => $userId,
                'order_id' => $orderId,
                'errors' => $exception->errors(),
            ]);

            return $this->error($exception->errors(), $exception->status());
        } catch (Throwable $exception) {
            $this->logger->error('Member shop order cancellation failed', [
                'user_id' => $userId,
                'order_id' => $orderId,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to cancel your shop order.']], 500);
        }
    }

    public function listSessionOrders(?string $sessionToken, array $filters): array
    {
        $normalized = $this->validator->normalizeOrderFilters($filters);
        $normalizedToken = $this->normalizeSessionToken($sessionToken);
        if ($normalizedToken === null) {
            return $this->success([
                'source' => 'session',
                'session_attached' => false,
                'items' => [],
                'meta' => $this->paginationMeta(['page' => 1, 'per_page' => $normalized['per_page'], 'total' => 0]),
                'summary' => $this->emptySummary(),
                'filters' => $normalized,
            ]);
        }

        try {
            $this->lifecycle->runMaintenance();
            $page = $this->orders->paginateSessionOrders($normalizedToken, $normalized);
            $summary = $this->orders->summarizeSessionOrders($normalizedToken, $normalized);
            $detailLookup = $this->detailLookup($page['items']);
        } catch (Throwable $exception) {
            $this->logger->error('Guest session shop order history load failed', [
                'session_token' => $this->sessionTokenPreview($normalizedToken),
                'filters' => $normalized,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to load guest orders for this browser.']], 500);
        }

        return $this->success([
            'source' => 'session',
            'session_attached' => true,
            'items' => array_map(function (array $header) use ($detailLookup): array {
                return $this->decorateSummaryOrder(
                    $this->formatShopOrderSummary($header),
                    $detailLookup[(int) ($header['id'] ?? 0)] ?? []
                );
            }, $page['items']),
            'meta' => $this->paginationMeta($page),
            'summary' => $summary,
            'filters' => $normalized,
        ]);
    }

    public function getSessionOrder(?string $sessionToken, int $orderId): array
    {
        $normalizedToken = $this->normalizeSessionToken($sessionToken);
        if ($normalizedToken === null) {
            return $this->error(['order' => ['Guest order was not found.']], 404);
        }

        try {
            $this->lifecycle->runMaintenance();
            $header = $this->orders->findOrderHeaderById($orderId);
            if (!$this->isSessionOwner($header, $normalizedToken)) {
                return $this->error(['order' => ['Guest order was not found.']], 404);
            }

            $detailRows = $this->details->listByOrderIds([$orderId]);
        } catch (Throwable $exception) {
            $this->logger->error('Guest session shop order detail load failed', [
                'session_token' => $this->sessionTokenPreview($normalizedToken),
                'order_id' => $orderId,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to load the guest order.']], 500);
        }

        return $this->success([
            'source' => 'session',
            'order' => $this->decorateDetailOrder($this->formatShopOrderDetail($header, $detailRows)),
        ]);
    }

    public function cancelSessionOrder(?string $sessionToken, int $orderId): array
    {
        $normalizedToken = $this->normalizeSessionToken($sessionToken);
        if ($normalizedToken === null) {
            return $this->error(['order' => ['Guest order was not found.']], 404);
        }

        try {
            $this->lifecycle->runMaintenance();
            $header = $this->orders->findOrderHeaderById($orderId);
            if (!$this->isSessionOwner($header, $normalizedToken)) {
                return $this->error(['order' => ['Guest order was not found.']], 404);
            }

            return $this->performCancellation($header, 'session', [
                'session_token' => $this->sessionTokenPreview($normalizedToken),
            ]);
        } catch (UserShopOrderAccessException $exception) {
            $this->logger->info('Guest session shop order cancellation blocked', [
                'session_token' => $this->sessionTokenPreview($normalizedToken),
                'order_id' => $orderId,
                'errors' => $exception->errors(),
            ]);

            return $this->error($exception->errors(), $exception->status());
        } catch (Throwable $exception) {
            $this->logger->error('Guest session shop order cancellation failed', [
                'session_token' => $this->sessionTokenPreview($normalizedToken),
                'order_id' => $orderId,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to cancel the guest order.']], 500);
        }
    }

    public function lookupGuestOrder(array $payload, int $userId = 0): array
    {
        $validation = $this->validator->validateLookupPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $lookup = $validation['data'];

        try {
            $this->lifecycle->runMaintenance();
            $header = $this->orders->findOrderHeaderByCode((string) $lookup['order_code']);
            $lookupDisposition = $this->resolveLookupDisposition($header, $lookup, $userId);
            if ($lookupDisposition !== 'guest_match') {
                return $this->lookupAccessError($lookupDisposition, $header, $lookup, 'view', $userId);
            }

            $detailRows = $this->details->listByOrderIds([(int) ($header['id'] ?? 0)]);
        } catch (Throwable $exception) {
            $this->logger->error('Guest shop order lookup failed', [
                'order_code' => $lookup['order_code'],
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to look up the guest order.']], 500);
        }

        return $this->success([
            'source' => 'lookup',
            'order' => $this->decorateDetailOrder($this->formatShopOrderDetail($header, $detailRows)),
        ]);
    }

    public function cancelGuestOrder(array $payload): array
    {
        $validation = $this->validator->validateLookupPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $lookup = $validation['data'];

        try {
            $this->lifecycle->runMaintenance();
            $header = $this->orders->findOrderHeaderByCode((string) $lookup['order_code']);
            $lookupDisposition = $this->resolveLookupDisposition($header, $lookup);
            if ($lookupDisposition !== 'guest_match') {
                return $this->lookupAccessError($lookupDisposition, $header, $lookup, 'manage');
            }

            return $this->performCancellation($header, 'lookup', [
                'lookup_order_code' => $lookup['order_code'],
                'contact_email' => $lookup['contact_email'],
                'contact_phone' => $lookup['contact_phone'],
            ]);
        } catch (UserShopOrderAccessException $exception) {
            $this->logger->info('Guest shop order cancellation blocked', [
                'order_code' => $lookup['order_code'],
                'errors' => $exception->errors(),
            ]);

            return $this->error($exception->errors(), $exception->status());
        } catch (Throwable $exception) {
            $this->logger->error('Guest shop order cancellation failed', [
                'order_code' => $lookup['order_code'],
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to cancel the guest order.']], 500);
        }
    }

    protected function resolveShopAssetUrl(?string $value): ?string
    {
        return $this->assetUrlResolver->resolve($value);
    }

    private function performCancellation(array $header, string $source, array $context = []): array
    {
        $orderId = (int) ($header['id'] ?? 0);
        if ($orderId <= 0) {
            return $this->error(['order' => ['Shop order was not found.']], 404);
        }

        $currentOrder = $this->decorateDetailOrder($this->formatShopOrderDetail($header, $this->details->listByOrderIds([$orderId])));
        if (!$currentOrder['can_cancel']) {
            throw new UserShopOrderAccessException([
                'order' => ['This shop order can no longer be cancelled from the customer side.'],
            ], 409);
        }

        $payment = $this->payments->findLatestShopPaymentByOrderId($orderId);
        if ($payment === null) {
            throw new UserShopOrderAccessException([
                'payment' => ['The payment snapshot for this order is missing.'],
            ], 409);
        }

        $paymentStatus = strtolower((string) ($payment['payment_status'] ?? 'pending'));
        if (in_array($paymentStatus, ['success', 'processing', 'refunded'], true)) {
            throw new UserShopOrderAccessException([
                'payment' => ['Paid shop orders require a refund workflow before cancellation.'],
            ], 409);
        }

        $this->transactional(function () use ($orderId, $payment, $currentOrder): void {
            $now = date('Y-m-d H:i:s');

            $this->lifecycle->restoreInventoryReservation($orderId);
            if (strtolower((string) ($payment['payment_status'] ?? 'pending')) === 'pending') {
                $this->payments->markPaymentIssue((int) ($payment['id'] ?? 0), [
                    'payment_status' => 'cancelled',
                    'provider_message' => 'Shop order cancelled by customer.',
                    'failed_at' => $now,
                ]);
            }

            if (!$this->orders->updateOrderStatus($orderId, 'cancelled', [
                'cancelled_at' => $now,
                'payment_due_at' => null,
            ], [strtolower((string) ($currentOrder['status'] ?? 'pending'))])) {
                throw new UserShopOrderAccessException([
                    'order' => ['The order changed while cancellation was in progress. Please refresh and try again.'],
                ], 409);
            }
        });

        $updatedHeader = $this->orders->findOrderHeaderById($orderId);
        if ($updatedHeader === null) {
            throw new \RuntimeException('Shop order disappeared after cancellation.');
        }

        $updatedOrder = $this->decorateDetailOrder($this->formatShopOrderDetail(
            $updatedHeader,
            $this->details->listByOrderIds([$orderId])
        ));

        $this->logger->info('Customer shop order cancelled', array_filter([
            'source' => $source,
            'order_id' => $orderId,
            'order_code' => $updatedOrder['order_code'] ?? null,
            'actor_user_id' => $context['actor_user_id'] ?? null,
            'lookup_order_code' => $context['lookup_order_code'] ?? null,
            'contact_email' => $context['contact_email'] ?? null,
            'contact_phone' => $context['contact_phone'] ?? null,
            'session_token' => $context['session_token'] ?? null,
        ], static function ($value) {
            return $value !== null && $value !== '';
        }));

        return $this->success([
            'source' => $source,
            'order' => $updatedOrder,
        ]);
    }

    private function detailLookup(array $headers): array
    {
        $orderIds = array_values(array_filter(array_map(static function (array $header): int {
            return (int) ($header['id'] ?? 0);
        }, $headers)));
        $grouped = [];

        foreach ($this->details->listByOrderIds($orderIds) as $row) {
            $grouped[(int) ($row['order_id'] ?? 0)][] = $row;
        }

        return $grouped;
    }

    private function decorateSummaryOrder(array $order, array $detailRows): array
    {
        $order = $this->decorateOrder($order);
        $order['preview_items'] = array_slice(array_map([$this, 'formatShopOrderItemRow'], $detailRows), 0, 3);

        return $order;
    }

    private function decorateDetailOrder(array $order): array
    {
        return $this->decorateOrder($order);
    }

    private function decorateOrder(array $order): array
    {
        $status = strtolower((string) ($order['status'] ?? ''));
        $paymentStatus = strtolower((string) ($order['payment_status'] ?? 'pending'));
        $paymentMethod = strtolower((string) ($order['payment_method'] ?? ''));
        $paymentDueAt = $order['payment_due_at'] ?? null;

        $order['is_guest_order'] = !isset($order['user_id']) || (int) ($order['user_id'] ?? 0) <= 0;
        $order['can_cancel'] = $status === 'pending' && !in_array($paymentStatus, ['success', 'processing', 'refunded'], true);
        $order['requires_payment_resume'] = $status === 'pending'
            && $paymentMethod === 'vnpay'
            && trim((string) ($order['redirect_url'] ?? '')) !== ''
            && $this->expiresAtFuture($paymentDueAt);
        $order['expires_in_seconds'] = $this->remainingSeconds($paymentDueAt);
        $order['shipping_summary'] = $this->shippingSummary($order);

        return $order;
    }

    private function isMemberOwner(?array $header, int $userId): bool
    {
        return $header !== null && (int) ($header['user_id'] ?? 0) === $userId && $userId > 0;
    }

    private function isSessionOwner(?array $header, string $sessionToken): bool
    {
        if ($header === null || (int) ($header['user_id'] ?? 0) > 0) {
            return false;
        }

        $orderSessionToken = trim((string) ($header['session_token'] ?? ''));
        if ($orderSessionToken === '' || $sessionToken === '') {
            return false;
        }

        return hash_equals($orderSessionToken, $sessionToken);
    }

    private function resolveLookupDisposition(?array $header, array $lookup, int $userId = 0): string
    {
        if (!$this->lookupMatchesContact($header, $lookup)) {
            return 'no_match';
        }

        $ownerId = (int) ($header['user_id'] ?? 0);
        if ($ownerId > 0) {
            if ($userId > 0 && $userId === $ownerId) {
                return 'guest_match';
            }

            return 'member_match';
        }

        return 'guest_match';
    }

    private function lookupMatchesContact(?array $header, array $lookup): bool
    {
        if ($header === null) {
            return false;
        }

        $headerCode = strtoupper(trim((string) ($header['order_code'] ?? '')));
        if ($headerCode === '' || !hash_equals($headerCode, strtoupper((string) ($lookup['order_code'] ?? '')))) {
            return false;
        }

        if (($lookup['contact_email'] ?? null) !== null) {
            $storedEmail = strtolower(trim((string) ($header['contact_email'] ?? '')));
            if ($storedEmail === '' || !hash_equals($storedEmail, strtolower((string) $lookup['contact_email']))) {
                return false;
            }
        }

        if (($lookup['contact_phone'] ?? null) !== null) {
            $storedPhone = $this->normalizePhoneForCompare((string) ($header['contact_phone'] ?? ''));
            $lookupPhone = $this->normalizePhoneForCompare((string) $lookup['contact_phone']);
            if ($storedPhone === '' || $lookupPhone === '' || !hash_equals($storedPhone, $lookupPhone)) {
                return false;
            }
        }

        return true;
    }

    private function lookupAccessError(string $disposition, ?array $header, array $lookup, string $intent, int $userId = 0): array
    {
        if ($disposition === 'member_match') {
            $matchedVia = [];
            if (($lookup['contact_email'] ?? null) !== null) {
                $matchedVia[] = 'email';
            }
            if (($lookup['contact_phone'] ?? null) !== null) {
                $matchedVia[] = 'phone';
            }

            $this->logger->info('Guest lookup blocked because the order belongs to a member account', array_filter([
                'intent' => $intent,
                'order_id' => (int) ($header['id'] ?? 0) ?: null,
                'order_code' => strtoupper(trim((string) ($lookup['order_code'] ?? ''))),
                'member_user_id' => (int) ($header['user_id'] ?? 0) ?: null,
                'matched_via' => $matchedVia !== [] ? implode(',', $matchedVia) : null,
            ], static function ($value) {
                return $value !== null && $value !== '';
            }));

            $message = $userId > 0
                ? 'Đơn này thuộc tài khoản thành viên khác'
                : 'Đơn này thuộc tài khoản thành viên, vui lòng đăng nhập để xem';

            return $this->error(['lookup' => [$message]], 403);
        }

        return $this->error(['lookup' => ['No guest order matched the supplied order code and contact details.']], 404);
    }

    private function shippingSummary(array $order): string
    {
        if (strtolower((string) ($order['fulfillment_method'] ?? 'pickup')) === 'pickup') {
            return 'Pickup at cinema counter';
        }

        $parts = array_filter([
            trim((string) ($order['shipping_address']['address_text'] ?? '')),
            trim((string) ($order['shipping_address']['district'] ?? '')),
            trim((string) ($order['shipping_address']['city'] ?? '')),
        ], static function (string $value): bool {
            return $value !== '';
        });

        return $parts !== [] ? implode(', ', $parts) : 'Delivery address pending';
    }

    private function normalizeSessionToken(?string $sessionToken): ?string
    {
        $normalized = trim((string) ($sessionToken ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizePhoneForCompare(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?: '';
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

    private function emptySummary(): array
    {
        return [
            'total_orders' => 0,
            'pending_orders' => 0,
            'active_orders' => 0,
            'completed_orders' => 0,
            'issue_orders' => 0,
        ];
    }

    private function sessionTokenPreview(?string $sessionToken): ?string
    {
        $normalized = trim((string) ($sessionToken ?? ''));
        if ($normalized === '') {
            return null;
        }

        return substr($normalized, 0, 12);
    }

    private function paginationMeta(array $page): array
    {
        $total = (int) ($page['total'] ?? 0);
        $perPage = max(1, (int) ($page['per_page'] ?? 20));
        $pageNumber = max(1, (int) ($page['page'] ?? 1));

        return [
            'total' => $total,
            'page' => $pageNumber,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
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
}
