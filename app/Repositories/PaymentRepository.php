<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PaymentRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function createTicketPayment(array $data): int
    {
        return $this->createPayment(array_merge($data, [
            'ticket_order_id' => $data['ticket_order_id'],
            'shop_order_id' => null,
        ]));
    }

    public function createShopPayment(array $data): int
    {
        return $this->createPayment(array_merge($data, [
            'ticket_order_id' => null,
            'shop_order_id' => $data['shop_order_id'],
        ]));
    }

    public function findLatestTicketPaymentByOrderId(int $orderId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT
                id,
                ticket_order_id,
                shop_order_id,
                payment_method,
                payment_status,
                amount,
                currency,
                transaction_code,
                provider_transaction_code,
                provider_order_ref,
                provider_response_code,
                provider_message,
                idempotency_key,
                checkout_url,
                request_payload,
                callback_payload,
                initiated_at,
                completed_at,
                failed_at,
                refunded_at,
                payment_date,
                created_at,
                updated_at
            FROM payments
            WHERE ticket_order_id = :ticket_order_id
            ORDER BY id DESC
            LIMIT 1
        ');
        $stmt->execute(['ticket_order_id' => $orderId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findLatestShopPaymentByOrderId(int $orderId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT
                id,
                ticket_order_id,
                shop_order_id,
                payment_method,
                payment_status,
                amount,
                currency,
                transaction_code,
                provider_transaction_code,
                provider_order_ref,
                provider_response_code,
                provider_message,
                idempotency_key,
                checkout_url,
                request_payload,
                callback_payload,
                initiated_at,
                completed_at,
                failed_at,
                refunded_at,
                payment_date,
                created_at,
                updated_at
            FROM payments
            WHERE shop_order_id = :shop_order_id
            ORDER BY id DESC
            LIMIT 1
        ');
        $stmt->execute(['shop_order_id' => $orderId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findTicketPaymentByProviderOrderRef(string $providerOrderRef, string $method = 'vnpay'): ?array
    {
        $stmt = $this->db->prepare('
            SELECT
                p.id,
                p.ticket_order_id,
                p.shop_order_id,
                p.payment_method,
                p.payment_status,
                p.amount,
                p.currency,
                p.transaction_code,
                p.provider_transaction_code,
                p.provider_order_ref,
                p.provider_response_code,
                p.provider_message,
                p.idempotency_key,
                p.checkout_url,
                p.request_payload,
                p.callback_payload,
                p.initiated_at,
                p.completed_at,
                p.failed_at,
                p.refunded_at,
                p.payment_date,
                p.created_at,
                p.updated_at,
                o.order_code,
                o.status AS order_status,
                o.hold_expires_at,
                o.total_price,
                o.currency AS order_currency
            FROM payments p
            INNER JOIN ticket_orders o ON o.id = p.ticket_order_id
            WHERE p.provider_order_ref = :provider_order_ref
              AND p.payment_method = :payment_method
            ORDER BY p.id DESC
            LIMIT 1
        ');
        $stmt->execute([
            'provider_order_ref' => $providerOrderRef,
            'payment_method' => $method,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findShopPaymentByProviderOrderRef(string $providerOrderRef, string $method = 'vnpay'): ?array
    {
        $stmt = $this->db->prepare('
            SELECT
                p.id,
                p.ticket_order_id,
                p.shop_order_id,
                p.payment_method,
                p.payment_status,
                p.amount,
                p.currency,
                p.transaction_code,
                p.provider_transaction_code,
                p.provider_order_ref,
                p.provider_response_code,
                p.provider_message,
                p.idempotency_key,
                p.checkout_url,
                p.request_payload,
                p.callback_payload,
                p.initiated_at,
                p.completed_at,
                p.failed_at,
                p.refunded_at,
                p.payment_date,
                p.created_at,
                p.updated_at,
                o.order_code,
                o.status AS order_status,
                o.payment_due_at,
                o.total_price,
                o.currency AS order_currency
            FROM payments p
            INNER JOIN shop_orders o ON o.id = p.shop_order_id
            WHERE p.provider_order_ref = :provider_order_ref
              AND p.payment_method = :payment_method
            ORDER BY p.id DESC
            LIMIT 1
        ');
        $stmt->execute([
            'provider_order_ref' => $providerOrderRef,
            'payment_method' => $method,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?array
    {
        $stmt = $this->db->prepare('
            SELECT
                id,
                ticket_order_id,
                shop_order_id,
                payment_method,
                payment_status,
                amount,
                currency,
                transaction_code,
                provider_transaction_code,
                provider_order_ref,
                provider_response_code,
                provider_message,
                idempotency_key,
                checkout_url,
                request_payload,
                callback_payload,
                initiated_at,
                completed_at,
                failed_at,
                refunded_at,
                payment_date,
                created_at,
                updated_at
            FROM payments
            WHERE idempotency_key = :idempotency_key
            ORDER BY id DESC
            LIMIT 1
        ');
        $stmt->execute(['idempotency_key' => $idempotencyKey]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function updateGatewayCheckout(int $paymentId, array $data): void
    {
        $stmt = $this->db->prepare('
            UPDATE payments
            SET
                checkout_url = :checkout_url,
                request_payload = :request_payload,
                provider_order_ref = :provider_order_ref,
                idempotency_key = :idempotency_key,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');
        $stmt->execute([
            'id' => $paymentId,
            'checkout_url' => $data['checkout_url'] ?? null,
            'request_payload' => $data['request_payload'] ?? null,
            'provider_order_ref' => $data['provider_order_ref'] ?? null,
            'idempotency_key' => $data['idempotency_key'] ?? null,
        ]);
    }

    public function updateCheckoutUrl(int $paymentId, string $checkoutUrl): void
    {
        $stmt = $this->db->prepare('
            UPDATE payments
            SET
                checkout_url = :checkout_url,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');
        $stmt->execute([
            'id' => $paymentId,
            'checkout_url' => $checkoutUrl,
        ]);
    }

    public function markPaymentSuccess(int $paymentId, array $data): void
    {
        $stmt = $this->db->prepare('
            UPDATE payments
            SET
                payment_status = :payment_status,
                provider_transaction_code = :provider_transaction_code,
                provider_response_code = :provider_response_code,
                provider_message = :provider_message,
                callback_payload = :callback_payload,
                completed_at = :completed_at,
                payment_date = :payment_date,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');
        $stmt->execute([
            'id' => $paymentId,
            'payment_status' => $data['payment_status'] ?? 'success',
            'provider_transaction_code' => $data['provider_transaction_code'] ?? null,
            'provider_response_code' => $data['provider_response_code'] ?? null,
            'provider_message' => $data['provider_message'] ?? null,
            'callback_payload' => $data['callback_payload'] ?? null,
            'completed_at' => $data['completed_at'] ?? date('Y-m-d H:i:s'),
            'payment_date' => $data['payment_date'] ?? date('Y-m-d H:i:s'),
        ]);
    }

    public function markPaymentIssue(int $paymentId, array $data): void
    {
        $stmt = $this->db->prepare('
            UPDATE payments
            SET
                payment_status = :payment_status,
                provider_transaction_code = :provider_transaction_code,
                provider_response_code = :provider_response_code,
                provider_message = :provider_message,
                callback_payload = :callback_payload,
                failed_at = :failed_at,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');
        $stmt->execute([
            'id' => $paymentId,
            'payment_status' => $data['payment_status'] ?? 'failed',
            'provider_transaction_code' => $data['provider_transaction_code'] ?? null,
            'provider_response_code' => $data['provider_response_code'] ?? null,
            'provider_message' => $data['provider_message'] ?? null,
            'callback_payload' => $data['callback_payload'] ?? null,
            'failed_at' => $data['failed_at'] ?? date('Y-m-d H:i:s'),
        ]);
    }

    public function markTicketPaymentsExpired(array $orderIds): int
    {
        if ($orderIds === []) {
            return 0;
        }

        $params = [];
        $placeholders = $this->orderIdPlaceholders($orderIds, $params);
        $stmt = $this->db->prepare("
            UPDATE payments
            SET payment_status = 'expired',
                provider_message = COALESCE(provider_message, 'Payment expired before confirmation.'),
                failed_at = COALESCE(failed_at, CURRENT_TIMESTAMP)
            WHERE ticket_order_id IN ({$placeholders})
              AND payment_status = 'pending'
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function markShopPaymentsExpired(array $orderIds): int
    {
        if ($orderIds === []) {
            return 0;
        }

        $params = [];
        $placeholders = $this->orderIdPlaceholders($orderIds, $params);
        $stmt = $this->db->prepare("
            UPDATE payments
            SET payment_status = 'expired',
                provider_message = COALESCE(provider_message, 'Payment expired before confirmation.'),
                failed_at = COALESCE(failed_at, CURRENT_TIMESTAMP)
            WHERE shop_order_id IN ({$placeholders})
              AND payment_status = 'pending'
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->rowCount();
    }

    private function createPayment(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO payments (
                ticket_order_id,
                shop_order_id,
                payment_method,
                payment_status,
                amount,
                currency,
                transaction_code,
                provider_transaction_code,
                provider_order_ref,
                provider_response_code,
                provider_message,
                idempotency_key,
                checkout_url,
                request_payload,
                callback_payload,
                initiated_at,
                completed_at,
                failed_at,
                refunded_at,
                payment_date
            )
            VALUES (
                :ticket_order_id,
                :shop_order_id,
                :payment_method,
                :payment_status,
                :amount,
                :currency,
                :transaction_code,
                :provider_transaction_code,
                :provider_order_ref,
                :provider_response_code,
                :provider_message,
                :idempotency_key,
                :checkout_url,
                :request_payload,
                :callback_payload,
                :initiated_at,
                :completed_at,
                :failed_at,
                :refunded_at,
                :payment_date
            )
        ');
        $status = (string) ($data['payment_status'] ?? 'pending');
        $initiatedAt = $data['initiated_at'] ?? date('Y-m-d H:i:s');
        $completedAt = $data['completed_at'] ?? ($status === 'success' ? $initiatedAt : null);
        $failedAt = $data['failed_at'] ?? ($status === 'failed' || $status === 'cancelled' || $status === 'expired' ? $initiatedAt : null);
        $refundedAt = $data['refunded_at'] ?? ($status === 'refunded' ? $initiatedAt : null);
        $paymentDate = $data['payment_date'] ?? ($completedAt ?? $initiatedAt);

        $stmt->execute([
            'ticket_order_id' => $data['ticket_order_id'] ?? null,
            'shop_order_id' => $data['shop_order_id'] ?? null,
            'payment_method' => $data['payment_method'],
            'payment_status' => $status,
            'amount' => $data['amount'] ?? 0,
            'currency' => $data['currency'] ?? 'VND',
            'transaction_code' => $data['transaction_code'] ?? null,
            'provider_transaction_code' => $data['provider_transaction_code'] ?? null,
            'provider_order_ref' => $data['provider_order_ref'] ?? null,
            'provider_response_code' => $data['provider_response_code'] ?? null,
            'provider_message' => $data['provider_message'] ?? null,
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'checkout_url' => $data['checkout_url'] ?? null,
            'request_payload' => $data['request_payload'] ?? null,
            'callback_payload' => $data['callback_payload'] ?? null,
            'initiated_at' => $initiatedAt,
            'completed_at' => $completedAt,
            'failed_at' => $failedAt,
            'refunded_at' => $refundedAt,
            'payment_date' => $paymentDate,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function orderIdPlaceholders(array $orderIds, array &$params): string
    {
        $placeholders = [];
        foreach (array_values($orderIds) as $index => $orderId) {
            $key = 'order_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int) $orderId;
        }

        return implode(', ', $placeholders);
    }
}
