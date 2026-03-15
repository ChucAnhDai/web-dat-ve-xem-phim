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
        $stmt = $this->db->prepare('
            INSERT INTO payments (
                ticket_order_id,
                shop_order_id,
                payment_method,
                payment_status,
                transaction_code
            )
            VALUES (
                :ticket_order_id,
                NULL,
                :payment_method,
                :payment_status,
                :transaction_code
            )
        ');
        $stmt->execute([
            'ticket_order_id' => $data['ticket_order_id'],
            'payment_method' => $data['payment_method'],
            'payment_status' => $data['payment_status'],
            'transaction_code' => $data['transaction_code'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function markTicketPaymentsFailed(array $orderIds): int
    {
        if ($orderIds === []) {
            return 0;
        }

        $params = [];
        $placeholders = $this->orderIdPlaceholders($orderIds, $params);
        $stmt = $this->db->prepare("
            UPDATE payments
            SET payment_status = 'failed'
            WHERE ticket_order_id IN ({$placeholders})
              AND payment_status = 'pending'
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->rowCount();
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
