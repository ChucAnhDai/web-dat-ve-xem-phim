<?php

namespace App\Services;

use App\Core\Logger;
use App\Repositories\TicketOrderRepository;
use App\Repositories\TicketSeatHoldRepository;
use App\Services\Concerns\FormatsTicketData;
use App\Validators\TicketOrderValidator;
use Throwable;

class AdminTicketManagementService
{
    use FormatsTicketData;

    private TicketOrderRepository $orders;
    private TicketSeatHoldRepository $holds;
    private TicketOrderValidator $validator;
    private TicketLifecycleService $lifecycle;
    private Logger $logger;

    public function __construct(
        ?TicketOrderRepository $orders = null,
        ?TicketSeatHoldRepository $holds = null,
        ?TicketOrderValidator $validator = null,
        ?TicketLifecycleService $lifecycle = null,
        ?Logger $logger = null
    ) {
        $this->orders = $orders ?? new TicketOrderRepository();
        $this->holds = $holds ?? new TicketSeatHoldRepository();
        $this->validator = $validator ?? new TicketOrderValidator();
        $this->lifecycle = $lifecycle ?? new TicketLifecycleService();
        $this->logger = $logger ?? new Logger();
    }

    public function listTicketOrders(array $filters): array
    {
        $normalized = $this->validator->normalizeAdminOrderFilters($filters);

        try {
            $this->lifecycle->runMaintenance();
            $page = $this->orders->paginateAdminOrders($normalized);
            $summary = $this->orders->summarizeAdminOrders($normalized);
            $detailRows = $this->orders->listOrderContextRowsByOrderIds(array_map(static function (array $row): int {
                return (int) ($row['id'] ?? 0);
            }, $page['items']));
        } catch (Throwable $exception) {
            $this->logger->error('Admin ticket order list failed', [
                'filters' => $normalized,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to load ticket orders.']], 500);
        }

        $detailLookup = $this->groupDetailsByOrder($detailRows);
        $items = array_map(function (array $header) use ($detailLookup): array {
            return $this->formatOrderSummary($header, $detailLookup[(int) ($header['id'] ?? 0)] ?? []);
        }, $page['items']);

        return $this->success([
            'items' => $items,
            'meta' => $this->paginationMeta($page),
            'summary' => $summary,
            'filters' => $normalized,
        ]);
    }

    public function getTicketOrder(int $orderId): array
    {
        try {
            $this->lifecycle->runMaintenance();
            $header = $this->orders->findOrderHeaderById($orderId);
            if ($header === null) {
                return $this->error(['order' => ['Ticket order not found.']], 404);
            }

            $detailRows = $this->orders->listOrderContextRowsByOrderIds([$orderId]);
        } catch (Throwable $exception) {
            $this->logger->error('Admin ticket order detail failed', [
                'order_id' => $orderId,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to load ticket order.']], 500);
        }

        return $this->success($this->formatOrderDetail($header, $detailRows));
    }

    public function listTicketDetails(array $filters): array
    {
        $normalized = $this->validator->normalizeAdminTicketFilters($filters);

        try {
            $this->lifecycle->runMaintenance();
            $page = $this->orders->paginateAdminTickets($normalized);
            $summary = $this->orders->summarizeAdminTickets($normalized);
        } catch (Throwable $exception) {
            $this->logger->error('Admin ticket detail list failed', [
                'filters' => $normalized,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to load ticket details.']], 500);
        }

        return $this->success([
            'items' => array_map([$this, 'formatTicketRow'], $page['items']),
            'meta' => $this->paginationMeta($page),
            'summary' => $summary,
            'filters' => $normalized,
        ]);
    }

    public function getTicketDetail(int $ticketId): array
    {
        try {
            $this->lifecycle->runMaintenance();
            $row = $this->orders->findTicketRowById($ticketId);
            if ($row === null) {
                return $this->error(['ticket' => ['Ticket detail not found.']], 404);
            }
        } catch (Throwable $exception) {
            $this->logger->error('Admin ticket detail load failed', [
                'ticket_id' => $ticketId,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to load ticket detail.']], 500);
        }

        return $this->success($this->formatTicketRow($row));
    }

    public function listActiveHolds(array $filters): array
    {
        $normalized = $this->validator->normalizeHoldFilters($filters);

        try {
            $this->lifecycle->runMaintenance();
            $items = array_map([$this, 'formatHoldRow'], $this->holds->listActiveQueue($normalized['limit']));
        } catch (Throwable $exception) {
            $this->logger->error('Admin active hold queue load failed', [
                'filters' => $normalized,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to load active seat holds.']], 500);
        }

        return $this->success([
            'items' => $items,
            'summary' => [
                'active_holds' => count($items),
            ],
            'filters' => $normalized,
        ]);
    }

    private function groupDetailsByOrder(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) ($row['order_id'] ?? 0)][] = $row;
        }

        return $grouped;
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
