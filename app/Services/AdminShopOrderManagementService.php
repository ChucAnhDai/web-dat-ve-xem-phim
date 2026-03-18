<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\OrderDetailRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ShopOrderRepository;
use App\Services\Concerns\FormatsShopOrderData;
use App\Support\AssetUrlResolver;
use App\Validators\ShopOrderAdminValidator;
use PDO;
use Throwable;

class AdminShopOrderManagementService
{
    use FormatsShopOrderData;

    private PDO $db;
    private ShopOrderRepository $orders;
    private OrderDetailRepository $details;
    private PaymentRepository $payments;
    private ShopOrderAdminValidator $validator;
    private ShopOrderLifecycleService $lifecycle;
    private Logger $logger;
    private AssetUrlResolver $assetUrlResolver;

    public function __construct(
        ?PDO $db = null,
        ?ShopOrderRepository $orders = null,
        ?OrderDetailRepository $details = null,
        ?PaymentRepository $payments = null,
        ?ShopOrderAdminValidator $validator = null,
        ?ShopOrderLifecycleService $lifecycle = null,
        ?Logger $logger = null,
        ?AssetUrlResolver $assetUrlResolver = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->orders = $orders ?? new ShopOrderRepository($this->db);
        $this->details = $details ?? new OrderDetailRepository($this->db);
        $this->payments = $payments ?? new PaymentRepository($this->db);
        $this->validator = $validator ?? new ShopOrderAdminValidator();
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

    public function listShopOrders(array $filters): array
    {
        $normalized = $this->validator->normalizeOrderFilters($filters);

        try {
            $this->lifecycle->runMaintenance();
            $page = $this->orders->paginateAdminOrders($normalized);
            $summary = $this->orders->summarizeAdminOrders($normalized);
        } catch (Throwable $exception) {
            $this->logger->error('Admin shop order list failed', [
                'filters' => $normalized,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to load shop orders.']], 500);
        }

        return $this->success([
            'items' => array_map(function (array $row): array {
                return $this->decorateOrder($this->formatShopOrderSummary($row));
            }, $page['items']),
            'meta' => $this->paginationMeta($page),
            'summary' => $summary,
            'filters' => $normalized,
        ]);
    }

    public function getShopOrder(int $orderId): array
    {
        try {
            $this->lifecycle->runMaintenance();
            $header = $this->orders->findOrderHeaderById($orderId);
            if ($header === null) {
                return $this->error(['order' => ['Shop order not found.']], 404);
            }

            $detailRows = $this->details->listByOrderIds([$orderId]);
        } catch (Throwable $exception) {
            $this->logger->error('Admin shop order detail failed', [
                'order_id' => $orderId,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to load shop order.']], 500);
        }

        return $this->success($this->decorateOrder($this->formatShopOrderDetail($header, $detailRows)));
    }

    public function listOrderDetails(array $filters): array
    {
        $normalized = $this->validator->normalizeOrderDetailFilters($filters);

        try {
            $this->lifecycle->runMaintenance();
            $page = $this->orders->paginateAdminDetails($normalized);
            $summary = $this->orders->summarizeAdminDetails($normalized);
            $queueRows = $this->orders->listFulfillmentQueue((int) $normalized['queue_limit']);
        } catch (Throwable $exception) {
            $this->logger->error('Admin shop order detail rows failed', [
                'filters' => $normalized,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to load order detail rows.']], 500);
        }

        return $this->success([
            'items' => array_map([$this, 'formatAdminDetailRow'], $page['items']),
            'meta' => $this->paginationMeta($page),
            'summary' => $summary,
            'queue' => array_map(function (array $row): array {
                return $this->decorateOrder($this->formatShopOrderSummary($row));
            }, $queueRows),
            'filters' => $normalized,
        ]);
    }

    public function updateShopOrderStatus(int $orderId, array $payload, ?int $actorId = null): array
    {
        $validation = $this->validator->validateStatusUpdatePayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $targetStatus = (string) ($validation['data']['status'] ?? '');

        try {
            $this->lifecycle->runMaintenance();
            $header = $this->orders->findOrderHeaderById($orderId);
            if ($header === null) {
                return $this->error(['order' => ['Shop order not found.']], 404);
            }

            $order = $this->decorateOrder($this->formatShopOrderSummary($header));
            if (!in_array($targetStatus, $order['allowed_next_statuses'], true)) {
                throw new AdminShopOrderManagementException([
                    'status' => ['This order cannot be moved to the selected status.'],
                ], 409);
            }

            $this->transactional(function () use ($orderId, $header, $targetStatus): void {
                $now = date('Y-m-d H:i:s');
                $payment = $this->payments->findLatestShopPaymentByOrderId($orderId);
                if ($payment === null) {
                    throw new AdminShopOrderManagementException([
                        'payment' => ['The latest payment snapshot for this order is missing.'],
                    ], 409);
                }

                $currentStatus = strtolower((string) ($header['status'] ?? ''));
                switch ($targetStatus) {
                    case 'confirmed':
                        $this->confirmOrder($orderId, $header, $payment, $now, $currentStatus);
                        break;

                    case 'cancelled':
                        $this->cancelOrder($orderId, $payment, $now, $currentStatus);
                        break;

                    case 'completed':
                        $this->updateOrderStatus($orderId, $targetStatus, [
                            'fulfilled_at' => $header['fulfilled_at'] ?: $now,
                        ], [$currentStatus]);
                        break;

                    default:
                        $this->updateOrderStatus($orderId, $targetStatus, [], [$currentStatus]);
                        break;
                }
            });

            $updatedHeader = $this->orders->findOrderHeaderById($orderId);
            if ($updatedHeader === null) {
                throw new \RuntimeException('Shop order disappeared after status update.');
            }
            $detailRows = $this->details->listByOrderIds([$orderId]);
        } catch (AdminShopOrderManagementException $exception) {
            $this->logger->info('Admin shop order transition blocked', [
                'actor_id' => $actorId,
                'order_id' => $orderId,
                'target_status' => $targetStatus,
                'errors' => $exception->errors(),
            ]);

            return $this->error($exception->errors(), $exception->status());
        } catch (Throwable $exception) {
            $this->logger->error('Admin shop order status update failed', [
                'actor_id' => $actorId,
                'order_id' => $orderId,
                'target_status' => $targetStatus,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to update shop order status.']], 500);
        }

        $updatedOrder = $this->decorateOrder($this->formatShopOrderDetail($updatedHeader, $detailRows));
        $this->logger->info('Admin shop order status updated', [
            'actor_id' => $actorId,
            'order_id' => $orderId,
            'order_code' => $updatedOrder['order_code'] ?? null,
            'status' => $updatedOrder['status'] ?? null,
            'payment_status' => $updatedOrder['payment_status'] ?? null,
        ]);

        return $this->success($updatedOrder);
    }

    protected function resolveShopAssetUrl(?string $value): ?string
    {
        return $this->assetUrlResolver->resolve($value);
    }

    private function decorateOrder(array $order): array
    {
        $paymentStatus = strtolower((string) ($order['payment_status'] ?? ''));
        $order['customer_ref'] = $this->customerReference($order);
        $order['shipping_address_summary'] = $this->shippingSummary($order);
        $order['allowed_next_statuses'] = $this->allowedTransitions($order);
        $order['is_guest_order'] = !isset($order['user_id']) || (int) ($order['user_id'] ?? 0) <= 0;
        $order['payment_is_settled'] = in_array($paymentStatus, ['success', 'refunded'], true);

        return $order;
    }

    private function formatAdminDetailRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'order_id' => (int) ($row['order_id'] ?? 0),
            'order_code' => $row['order_code'] ?? null,
            'order_status' => $row['order_status'] ?? null,
            'payment_method' => $row['payment_method'] ?? null,
            'payment_status' => $row['payment_status'] ?? null,
            'customer_ref' => $this->customerReference($row),
            'customer_name' => $row['contact_name'] ?? null,
            'product_id' => isset($row['product_id']) && $row['product_id'] !== null ? (int) $row['product_id'] : null,
            'product_slug' => $row['product_slug'] ?? null,
            'product_name' => $row['product_name_snapshot'] ?? null,
            'product_sku' => $row['product_sku_snapshot'] ?? null,
            'quantity' => (int) ($row['quantity'] ?? 0),
            'unit_price' => (float) ($row['price'] ?? 0),
            'discount_amount' => (float) ($row['discount_amount'] ?? 0),
            'line_total' => (float) ($row['line_total'] ?? 0),
            'currency' => $row['currency'] ?? 'VND',
            'fulfillment_method' => $row['fulfillment_method'] ?? null,
            'shipping_address_summary' => $this->shippingSummary($row),
            'order_date' => $row['order_date'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'primary_image_url' => $this->resolveShopAssetUrl($row['primary_image_url'] ?? null),
            'primary_image_alt' => $row['primary_image_alt'] ?? ($row['product_name_snapshot'] ?? 'Product'),
        ];
    }

    private function customerReference(array $source): string
    {
        foreach (['contact_email', 'contact_phone', 'contact_name', 'user_name'] as $field) {
            $value = trim((string) ($source[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $sessionToken = trim((string) ($source['session_token'] ?? ''));

        return $sessionToken !== '' ? substr($sessionToken, 0, 12) : 'Guest';
    }

    private function shippingSummary(array $source): string
    {
        $fulfillment = strtolower((string) ($source['fulfillment_method'] ?? ''));
        if ($fulfillment === 'pickup') {
            return 'Pickup at cinema counter';
        }

        $parts = array_filter([
            trim((string) ($source['shipping_address_text'] ?? '')),
            trim((string) ($source['shipping_district'] ?? '')),
            trim((string) ($source['shipping_city'] ?? '')),
        ], static function (string $value): bool {
            return $value !== '';
        });

        return $parts !== [] ? implode(', ', $parts) : 'Delivery address pending';
    }

    private function allowedTransitions(array $order): array
    {
        $status = strtolower((string) ($order['status'] ?? ''));
        $fulfillment = strtolower((string) ($order['fulfillment_method'] ?? 'pickup'));
        $paymentMethod = strtolower((string) ($order['payment_method'] ?? ''));
        $paymentStatus = strtolower((string) ($order['payment_status'] ?? 'pending'));

        if ($status === 'pending') {
            $allowed = ['cancelled'];
            if ($paymentMethod === 'cash' && $paymentStatus === 'pending') {
                array_unshift($allowed, 'confirmed');
            } elseif ($paymentMethod !== 'cash' && $paymentStatus === 'success') {
                array_unshift($allowed, 'confirmed');
            }

            return array_values(array_unique($allowed));
        }

        if ($status === 'confirmed') {
            return $this->appendCancellable(
                $fulfillment === 'pickup'
                    ? ['preparing', 'ready', 'completed']
                    : ['preparing', 'ready', 'shipping'],
                $order
            );
        }

        if ($status === 'preparing') {
            return $this->appendCancellable(
                $fulfillment === 'pickup'
                    ? ['ready', 'completed']
                    : ['ready', 'shipping'],
                $order
            );
        }

        if ($status === 'ready') {
            return $this->appendCancellable(
                $fulfillment === 'pickup'
                    ? ['completed']
                    : ['shipping', 'completed'],
                $order
            );
        }

        if ($status === 'shipping') {
            return ['completed'];
        }

        return [];
    }

    private function appendCancellable(array $transitions, array $order): array
    {
        if ($this->canCancelOrder($order)) {
            $transitions[] = 'cancelled';
        }

        return array_values(array_unique($transitions));
    }

    private function canCancelOrder(array $order): bool
    {
        $status = strtolower((string) ($order['status'] ?? ''));
        $paymentStatus = strtolower((string) ($order['payment_status'] ?? 'pending'));

        if (in_array($status, ['cancelled', 'expired', 'completed', 'refunded', 'shipping'], true)) {
            return false;
        }

        return !in_array($paymentStatus, ['success', 'processing', 'refunded'], true);
    }

    private function confirmOrder(int $orderId, array $header, array $payment, string $now, string $currentStatus): void
    {
        $paymentMethod = strtolower((string) ($payment['payment_method'] ?? ''));
        $paymentStatus = strtolower((string) ($payment['payment_status'] ?? 'pending'));

        if ($paymentMethod === 'cash') {
            if (!in_array($paymentStatus, ['pending', 'success'], true)) {
                throw new AdminShopOrderManagementException([
                    'payment' => ['Cash payment is not in a confirmable state.'],
                ], 409);
            }

            if ($paymentStatus !== 'success') {
                $this->payments->markPaymentSuccess((int) $payment['id'], [
                    'payment_status' => 'success',
                    'provider_message' => 'Cash payment confirmed by admin.',
                    'completed_at' => $now,
                    'payment_date' => $now,
                ]);
            }
        } elseif ($paymentStatus !== 'success') {
            throw new AdminShopOrderManagementException([
                'payment' => ['Online payment must succeed before the order can be confirmed.'],
            ], 409);
        }

        $this->updateOrderStatus($orderId, 'confirmed', [
            'confirmed_at' => $header['confirmed_at'] ?: $now,
            'payment_due_at' => null,
        ], [$currentStatus]);
    }

    private function cancelOrder(int $orderId, array $payment, string $now, string $currentStatus): void
    {
        $paymentStatus = strtolower((string) ($payment['payment_status'] ?? 'pending'));
        if (in_array($paymentStatus, ['success', 'processing', 'refunded'], true)) {
            throw new AdminShopOrderManagementException([
                'payment' => ['Paid shop orders require a refund workflow before cancellation.'],
            ], 409);
        }

        $this->lifecycle->restoreInventoryReservation($orderId);
        if ($paymentStatus === 'pending') {
            $this->payments->markPaymentIssue((int) $payment['id'], [
                'payment_status' => 'cancelled',
                'provider_message' => 'Shop order cancelled by admin.',
                'failed_at' => $now,
            ]);
        }

        $this->updateOrderStatus($orderId, 'cancelled', [
            'cancelled_at' => $now,
            'payment_due_at' => null,
        ], [$currentStatus]);
    }

    private function updateOrderStatus(int $orderId, string $status, array $fields, array $allowedCurrentStatuses): void
    {
        if (!$this->orders->updateOrderStatus($orderId, $status, $fields, $allowedCurrentStatuses)) {
            throw new AdminShopOrderManagementException([
                'order' => ['The order changed while the update was in progress. Please refresh and try again.'],
            ], 409);
        }
    }

    private function transactional(callable $callback): void
    {
        $startedTransaction = !$this->db->inTransaction();
        if ($startedTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $callback();
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }
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
