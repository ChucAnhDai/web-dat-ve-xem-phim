<?php

namespace App\Services;

use App\Core\Logger;
use App\Repositories\OrderDetailRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ShopOrderRepository;
use App\Repositories\TicketOrderRepository;
use App\Services\Concerns\FormatsShopOrderData;
use App\Services\Concerns\FormatsTicketData;
use App\Validators\CustomerOrderAccessValidator;
use Throwable;

class CustomerOrderService
{
    use FormatsShopOrderData;
    use FormatsTicketData;

    private TicketOrderRepository $ticketOrders;
    private ShopOrderRepository $shopOrders;
    private OrderDetailRepository $details;
    private PaymentRepository $payments;
    private CustomerOrderAccessValidator $validator;
    private ShopOrderLifecycleService $shopLifecycle;
    private TicketLifecycleService $ticketLifecycle;
    private Logger $logger;

    public function __construct(
        ?TicketOrderRepository $ticketOrders = null,
        ?ShopOrderRepository $shopOrders = null,
        ?OrderDetailRepository $details = null,
        ?PaymentRepository $payments = null,
        ?CustomerOrderAccessValidator $validator = null,
        ?ShopOrderLifecycleService $shopLifecycle = null,
        ?TicketLifecycleService $ticketLifecycle = null,
        ?Logger $logger = null
    ) {
        $this->ticketOrders = $ticketOrders ?? new TicketOrderRepository();
        $this->shopOrders = $shopOrders ?? new ShopOrderRepository();
        $this->details = $details ?? new OrderDetailRepository();
        $this->payments = $payments ?? new PaymentRepository();
        $this->validator = $validator ?? new CustomerOrderAccessValidator();
        $this->logger = $logger ?? new Logger();
        $this->shopLifecycle = $shopLifecycle ?? new ShopOrderLifecycleService();
        $this->ticketLifecycle = $ticketLifecycle ?? new TicketLifecycleService();
    }

