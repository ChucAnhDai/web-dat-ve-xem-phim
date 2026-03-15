<?php

namespace App\Support;

class VnpayGateway
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $root = dirname(__DIR__, 2);
        $settings = $config ?? require $root . '/config/payments.php';
        $this->config = is_array($settings['vnpay'] ?? null) ? $settings['vnpay'] : [];
    }

    public function isConfigured(): bool
    {
        return trim((string) ($this->config['tmn_code'] ?? '')) !== ''
            && trim((string) ($this->config['hash_secret'] ?? '')) !== ''
            && trim((string) ($this->config['pay_url'] ?? '')) !== '';
    }

    public function buildCheckoutUrl(array $params): array
    {
        $sorted = $this->sortParams($params);
        $query = http_build_query($sorted);
        $hash = hash_hmac('sha512', $query, (string) ($this->config['hash_secret'] ?? ''));

        return [
            'checkout_url' => rtrim((string) ($this->config['pay_url'] ?? ''), '?') . '?' . $query . '&vnp_SecureHash=' . $hash,
            'signature' => $hash,
            'query' => $sorted,
            'hash_data' => $query,
        ];
    }

    public function validateSignature(array $payload): bool
    {
        $received = trim((string) ($payload['vnp_SecureHash'] ?? ''));
        if ($received === '') {
            return false;
        }

        $input = $this->filterPayloadForSignature($payload);
        $query = http_build_query($this->sortParams($input));
        $expected = hash_hmac('sha512', $query, (string) ($this->config['hash_secret'] ?? ''));

        return hash_equals($expected, $received);
    }

    public function config(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function filterPayloadForSignature(array $payload): array
    {
        $filtered = [];
        foreach ($payload as $key => $value) {
            if (strpos((string) $key, 'vnp_') !== 0) {
                continue;
            }
            if ($key === 'vnp_SecureHash' || $key === 'vnp_SecureHashType') {
                continue;
            }
            $filtered[$key] = (string) $value;
        }

        return $filtered;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function sortParams(array $params): array
    {
        ksort($params);

        return $params;
    }
}
