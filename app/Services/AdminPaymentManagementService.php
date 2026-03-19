<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\PaymentRepository;
use App\Validators\AdminPaymentManagementValidator;
use PDO;
use Throwable;

class AdminPaymentManagementService
{
    private PDO $db;
    private PaymentRepository $payments;
    private PaymentMethodRepository $methods;
    private AdminPaymentManagementValidator $validator;
    private Logger $logger;

    public function __construct(
        ?PDO $db = null,
        ?PaymentRepository $payments = null,
        ?PaymentMethodRepository $methods = null,
        ?AdminPaymentManagementValidator $validator = null,
        ?Logger $logger = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->payments = $payments ?? new PaymentRepository($this->db);
        $this->methods = $methods ?? new PaymentMethodRepository($this->db);
        $this->validator = $validator ?? new AdminPaymentManagementValidator();
        $this->logger = $logger ?? new Logger();
    }

    public function listPayments(array $filters): array
    {
        $normalized = $this->validator->normalizePaymentFilters($filters);

        try {
            $page = $this->payments->paginateAdminPayments($normalized);
            $summary = $this->payments->summarizeAdminPayments($normalized);
            $methodOptions = $this->methods->listAdminMethodOptions();
        } catch (Throwable $exception) {
            $this->logger->error('Admin payment list failed', [
                'filters' => $normalized,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to load payment records.']], 500);
        }

        return $this->success([
            'items' => array_map([$this, 'formatPaymentRow'], $page['items']),
            'meta' => $this->paginationMeta($page),
            'summary' => $summary,
            'method_options' => array_map(static function (array $row): array {
                return [
                    'code' => $row['code'] ?? null,
                    'name' => $row['name'] ?? null,
                    'status' => $row['status'] ?? null,
                ];
            }, $methodOptions),
            'filters' => $normalized,
        ]);
    }

    public function getPayment(int $paymentId): array
    {
        try {
            $payment = $this->payments->findAdminPaymentById($paymentId);
            if ($payment === null) {
                return $this->error(['payment' => ['Payment record not found.']], 404);
            }
        } catch (Throwable $exception) {
            $this->logger->error('Admin payment detail failed', [
                'payment_id' => $paymentId,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to load payment detail.']], 500);
        }

        return $this->success($this->formatPaymentRow($payment));
    }

    public function listPaymentMethods(array $filters): array
    {
        $normalized = $this->validator->normalizePaymentMethodFilters($filters);

        try {
            $page = $this->methods->paginateAdminMethods($normalized);
            $summary = $this->methods->summarizeAdminMethods($normalized);
            $overview = $this->methods->listAdminMethodOverview();
        } catch (Throwable $exception) {
            $this->logger->error('Admin payment method list failed', [
                'filters' => $normalized,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to load payment methods.']], 500);
        }

        return $this->success([
            'items' => array_map([$this, 'formatPaymentMethodRow'], $page['items']),
            'meta' => $this->paginationMeta($page),
            'summary' => $summary,
            'overview' => array_map([$this, 'formatPaymentMethodRow'], $overview),
            'filters' => $normalized,
        ]);
    }

    public function getPaymentMethod(int $methodId): array
    {
        try {
            $method = $this->methods->findById($methodId);
            if ($method === null) {
                return $this->error(['payment_method' => ['Payment method not found.']], 404);
            }
        } catch (Throwable $exception) {
            $this->logger->error('Admin payment method detail failed', [
                'payment_method_id' => $methodId,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to load payment method detail.']], 500);
        }

        return $this->success($this->formatPaymentMethodRow($method));
    }

    public function createPaymentMethod(array $payload, ?int $actorId = null): array
    {
        $validation = $this->validator->validatePaymentMethodPayload($payload, true);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $data = $validation['data'];

        try {
            if ($data['code'] === null) {
                throw new AdminPaymentManagementException([
                    'code' => ['Payment method code is required.'],
                ], 422);
            }

            if ($this->methods->codeExists($data['code'])) {
                throw new AdminPaymentManagementException([
                    'code' => ['Payment method code already exists.'],
                ], 409);
            }

            if ($data['display_order'] === null) {
                $data['display_order'] = $this->methods->nextDisplayOrder();
            }

            $methodId = $this->transactional(function () use ($data): int {
                return $this->methods->create($data);
            });

            $method = $this->methods->findById($methodId);
            if ($method === null) {
                throw new \RuntimeException('Payment method disappeared after creation.');
            }
        } catch (AdminPaymentManagementException $exception) {
            $this->logger->info('Admin payment method creation blocked', [
                'actor_id' => $actorId,
                'code' => $data['code'] ?? null,
                'errors' => $exception->errors(),
            ]);

            return $this->error($exception->errors(), $exception->status());
        } catch (Throwable $exception) {
            $this->logger->error('Admin payment method creation failed', [
                'actor_id' => $actorId,
                'code' => $data['code'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to create payment method.']], 500);
        }

        $formatted = $this->formatPaymentMethodRow($method);
        $this->logger->info('Admin payment method created', [
            'actor_id' => $actorId,
            'payment_method_id' => $formatted['id'],
            'code' => $formatted['code'],
            'status' => $formatted['status'],
        ]);

        return $this->success($formatted, 201);
    }

    public function updatePaymentMethod(int $methodId, array $payload, ?int $actorId = null): array
    {
        $validation = $this->validator->validatePaymentMethodPayload($payload, false);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $data = $validation['data'];

        try {
            $existing = $this->methods->findById($methodId);
            if ($existing === null) {
                return $this->error(['payment_method' => ['Payment method not found.']], 404);
            }

            if (
                array_key_exists('code', $payload)
                && $data['code'] !== null
                && $data['code'] !== strtolower(trim((string) ($existing['code'] ?? '')))
            ) {
                throw new AdminPaymentManagementException([
                    'code' => ['Payment method code cannot be changed after creation.'],
                ], 409);
            }

            $merged = [
                'name' => $data['name'] ?? $existing['name'],
                'provider' => $data['provider'] ?? $existing['provider'],
                'channel_type' => $data['channel_type'] ?? $existing['channel_type'],
                'status' => $data['status'] ?? $existing['status'],
                'fee_rate_percent' => $data['fee_rate_percent'] ?? (float) ($existing['fee_rate_percent'] ?? 0),
                'fixed_fee_amount' => $data['fixed_fee_amount'] ?? (float) ($existing['fixed_fee_amount'] ?? 0),
                'settlement_cycle' => $data['settlement_cycle'] ?? $existing['settlement_cycle'],
                'supports_refund' => $data['supports_refund'] ?? ((int) ($existing['supports_refund'] ?? 0) === 1),
                'supports_webhook' => $data['supports_webhook'] ?? ((int) ($existing['supports_webhook'] ?? 0) === 1),
                'supports_redirect' => $data['supports_redirect'] ?? ((int) ($existing['supports_redirect'] ?? 0) === 1),
                'display_order' => $data['display_order'] ?? (int) ($existing['display_order'] ?? 0),
                'description' => array_key_exists('description', $data) ? $data['description'] : ($existing['description'] ?? null),
            ];

            $this->transactional(function () use ($methodId, $merged): void {
                $this->methods->update($methodId, $merged);
            });

            $method = $this->methods->findById($methodId);
            if ($method === null) {
                throw new \RuntimeException('Payment method disappeared after update.');
            }
        } catch (AdminPaymentManagementException $exception) {
            $this->logger->info('Admin payment method update blocked', [
                'actor_id' => $actorId,
                'payment_method_id' => $methodId,
                'errors' => $exception->errors(),
            ]);

            return $this->error($exception->errors(), $exception->status());
        } catch (Throwable $exception) {
            $this->logger->error('Admin payment method update failed', [
                'actor_id' => $actorId,
                'payment_method_id' => $methodId,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to update payment method.']], 500);
        }

        $formatted = $this->formatPaymentMethodRow($method);
        $this->logger->info('Admin payment method updated', [
            'actor_id' => $actorId,
            'payment_method_id' => $formatted['id'],
            'code' => $formatted['code'],
            'status' => $formatted['status'],
        ]);

        return $this->success($formatted);
    }

    public function archivePaymentMethod(int $methodId, ?int $actorId = null): array
    {
        try {
            $existing = $this->methods->findById($methodId);
            if ($existing === null) {
                return $this->error(['payment_method' => ['Payment method not found.']], 404);
            }

            $this->transactional(function () use ($methodId, $existing): void {
                $this->methods->update($methodId, [
                    'name' => $existing['name'],
                    'provider' => $existing['provider'],
                    'channel_type' => $existing['channel_type'],
                    'status' => 'disabled',
                    'fee_rate_percent' => (float) ($existing['fee_rate_percent'] ?? 0),
                    'fixed_fee_amount' => (float) ($existing['fixed_fee_amount'] ?? 0),
                    'settlement_cycle' => $existing['settlement_cycle'],
                    'supports_refund' => (int) ($existing['supports_refund'] ?? 0) === 1,
                    'supports_webhook' => (int) ($existing['supports_webhook'] ?? 0) === 1,
                    'supports_redirect' => (int) ($existing['supports_redirect'] ?? 0) === 1,
                    'display_order' => (int) ($existing['display_order'] ?? 0),
                    'description' => $existing['description'] ?? null,
                ]);
            });

            $method = $this->methods->findById($methodId);
            if ($method === null) {
                throw new \RuntimeException('Payment method disappeared after archive.');
            }
        } catch (Throwable $exception) {
            $this->logger->error('Admin payment method archive failed', [
                'actor_id' => $actorId,
                'payment_method_id' => $methodId,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to archive payment method.']], 500);
        }

        $formatted = $this->formatPaymentMethodRow($method);
        $this->logger->info('Admin payment method archived', [
            'actor_id' => $actorId,
            'payment_method_id' => $formatted['id'],
            'code' => $formatted['code'],
            'status' => $formatted['status'],
        ]);

        return $this->success($formatted);
    }

    private function formatPaymentRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'ticket_order_id' => isset($row['ticket_order_id']) && $row['ticket_order_id'] !== null ? (int) $row['ticket_order_id'] : null,
            'shop_order_id' => isset($row['shop_order_id']) && $row['shop_order_id'] !== null ? (int) $row['shop_order_id'] : null,
            'order_scope' => $row['order_scope'] ?? 'shop',
            'order_code' => $row['order_code'] ?? null,
            'order_status' => $row['order_status'] ?? null,
            'customer_name' => $row['customer_name'] ?? null,
            'contact_email' => $row['contact_email'] ?? null,
            'contact_phone' => $row['contact_phone'] ?? null,
            'payment_method' => $row['payment_method'] ?? null,
            'method_name' => $row['method_name'] ?? null,
            'method_provider' => $row['method_provider'] ?? null,
            'method_channel_type' => $row['method_channel_type'] ?? null,
            'method_status' => $row['method_status'] ?? null,
            'payment_status' => $row['payment_status'] ?? null,
            'amount' => (float) ($row['amount'] ?? 0),
            'currency' => $row['currency'] ?? 'VND',
            'transaction_code' => $row['transaction_code'] ?? null,
            'provider_transaction_code' => $row['provider_transaction_code'] ?? null,
            'provider_order_ref' => $row['provider_order_ref'] ?? null,
            'provider_response_code' => $row['provider_response_code'] ?? null,
            'provider_message' => $row['provider_message'] ?? null,
            'idempotency_key' => $row['idempotency_key'] ?? null,
            'checkout_url' => $row['checkout_url'] ?? null,
            'request_payload' => $row['request_payload'] ?? null,
            'callback_payload' => $row['callback_payload'] ?? null,
            'initiated_at' => $row['initiated_at'] ?? null,
            'completed_at' => $row['completed_at'] ?? null,
            'failed_at' => $row['failed_at'] ?? null,
            'refunded_at' => $row['refunded_at'] ?? null,
            'payment_date' => $row['payment_date'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'is_issue' => in_array(strtolower((string) ($row['payment_status'] ?? '')), ['failed', 'cancelled', 'expired', 'refunded'], true),
        ];
    }

    private function formatPaymentMethodRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'code' => $row['code'] ?? null,
            'name' => $row['name'] ?? null,
            'provider' => $row['provider'] ?? null,
            'channel_type' => $row['channel_type'] ?? null,
            'status' => $row['status'] ?? null,
            'fee_rate_percent' => (float) ($row['fee_rate_percent'] ?? 0),
            'fixed_fee_amount' => (float) ($row['fixed_fee_amount'] ?? 0),
            'settlement_cycle' => $row['settlement_cycle'] ?? null,
            'supports_refund' => (int) ($row['supports_refund'] ?? 0) === 1,
            'supports_webhook' => (int) ($row['supports_webhook'] ?? 0) === 1,
            'supports_redirect' => (int) ($row['supports_redirect'] ?? 0) === 1,
            'display_order' => (int) ($row['display_order'] ?? 0),
            'description' => $row['description'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'transaction_count' => (int) ($row['transaction_count'] ?? 0),
            'captured_value' => (float) ($row['captured_value'] ?? 0),
            'issue_count' => (int) ($row['issue_count'] ?? 0),
            'last_payment_at' => $row['last_payment_at'] ?? null,
        ];
    }

    private function paginationMeta(array $page): array
    {
        $total = (int) ($page['total'] ?? 0);
        $perPage = max(1, (int) ($page['per_page'] ?? 20));
        $totalPages = max(1, (int) ($page['total_pages'] ?? ceil($total / $perPage)));

        return [
            'total' => $total,
            'page' => max(1, (int) ($page['page'] ?? 1)),
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    private function transactional(callable $callback)
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $result = $callback();
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }

            return $result;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    private function success($data, int $status = 200): array
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
