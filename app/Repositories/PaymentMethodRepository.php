<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PaymentMethodRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare('
            SELECT
                id,
                code,
                name,
                provider,
                channel_type,
                status,
                fee_rate_percent,
                fixed_fee_amount,
                settlement_cycle,
                supports_refund,
                supports_webhook,
                supports_redirect,
                display_order,
                description
            FROM payment_methods
            WHERE code = :code
            LIMIT 1
        ');
        $stmt->execute(['code' => strtolower(trim($code))]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