    public function listMyOrders(int $userId, array $filters): array
    {
        if ($userId <= 0) {
            return $this->error(['auth' => ['Authentication is required.']], 401);
        }

        $normalized = $this->validator->normalizeFilters($filters);

        try {
            $this->shopLifecycle->runMaintenance();
            $this->ticketLifecycle->runMaintenance();

            $shopPage = $this->shopOrders->paginateMemberOrders($userId, [
                'search' => $normalized['search'],
                'page' => 1,
                'per_page' => 100,
            ]);
            $ticketPage = $this->ticketOrders->paginateUserOrders($userId, [
                'search' => $normalized['search'],
                'page' => 1,
                'per_page' => 100,
            ]);

            $orders = $this->mergeOrders($shopPage['items'] ?? [], $ticketPage['items'] ?? []);
        } catch (Throwable $exception) {
            $this->logger->error('Customer order history load failed', [
                'user_id' => $userId,
                'filters' => $normalized,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to load your orders.']], 500);
        }

        $filtered = $this->filterOrders($orders, $normalized['status']);
        $paged = $this->paginateOrders($filtered, $normalized['page'], $normalized['per_page']);

        return $this->success([
            'source' => 'member',
            'items' => $paged['items'],
            'meta' => $paged['meta'],
            'summary' => $this->summarizeOrders($filtered),
            'filters' => $normalized,
        ]);
    }

    public function lookupOrder(array $payload, int $userId = 0): array
    {
        $validation = $this->validator->validateLookupPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $lookup = $validation['data'];

        try {
            $this->shopLifecycle->runMaintenance();
            $this->ticketLifecycle->runMaintenance();

            $memberBlocked = false;
            $shopHeader = $this->shopOrders->findOrderHeaderByCode((string) $lookup['order_code']);
            $ticketHeader = $this->ticketOrders->findOrderHeaderByCode((string) $lookup['order_code']);

            $shopAllowed = $this->isLookupAllowed($shopHeader, $lookup, $userId, $memberBlocked);
            $ticketAllowed = $this->isLookupAllowed($ticketHeader, $lookup, $userId, $memberBlocked);

            if (!$shopAllowed && !$ticketAllowed) {
                if ($memberBlocked) {
                    return $this->error([
                        'lookup' => ['This order belongs to a member account. Sign in with the matching account to continue.'],
                    ], 403);
                }

                return $this->error([
                    'lookup' => ['No order matched the supplied order code and contact details.'],
                ], 404);
            }

            $order = $this->buildAggregateOrder(
                $shopAllowed ? $this->loadShopOrderDetail($shopHeader) : null,
                $ticketAllowed ? $this->loadTicketOrderDetail($ticketHeader) : null
            );
        } catch (Throwable $exception) {
            $this->logger->error('Customer order lookup failed', [
                'order_code' => $lookup['order_code'],
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to look up the order.']], 500);
        }

        return $this->success([
            'source' => 'lookup',
            'order' => $order,
        ]);
    }

    private function mergeOrders(array $shopHeaders, array $ticketHeaders): array
    {
        $grouped = [];

        foreach ($shopHeaders as $header) {
            $detail = $this->loadShopOrderDetail($header);
            $code = strtoupper((string) ($detail['order_code'] ?? ''));
            $grouped[$code]['shop'] = $detail;
        }

        foreach ($ticketHeaders as $header) {
            $detail = $this->loadTicketOrderDetail($header);
            $code = strtoupper((string) ($detail['order_code'] ?? ''));
            $grouped[$code]['ticket'] = $detail;
        }

        $orders = [];
        foreach ($grouped as $parts) {
            $orders[] = $this->buildAggregateOrder($parts['shop'] ?? null, $parts['ticket'] ?? null);
        }

        usort($orders, static function (array $left, array $right): int {
            $leftDate = (string) ($left['order_date'] ?? '');
            $rightDate = (string) ($right['order_date'] ?? '');
            if ($leftDate === $rightDate) {
                return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
            }

            return strcmp($rightDate, $leftDate);
        });

        return $orders;
    }

    private function loadShopOrderDetail(?array $header): ?array
    {
        if ($header === null) {
            return null;
        }

        $orderId = (int) ($header['id'] ?? 0);
        if ($orderId <= 0) {
            return null;
        }

        return $this->formatShopOrderDetail($header, $this->details->listByOrderIds([$orderId]));
    }

    private function loadTicketOrderDetail(?array $header): ?array
    {
        if ($header === null) {
            return null;
        }

        $orderId = (int) ($header['id'] ?? 0);
        if ($orderId <= 0) {
            return null;
        }

        return $this->formatOrderDetail($header, $this->ticketOrders->listOrderContextRowsByOrderIds([$orderId]));
    }

    private function buildAggregateOrder(?array $shopOrder, ?array $ticketOrder): array
    {
        $payment = $this->payments->findLatestByOrderIds(
            $ticketOrder['id'] ?? null,
            $shopOrder['id'] ?? null
        ) ?? [];
        $scope = $shopOrder !== null && $ticketOrder !== null
            ? 'mixed'
            : ($ticketOrder !== null ? 'ticket' : 'shop');
        $status = $this->resolveAggregateStatus($shopOrder, $ticketOrder, $payment);
        $paymentStatus = strtolower((string) ($payment['payment_status'] ?? ($shopOrder['payment_status'] ?? $ticketOrder['payment_status'] ?? 'pending')));
        $paymentMethod = $payment['payment_method'] ?? $shopOrder['payment_method'] ?? $ticketOrder['payment_method'] ?? null;
        $paymentDueAt = $shopOrder['payment_due_at'] ?? $ticketOrder['hold_expires_at'] ?? null;

        return [
            'id' => $shopOrder['id'] ?? $ticketOrder['id'] ?? 0,
            'order_code' => $shopOrder['order_code'] ?? $ticketOrder['order_code'] ?? ($payment['provider_order_ref'] ?? null),
            'order_scope' => $scope,
            'status' => $status,
            'status_group' => $this->statusGroup($scope, $status, $paymentStatus),
            'user_id' => $shopOrder['user_id'] ?? $ticketOrder['user_id'] ?? null,
            'contact_name' => $shopOrder['contact_name'] ?? $ticketOrder['contact_name'] ?? null,
            'contact_email' => $shopOrder['contact_email'] ?? $ticketOrder['contact_email'] ?? null,
            'contact_phone' => $shopOrder['contact_phone'] ?? $ticketOrder['contact_phone'] ?? null,
            'fulfillment_method' => $shopOrder['fulfillment_method'] ?? ($ticketOrder !== null ? 'e_ticket' : null),
            'shipping_address' => $shopOrder['shipping_address'] ?? [
                'address_text' => null,
                'city' => null,
                'district' => null,
            ],
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'transaction_code' => $payment['transaction_code'] ?? $shopOrder['transaction_code'] ?? $ticketOrder['transaction_code'] ?? null,
            'total_price' => round((float) ($shopOrder['total_price'] ?? 0) + (float) ($ticketOrder['total_price'] ?? 0), 2),
            'currency' => $shopOrder['currency'] ?? $ticketOrder['currency'] ?? 'VND',
            'item_count' => (int) ($shopOrder['item_count'] ?? 0),
            'seat_count' => (int) ($ticketOrder['seat_count'] ?? 0),
            'order_date' => $shopOrder['order_date'] ?? $ticketOrder['order_date'] ?? null,
            'payment_due_at' => $paymentDueAt,
            'shipping_amount' => isset($shopOrder['shipping_amount']) ? (float) $shopOrder['shipping_amount'] : 0.0,
            'redirect_url' => $payment['checkout_url'] ?? ($shopOrder['redirect_url'] ?? null),
            'expires_in_seconds' => $this->remainingSeconds($paymentDueAt),
            'requires_payment_resume' => strtolower((string) $paymentMethod) === 'vnpay'
                && $paymentStatus === 'pending'
                && trim((string) ($payment['checkout_url'] ?? '')) !== ''
                && $this->remainingSeconds($paymentDueAt) > 0,
            'contains_products' => $shopOrder !== null,
            'contains_tickets' => $ticketOrder !== null,
            'is_guest_order' => (int) ($shopOrder['user_id'] ?? $ticketOrder['user_id'] ?? 0) <= 0,
            'shipping_summary' => $shopOrder !== null ? $this->shippingSummary($shopOrder) : 'E-ticket delivery',
            'product_order_id' => $shopOrder['id'] ?? null,
            'ticket_order_id' => $ticketOrder['id'] ?? null,
            'can_cancel' => $scope === 'shop'
                && $status === 'pending'
                && !in_array($paymentStatus, ['success', 'processing', 'refunded'], true),
            'items' => $shopOrder['items'] ?? [],
            'tickets' => $ticketOrder['tickets'] ?? [],
            'preview_items' => $this->previewItems($shopOrder, $ticketOrder),
        ];
    }

    private function isLookupAllowed(?array $header, array $lookup, int $userId, bool &$memberBlocked): bool
    {
        if ($header === null || !$this->lookupMatchesContact($header, $lookup)) {
            return false;
        }

        $ownerId = (int) ($header['user_id'] ?? 0);
        if ($ownerId > 0 && $ownerId !== $userId) {
            $memberBlocked = true;
            return false;
        }

        return true;
    }

    private function lookupMatchesContact(array $header, array $lookup): bool
    {
        $storedEmail = strtolower(trim((string) ($header['contact_email'] ?? '')));
        $lookupEmail = strtolower(trim((string) ($lookup['contact_email'] ?? '')));
        if ($storedEmail === '' || !hash_equals($storedEmail, $lookupEmail)) {
            return false;
        }

        $storedPhone = preg_replace('/\D+/', '', (string) ($header['contact_phone'] ?? '')) ?: '';
        $lookupPhone = preg_replace('/\D+/', '', (string) ($lookup['contact_phone'] ?? '')) ?: '';

        return $storedPhone !== '' && $lookupPhone !== '' && hash_equals($storedPhone, $lookupPhone);
    }

    private function filterOrders(array $orders, string $status): array
    {
        if ($status === 'all') {
            return $orders;
        }

        return array_values(array_filter($orders, static function (array $order) use ($status): bool {
            return ($order['status_group'] ?? 'active') === $status;
        }));
    }

    private function summarizeOrders(array $orders): array
    {
        $summary = [
            'total_orders' => count($orders),
            'pending_orders' => 0,
            'active_orders' => 0,
            'completed_orders' => 0,
            'issue_orders' => 0,
        ];

        foreach ($orders as $order) {
            $group = $order['status_group'] ?? 'active';
            if ($group === 'pending') {
                $summary['pending_orders'] += 1;
            } elseif ($group === 'active') {
                $summary['active_orders'] += 1;
            } elseif ($group === 'completed') {
                $summary['completed_orders'] += 1;
            } elseif ($group === 'issue') {
                $summary['issue_orders'] += 1;
            }
        }

        return $summary;
    }

    private function paginateOrders(array $orders, int $page, int $perPage): array
    {
        $total = count($orders);
        $offset = ($page - 1) * $perPage;

        return [
            'items' => array_slice($orders, $offset, $perPage),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    private function previewItems(?array $shopOrder, ?array $ticketOrder): array
    {
        $items = [];

        foreach (array_slice($shopOrder['items'] ?? [], 0, 2) as $item) {
            $item['preview_type'] = 'product';
            $items[] = $item;
        }

        if ($ticketOrder !== null) {
            $items[] = [
                'preview_type' => 'ticket',
                'label' => $ticketOrder['movie_title'] ?? 'Movie tickets',
                'summary' => implode(', ', array_slice($ticketOrder['seats'] ?? [], 0, 3)),
                'line_total' => (float) ($ticketOrder['total_price'] ?? 0),
                'currency' => $ticketOrder['currency'] ?? 'VND',
                'primary_image_url' => $ticketOrder['poster_url'] ?? null,
                'primary_image_alt' => $ticketOrder['movie_title'] ?? 'Movie poster',
            ];
        }

        return array_slice($items, 0, 3);
    }

    private function resolveAggregateStatus(?array $shopOrder, ?array $ticketOrder, array $payment): string
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

    private function statusGroup(string $scope, string $status, string $paymentStatus): string
    {
        if (in_array($status, ['cancelled', 'expired', 'refunded'], true)
            || in_array($paymentStatus, ['failed', 'cancelled', 'expired', 'refunded'], true)) {
            return 'issue';
        }

        if ($status === 'pending') {
            return 'pending';
        }

        if ($scope === 'ticket' && $status === 'paid') {
            return 'completed';
        }

        if (in_array($status, ['confirmed', 'preparing', 'ready', 'shipping'], true)) {
            return 'active';
        }

        return 'completed';
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

    private function remainingSeconds(?string $value): int
    {
        $normalized = trim((string) ($value ?? ''));
        if ($normalized === '') {
            return 0;
        }

        $timestamp = strtotime($normalized);
        if ($timestamp === false) {
            return 0;
        }

        return max(0, $timestamp - time());
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
