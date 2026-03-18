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

    public function listActiveByCodes(array $codes): array
    {
        $normalizedCodes = array_values(array_unique(array_filter(array_map(static function ($code): string {
            return strtolower(trim((string) $code));
        }, $codes), static function (string $code): bool {
            return $code !== '';
        })));

        if ($normalizedCodes === []) {
            return [];
        }

        $params = ['status' => 'active'];
        $placeholders = [];
        foreach ($normalizedCodes as $index => $code) {
            $key = 'code_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $code;
        }

        $inList = implode(', ', $placeholders);
        $stmt = $this->db->prepare("
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
            WHERE status = :status
              AND code IN ({$inList})
            ORDER BY display_order ASC, id ASC
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, $key === 'status' ? PDO::PARAM_STR : PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }
}
