<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\TicketSeatHoldRepository;
use PDO;
use Throwable;

class UnifiedCartService extends ShopCartService
{
    private PDO $db;
    private ShopCartService $shopCart;
    private TicketSeatHoldRepository $holds;
    private TicketCheckoutContextService $ticketContext;
    private Logger $logger;

    public function __construct(
        ?PDO $db = null,
        ?ShopCartService $shopCart = null,
        ?TicketSeatHoldRepository $holds = null,
        ?TicketCheckoutContextService $ticketContext = null,
        ?Logger $logger = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->shopCart = $shopCart ?? new ShopCartService($this->db);
        $this->holds = $holds ?? new TicketSeatHoldRepository($this->db);
        $this->ticketContext = $ticketContext ?? new TicketCheckoutContextService($this->db);
        $this->logger = $logger ?? new Logger();
    }

    public function cartCookieName(): string
    {
        return $this->shopCart->cartCookieName();
    }

    public function getCart(?int $userId = null, ?string $sessionToken = null, ?string $ticketSessionToken = null): array
    {
        $shopResult = $this->shopCart->getCart($userId, $sessionToken);
        if (isset($shopResult['errors'])) {
            return $shopResult;
        }

        return $this->decorateSuccessfulCartResult($shopResult, $ticketSessionToken);
    }

    public function addItem(
        array $payload,
        ?int $userId = null,
        ?string $sessionToken = null,
        ?string $ticketSessionToken = null
    ): array {
        $shopResult = $this->shopCart->addItem($payload, $userId, $sessionToken);
        if (isset($shopResult['errors'])) {
            return $shopResult;
        }

        return $this->decorateSuccessfulCartResult($shopResult, $ticketSessionToken);
    }

    public function updateItemQuantity(
        int $productId,
        array $payload,
        ?int $userId = null,
        ?string $sessionToken = null,
        ?string $ticketSessionToken = null
    ): array {
        $shopResult = $this->shopCart->updateItemQuantity($productId, $payload, $userId, $sessionToken);
        if (isset($shopResult['errors'])) {
            return $shopResult;
        }

        return $this->decorateSuccessfulCartResult($shopResult, $ticketSessionToken);
    }

    public function removeItem(
        int $productId,
        ?int $userId = null,
        ?string $sessionToken = null,
        ?string $ticketSessionToken = null
    ): array {
        $shopResult = $this->shopCart->removeItem($productId, $userId, $sessionToken);
        if (isset($shopResult['errors'])) {
            return $shopResult;
        }

        return $this->decorateSuccessfulCartResult($shopResult, $ticketSessionToken);
    }

    public function clearCart(
        ?int $userId = null,
        ?string $sessionToken = null,
        ?string $ticketSessionToken = null
    ): array {
        $shopResult = $this->shopCart->clearCart($userId, $sessionToken);
        if (isset($shopResult['errors'])) {
            return $shopResult;
        }

        if ($this->normalizeTicketSessionToken($ticketSessionToken) !== null) {
            try {
                $releasedCount = $this->holds->releaseForSession((string) $ticketSessionToken);
                $this->logger->info('Unified cart ticket selection cleared', [
                    'session_token' => $this->ticketSessionPreview($ticketSessionToken),
                    'released_count' => $releasedCount,
                ]);
            } catch (Throwable $exception) {
                $this->logger->error('Unified cart failed to clear held tickets', [
                    'session_token' => $this->ticketSessionPreview($ticketSessionToken),
                    'error' => $exception->getMessage(),
                ]);

                return [
                    'status' => 500,
                    'errors' => [
                        'ticket_selection' => ['Failed to clear the held ticket selection.'],
                    ],
                ];
            }
        }

        return $this->decorateSuccessfulCartResult($shopResult, $ticketSessionToken);
    }

    public function removeTicketSelection(
        ?int $userId = null,
        ?string $sessionToken = null,
        ?string $ticketSessionToken = null
    ): array {
        $normalizedTicketSession = $this->normalizeTicketSessionToken($ticketSessionToken);
        if ($normalizedTicketSession !== null) {
            try {
                $releasedCount = $this->holds->releaseForSession($normalizedTicketSession);
                $this->logger->info('Unified cart ticket selection removed', [
                    'session_token' => $this->ticketSessionPreview($normalizedTicketSession),
                    'released_count' => $releasedCount,
                ]);
            } catch (Throwable $exception) {
                $this->logger->error('Unified cart remove ticket selection failed', [
                    'session_token' => $this->ticketSessionPreview($normalizedTicketSession),
                    'error' => $exception->getMessage(),
                ]);

                return [
                    'status' => 500,
                    'errors' => [
                        'ticket_selection' => ['Failed to remove the held ticket selection.'],
                    ],
                ];
            }
        }

        return $this->getCart($userId, $sessionToken, $ticketSessionToken);
    }

