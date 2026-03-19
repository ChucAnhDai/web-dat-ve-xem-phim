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

    public function findById(int $id): ?array
    {
        $stats = $this->adminStatsSubquery();
        $stmt = $this->db->prepare("
            SELECT
                pm.id,
                pm.code,
                pm.name,
                pm.provider,
                pm.channel_type,
                pm.status,
                pm.fee_rate_percent,
                pm.fixed_fee_amount,
                pm.settlement_cycle,
                pm.supports_refund,
                pm.supports_webhook,
                pm.supports_redirect,
                pm.display_order,
                pm.description,
                pm.created_at,
                pm.updated_at,
                COALESCE(stats.transaction_count, 0) AS transaction_count,
                COALESCE(stats.captured_value, 0) AS captured_value,
                COALESCE(stats.issue_count, 0) AS issue_count,
                stats.last_payment_at
            FROM payment_methods pm
            LEFT JOIN ({$stats}) stats ON stats.payment_method = pm.code
            WHERE pm.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
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

    public function paginateAdminMethods(array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, (int) ($filters['per_page'] ?? 20));
        $params = [];
        $stats = $this->adminStatsSubquery();
        $from = "FROM payment_methods pm LEFT JOIN ({$stats}) stats ON stats.payment_method = pm.code";
        $where = $this->buildAdminWhereClause($filters, $params);

        $countStmt = $this->db->prepare("SELECT COUNT(*) {$from} {$where}");
        $this->bindParams($countStmt, $params);
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare("
            SELECT
                pm.id,
                pm.code,
                pm.name,
                pm.provider,
                pm.channel_type,
                pm.status,
                pm.fee_rate_percent,
                pm.fixed_fee_amount,
                pm.settlement_cycle,
                pm.supports_refund,
                pm.supports_webhook,
                pm.supports_redirect,
                pm.display_order,
                pm.description,
                pm.created_at,
                pm.updated_at,
                COALESCE(stats.transaction_count, 0) AS transaction_count,
                COALESCE(stats.captured_value, 0) AS captured_value,
                COALESCE(stats.issue_count, 0) AS issue_count,
                stats.last_payment_at
            {$from}
            {$where}
            ORDER BY pm.display_order ASC, pm.id ASC
            LIMIT :limit OFFSET :offset
        ");
        $this->bindParams($stmt, $params);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll() ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function summarizeAdminMethods(array $filters = []): array
    {
        $params = [];
        $stats = $this->adminStatsSubquery();
        $from = "FROM payment_methods pm LEFT JOIN ({$stats}) stats ON stats.payment_method = pm.code";
        $where = $this->buildAdminWhereClause($filters, $params);

        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total_methods,
                COALESCE(SUM(CASE WHEN pm.status = 'active' THEN 1 ELSE 0 END), 0) AS active_methods,
                COALESCE(SUM(CASE WHEN pm.status = 'maintenance' THEN 1 ELSE 0 END), 0) AS maintenance_methods,
                COALESCE(SUM(CASE WHEN pm.status = 'disabled' THEN 1 ELSE 0 END), 0) AS disabled_methods,
                COALESCE(SUM(COALESCE(stats.transaction_count, 0)), 0) AS total_transactions,
                COALESCE(SUM(COALESCE(stats.captured_value, 0)), 0) AS captured_value
            {$from}
            {$where}
        ");
        $this->bindParams($stmt, $params);
        $stmt->execute();
        $row = $stmt->fetch() ?: [];

        return [
            'total_methods' => (int) ($row['total_methods'] ?? 0),
            'active_methods' => (int) ($row['active_methods'] ?? 0),
            'maintenance_methods' => (int) ($row['maintenance_methods'] ?? 0),
            'disabled_methods' => (int) ($row['disabled_methods'] ?? 0),
            'total_transactions' => (int) ($row['total_transactions'] ?? 0),
            'captured_value' => (float) ($row['captured_value'] ?? 0),
        ];
    }

    public function listAdminMethodOverview(int $limit = 12): array
    {
        $stats = $this->adminStatsSubquery();
        $stmt = $this->db->prepare("
            SELECT
                pm.id,
                pm.code,
                pm.name,
                pm.provider,
                pm.channel_type,
                pm.status,
                pm.fee_rate_percent,
                pm.fixed_fee_amount,
                pm.settlement_cycle,
                pm.supports_refund,
                pm.supports_webhook,
                pm.supports_redirect,
                pm.display_order,
                pm.description,
                pm.created_at,
                pm.updated_at,
                COALESCE(stats.transaction_count, 0) AS transaction_count,
                COALESCE(stats.captured_value, 0) AS captured_value,
                COALESCE(stats.issue_count, 0) AS issue_count,
                stats.last_payment_at
            FROM payment_methods pm
            LEFT JOIN ({$stats}) stats ON stats.payment_method = pm.code
            ORDER BY pm.display_order ASC, pm.id ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function listAdminMethodOptions(): array
    {
        $stmt = $this->db->query("
            SELECT
                code,
                name,
                status,
                display_order
            FROM payment_methods
            ORDER BY display_order ASC, id ASC
        ");

        return $stmt->fetchAll() ?: [];
    }

    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM payment_methods WHERE code = :code';
        $params = ['code' => strtolower(trim($code))];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, $key === 'exclude_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }

    public function nextDisplayOrder(): int
    {
        $stmt = $this->db->query('SELECT COALESCE(MAX(display_order), 0) + 1 FROM payment_methods');

        return max(1, (int) $stmt->fetchColumn());
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO payment_methods (
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
            ) VALUES (
                :code,
                :name,
                :provider,
                :channel_type,
                :status,
                :fee_rate_percent,
                :fixed_fee_amount,
                :settlement_cycle,
                :supports_refund,
                :supports_webhook,
                :supports_redirect,
                :display_order,
                :description
            )
        ');
        $stmt->execute([
            'code' => strtolower(trim((string) ($data['code'] ?? ''))),
            'name' => $data['name'] ?? '',
            'provider' => strtolower(trim((string) ($data['provider'] ?? ''))),
            'channel_type' => $data['channel_type'] ?? 'gateway',
            'status' => $data['status'] ?? 'active',
            'fee_rate_percent' => $data['fee_rate_percent'] ?? 0,
            'fixed_fee_amount' => $data['fixed_fee_amount'] ?? 0,
            'settlement_cycle' => $data['settlement_cycle'] ?? 'instant',
            'supports_refund' => !empty($data['supports_refund']) ? 1 : 0,
            'supports_webhook' => !empty($data['supports_webhook']) ? 1 : 0,
            'supports_redirect' => !empty($data['supports_redirect']) ? 1 : 0,
            'display_order' => $data['display_order'] ?? 0,
            'description' => $data['description'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare('
            UPDATE payment_methods
            SET
                name = :name,
                provider = :provider,
                channel_type = :channel_type,
                status = :status,
                fee_rate_percent = :fee_rate_percent,
                fixed_fee_amount = :fixed_fee_amount,
                settlement_cycle = :settlement_cycle,
                supports_refund = :supports_refund,
                supports_webhook = :supports_webhook,
                supports_redirect = :supports_redirect,
                display_order = :display_order,
                description = :description,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'] ?? '',
            'provider' => strtolower(trim((string) ($data['provider'] ?? ''))),
            'channel_type' => $data['channel_type'] ?? 'gateway',
            'status' => $data['status'] ?? 'active',
            'fee_rate_percent' => $data['fee_rate_percent'] ?? 0,
            'fixed_fee_amount' => $data['fixed_fee_amount'] ?? 0,
            'settlement_cycle' => $data['settlement_cycle'] ?? 'instant',
            'supports_refund' => !empty($data['supports_refund']) ? 1 : 0,
            'supports_webhook' => !empty($data['supports_webhook']) ? 1 : 0,
            'supports_redirect' => !empty($data['supports_redirect']) ? 1 : 0,
            'display_order' => $data['display_order'] ?? 0,
            'description' => $data['description'] ?? null,
        ]);
    }

    private function adminStatsSubquery(): string
    {
        return "
            SELECT
                payment_method,
                COUNT(*) AS transaction_count,
                COALESCE(SUM(CASE WHEN payment_status = 'success' THEN amount ELSE 0 END), 0) AS captured_value,
                COALESCE(SUM(CASE WHEN payment_status IN ('failed', 'cancelled', 'expired', 'refunded') THEN 1 ELSE 0 END), 0) AS issue_count,
                MAX(COALESCE(completed_at, payment_date, initiated_at, created_at)) AS last_payment_at
            FROM payments
            GROUP BY payment_method
        ";
    }

    private function buildAdminWhereClause(array $filters, array &$params): string
    {
        $clauses = [];
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $params['search'] = '%' . mb_strtolower($search) . '%';
            $clauses[] = '(
                LOWER(pm.code) LIKE :search
                OR LOWER(pm.name) LIKE :search
                OR LOWER(pm.provider) LIKE :search
                OR LOWER(COALESCE(pm.description, \'\')) LIKE :search
            )';
        }

        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        if ($status !== '') {
            $params['status'] = $status;
            $clauses[] = 'pm.status = :status';
        }

        $channelType = strtolower(trim((string) ($filters['channel_type'] ?? '')));
        if ($channelType !== '') {
            $params['channel_type'] = $channelType;
            $clauses[] = 'pm.channel_type = :channel_type';
        }

        return $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);
    }

    private function bindParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
    }
}
