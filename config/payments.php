<?php

$appUrl = (function (): string {
    $envAppUrl = trim((string) (getenv('APP_URL') ?: ''));
    if ($envAppUrl !== '') {
        return rtrim($envAppUrl, '/');
    }

    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return 'http://localhost/web-dat-ve-xem-phim';
    }

    $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $publicBase = rtrim(dirname($scriptName), '/');
    $publicBase = $publicBase === '.' ? '' : $publicBase;
    $appBase = preg_replace('#/public$#', '', $publicBase) ?: '';

    return rtrim($scheme . '://' . $host . $appBase, '/');
})();

$returnUrl = trim((string) (getenv('VNPAY_RETURN_URL') ?: ''));
if ($returnUrl === '') {
    $returnUrl = $appUrl . '/api/payments/vnpay/return';
}

$ipnUrl = trim((string) (getenv('VNPAY_IPN_URL') ?: ''));
if ($ipnUrl === '') {
    $ipnUrl = $appUrl . '/api/payments/vnpay/ipn';
}

return [
    'app_url' => $appUrl,
    'currency' => 'VND',
    'vnpay' => [
        'enabled' => true,
        'version' => '2.1.0',
        'command' => 'pay',
        'locale' => 'vn',
        'curr_code' => 'VND',
        'order_type' => 'other',
        'expire_minutes' => 15,
        'tmn_code' => getenv('VNPAY_TMN_CODE') ?: '',
        'hash_secret' => getenv('VNPAY_HASH_SECRET') ?: '',
        'pay_url' => getenv('VNPAY_PAY_URL') ?: 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
        'return_url' => $returnUrl,
        'ipn_url' => $ipnUrl,
    ],
];
