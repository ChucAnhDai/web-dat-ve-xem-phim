<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\OrderDetailRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ShopOrderRepository;
use PDO;
use Throwable;

class ShopOrderLifecycleService
{
    private PDO $db;
    private ShopOrderRepository $orders;
    private OrderDetailRepository $details;
    private ProductRepository $products;
    private PaymentRepository $payments;
    private Logger $logger;

    public function __construct(
        ?PDO $db = null,
        ?ShopOrderRepository $orders = null,
        ?OrderDetailRepository $details = null,
        ?ProductRepository $products = null,
        ?PaymentRepository $payments = null,
        ?Logger $logger = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->orders = $orders ?? new ShopOrderRepository($this->db);
        $this->details = $details ?? new OrderDetailRepository($this->db);
        $this->products = $products ?? new ProductRepository($this->db);
        $this->payments = $payments ?? new PaymentRepository($this->db);
        $this->logger = $logger ?? new Logger();
    }

    public function runMaintenance(): array
    {
        $expiredOrderIds = $this->orders->listExpiredPendingOrderIds();
        $expiredOrders = 0;
        $expiredPayments = 0;
        $restoredInventoryLines = 0;

        if ($expiredOrderIds !== []) {
            $this->transactional(function () use ($expiredOrderIds, &$expiredOrders, &$expiredPayments, &$restoredInventoryLines): void {
                $restoredInventoryLines = $this->restoreInventoryForOrderIds($expiredOrderIds);
                $expiredOrders = $this->orders->markOrdersIssue($expiredOrderIds, 'expired');
                $expiredPayments = $this->payments->markShopPaymentsExpired($expiredOrderIds);
            });
        }

        if ($expiredOrders > 0 || $expiredPayments > 0 || $restoredInventoryLines > 0) {
            $this->logger->info('Shop checkout lifecycle maintenance completed', [
                'expired_orders' => $expiredOrders,
                'expired_payments' => $expiredPayments,
                'restored_inventory_lines' => $restoredInventoryLines,
            ]);
        }

        return [
            'expired_orders' => $expiredOrders,
            'expired_payments' => $expiredPayments,
            'restored_inventory_lines' => $restoredInventoryLines,
        ];
    }

    public function releaseInventoryAndMarkIssue(int $orderId, string $status, ?string $timestamp = null): void
    {
        if ($orderId <= 0 || !in_array($status, ['cancelled', 'expired'], true)) {
            return;
        }

        $order = $this->orders->findById($orderId);
        if ($order === null || strtolower((string) ($order['status'] ?? '')) !== 'pending') {
            return;
        }

        $this->transactional(function () use ($orderId, $status, $timestamp): void {
            $this->restoreInventoryForOrderIds([$orderId]);
            $this->orders->markOrdersIssue([$orderId], $status, $timestamp);
        });
    }

    public function restoreInventoryReservation(int $orderId): int
    {
        if ($orderId <= 0) {
            return 0;
        }

        return $this->restoreInventoryForOrderIds([$orderId]);
    }

    private function restoreInventoryForOrderIds(array $orderIds): int
    {
        $rows = $this->details->listProductQuantitiesByOrderIds($orderIds);
        $restored = 0;

        foreach ($rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $quantity = (int) ($row['quantity'] ?? 0);
            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            if ($this->products->restoreTrackedInventory($productId, $quantity)) {
                $restored += 1;
            }
        }

        return $restored;
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
}