    private function decorateSuccessfulCartResult(array $result, ?string $ticketSessionToken): array
    {
        $data = $result['data'] ?? [];
        $productCart = is_array($data['cart'] ?? null) ? $data['cart'] : [];
        $ticketSelection = $this->loadTicketSelection($ticketSessionToken);

        $data['ticket_selection'] = $ticketSelection;
        $data['summary'] = $this->buildSummary($productCart, $ticketSelection);
        $result['data'] = $data;

        return $result;
    }

    private function loadTicketSelection(?string $ticketSessionToken): array
    {
        $normalizedTicketSession = $this->normalizeTicketSessionToken($ticketSessionToken);
        if ($normalizedTicketSession === null) {
            return $this->emptyTicketSelection();
        }

        $showtimeId = $this->holds->findActiveShowtimeIdForSession($normalizedTicketSession);
        if ($showtimeId === null || $showtimeId <= 0) {
            return $this->emptyTicketSelection();
        }

        try {
            $context = $this->ticketContext->buildContext($showtimeId, [], $normalizedTicketSession);
        } catch (Throwable $exception) {
            $this->logger->info('Unified cart skipped stale ticket selection', [
                'session_token' => $this->ticketSessionPreview($normalizedTicketSession),
                'showtime_id' => $showtimeId,
                'error' => $exception->getMessage(),
            ]);

            return $this->emptyTicketSelection();
        }

        return [
            'is_empty' => false,
            'showtime_id' => $showtimeId,
            'showtime' => $context['showtime_summary'],
            'seats' => array_map(static function (array $seat): array {
                return [
                    'id' => (int) ($seat['id'] ?? 0),
                    'label' => $seat['label'] ?? null,
                    'type' => $seat['type'] ?? 'normal',
                    'base_price' => isset($seat['base_price']) ? (float) $seat['base_price'] : 0.0,
                    'surcharge_amount' => isset($seat['surcharge_amount']) ? (float) $seat['surcharge_amount'] : 0.0,
                    'price' => isset($seat['price']) ? (float) $seat['price'] : 0.0,
                    'hold_expires_at' => $seat['hold_expires_at'] ?? null,
                ];
            }, $context['seats'] ?? []),
            'seat_count' => count($context['seats'] ?? []),
            'subtotal_price' => isset($context['subtotal_price']) ? (float) $context['subtotal_price'] : 0.0,
            'surcharge_total' => isset($context['surcharge_total']) ? (float) $context['surcharge_total'] : 0.0,
            'total_price' => isset($context['total_price']) ? (float) $context['total_price'] : 0.0,
            'currency' => 'VND',
            'hold_expires_at' => $context['hold_expires_at'] ?? null,
        ];
    }

    private function buildSummary(array $productCart, array $ticketSelection): array
    {
        $productLineCount = max(0, (int) ($productCart['line_count'] ?? 0));
        $productItemCount = max(0, (int) ($productCart['item_count'] ?? 0));
        $productSubtotal = round((float) ($productCart['subtotal_price'] ?? 0), 2);
        $productDiscount = round((float) ($productCart['discount_amount'] ?? 0), 2);
        $productFee = round((float) ($productCart['fee_amount'] ?? 0), 2);
        $productTotal = round((float) ($productCart['total_price'] ?? 0), 2);
        $containsTickets = empty($ticketSelection['is_empty']);
        $ticketSeatCount = max(0, (int) ($ticketSelection['seat_count'] ?? 0));
        $ticketTotal = round((float) ($ticketSelection['total_price'] ?? 0), 2);
        $currency = strtoupper(trim((string) ($productCart['currency'] ?? $ticketSelection['currency'] ?? 'VND')));

        return [
            'currency' => $currency !== '' ? $currency : 'VND',
            'product_line_count' => $productLineCount,
            'product_item_count' => $productItemCount,
            'ticket_line_count' => $containsTickets ? 1 : 0,
            'ticket_item_count' => $ticketSeatCount,
            'line_count' => $productLineCount + ($containsTickets ? 1 : 0),
            'item_count' => $productItemCount + $ticketSeatCount,
            'product_subtotal_price' => $productSubtotal,
            'ticket_total_price' => $ticketTotal,
            'subtotal_price' => round($productSubtotal + $ticketTotal, 2),
            'discount_amount' => $productDiscount,
            'fee_amount' => $productFee,
            'total_price' => round($productTotal + $ticketTotal, 2),
            'contains_products' => !($productCart['is_empty'] ?? true),
            'contains_tickets' => $containsTickets,
            'is_empty' => ($productCart['is_empty'] ?? true) && !$containsTickets,
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
            'currency' => 'VND',
            'hold_expires_at' => null,
        ];
    }

    private function normalizeTicketSessionToken(?string $value): ?string
    {
        $normalized = strtolower(trim((string) ($value ?? '')));
        if ($normalized === '') {
            return null;
        }

        return preg_match('/^[a-f0-9]{48}$/', $normalized) === 1 ? $normalized : null;
    }

    private function ticketSessionPreview(?string $value): ?string
    {
        $normalized = $this->normalizeTicketSessionToken($value);
        if ($normalized === null) {
            return null;
        }

        return substr($normalized, 0, 12);
    }
}
