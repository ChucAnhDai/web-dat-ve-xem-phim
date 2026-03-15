<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PaymentConfigTest extends TestCase
{
    private array $snapshot = [];

    protected function setUp(): void
    {
        $this->snapshot = [
            'APP_URL' => getenv('APP_URL') !== false ? (string) getenv('APP_URL') : null,
            'VNPAY_RETURN_URL' => getenv('VNPAY_RETURN_URL') !== false ? (string) getenv('VNPAY_RETURN_URL') : null,
            'VNPAY_IPN_URL' => getenv('VNPAY_IPN_URL') !== false ? (string) getenv('VNPAY_IPN_URL') : null,
        ];
    }

    protected function tearDown(): void
    {
        foreach ($this->snapshot as $key => $value) {
            if ($value === null) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
                continue;
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    public function testPaymentsConfigBuildsDefaultVnpayUrlsFromAppUrl(): void
    {
        putenv('APP_URL=https://example.test/cinema');
        putenv('VNPAY_RETURN_URL');
        putenv('VNPAY_IPN_URL');
        $_ENV['APP_URL'] = 'https://example.test/cinema';
        unset($_ENV['VNPAY_RETURN_URL'], $_ENV['VNPAY_IPN_URL'], $_SERVER['VNPAY_RETURN_URL'], $_SERVER['VNPAY_IPN_URL']);

        $config = require __DIR__ . '/../../config/payments.php';

        $this->assertSame('https://example.test/cinema', $config['app_url']);
        $this->assertSame('https://example.test/cinema/api/payments/vnpay/return', $config['vnpay']['return_url']);
        $this->assertSame('https://example.test/cinema/api/payments/vnpay/ipn', $config['vnpay']['ipn_url']);
    }
}
