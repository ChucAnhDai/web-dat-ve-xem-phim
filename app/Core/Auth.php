<?php

namespace App\Core;

use Exception;

class Auth
{
    private string $secret;
    private int $ttlSeconds;

    public function __construct()
    {
        $this->secret = 'change_me_secret_key';
        $this->ttlSeconds = 60 * 60 * 24; // 24h
    }

    public function generateToken(array $payload): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload['exp'] = time() + $this->ttlSeconds;
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        $signature = $this->sign("{$header}.{$payloadEncoded}");

        return "{$header}.{$payloadEncoded}.{$signature}";
    }

    public function verifyToken(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception('Invalid token.');
        }

        [$header, $payload, $signature] = $parts;
        $expected = $this->sign("{$header}.{$payload}");
        if (!hash_equals($expected, $signature)) {
            throw new Exception('Invalid token signature.');
        }

        $data = json_decode($this->base64UrlDecode($payload), true);
        if (!$data || ($data['exp'] ?? 0) < time()) {
            throw new Exception('Token expired.');
        }

        return $data;
    }

    private function sign(string $data): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $data, $this->secret, true));
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
