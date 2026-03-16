<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\PaymentRepository;
use App\Repositories\TicketOrderRepository;
use App\Repositories\TicketSeatHoldRepository;
use PDO;
use Throwable;

class TicketLifecycleService
{
    private PDO $db;
    private TicketSeatHoldRepository $holds;
    private TicketOrderRepository $orders;
    private PaymentRepository $payments;
    private Logger $logger;

    public function __construct(
        ?PDO $db = null,
        ?TicketSeatHoldRepository $holds = null,
        ?TicketOrderRepository $orders = null,
        ?PaymentRepository $payments = null,
        ?Logger $logger = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->holds = $holds ?? new TicketSeatHoldRepository($this->db);
        $this->orders = $orders ?? new TicketOrderRepository($this->db);
        $this->payments = $payments ?? new PaymentRepository($this->db);
        $this->logger = $logger ?? new Logger();
    }

    public function runMaintenance(): array
    {
        $purgedHolds = $this->holds->purgeExpired();
        $expiredOrderIds = $this->orders->listExpiredPendingOrderIds();
        $expiredOrders = 0;
        $expiredTickets = 0;
        $expiredPayments = 0;

        if ($expiredOrderIds !== []) {
            $this->transactional(function () use ($expiredOrderIds, &$expiredOrders, &$expiredTickets, &$expiredPayments) {
                $expiredOrders = $this->orders->expireOrders($expiredOrderIds);
                $expiredTickets = $this->orders->expireTicketDetailsForOrderIds($expiredOrderIds);
                $expiredPayments = $this->payments->markTicketPaymentsExpired($expiredOrderIds);
            });
        }

        if ($purgedHolds > 0 || $expiredOrders > 0 || $expiredTickets > 0 || $expiredPayments > 0) {
            $this->logger->info('Ticket lifecycle maintenance completed', [
                'purged_holds' => $purgedHolds,
                'expired_orders' => $expiredOrders,
                'expired_tickets' => $expiredTickets,
                'expired_payments' => $expiredPayments,
            ]);
        }

        return [
            'purged_holds' => $purgedHolds,
            'expired_orders' => $expiredOrders,
            'expired_tickets' => $expiredTickets,
            'expired_payments' => $expiredPayments,
        ];
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
