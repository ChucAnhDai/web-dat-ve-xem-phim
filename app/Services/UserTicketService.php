<?php

namespace App\Services;

use App\Core\Logger;
use App\Repositories\TicketOrderRepository;
use App\Services\Concerns\FormatsTicketData;
use App\Validators\TicketOrderValidator;
use Throwable;

class UserTicketService
{
    use FormatsTicketData;

    private TicketOrderRepository $orders;
    private TicketOrderValidator $validator;
    private TicketLifecycleService $lifecycle;
    private Logger $logger;

    public function __construct(
        ?TicketOrderRepository $orders = null,
        ?TicketOrderValidator $validator = null,
        ?TicketLifecycleService $lifecycle = null,
        ?Logger $logger = null
    ) {
        $this->orders = $orders ?? new TicketOrderRepository();
        $this->validator = $validator ?? new TicketOrderValidator();
        $this->lifecycle = $lifecycle ?? new TicketLifecycleService();
        $this->logger = $logger ?? new Logger();
    }

    public function listMyTickets(int $userId, array $filters): array
    {
        $normalized = $this->validator->normalizeUserTicketFilters($filters);

        try {
            $this->lifecycle->runMaintenance();
            $page = $this->orders->paginateUserTickets($userId, $normalized);
            $summary = $this->orders->summarizeUserTickets($userId, $normalized);
        } catch (Throwable $exception) {
            $this->logger->error('User ticket history load failed', [
                'user_id' => $userId,
                'filters' => $normalized,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to load tickets.']], 500);
        }

        return $this->success([
            'items' => array_map([$this, 'formatTicketRow'], $page['items']),
            'meta' => $this->paginationMeta($page),
            'summary' => $summary,
            'filters' => $normalized,
        ]);
    }

    public function listMyOrders(int $userId, array $filters): array
    {
        $normalized = $this->validator->normalizeUserOrderFilters($filters);

        try {
            $this->lifecycle->runMaintenance();
            $page = $this->orders->paginateUserOrders($userId, $normalized);
            $summary = $this->orders->summarizeUserOrders($userId, $normalized);
            $detailRows = $this->orders->listOrderContextRowsByOrderIds(array_map(static function (array $row): int {
                return (int) ($row['id'] ?? 0);
            }, $page['items']));
        } catch (Throwable $exception) {
            $this->logger->error('User ticket order history load failed', [
                'user_id' => $userId,
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
