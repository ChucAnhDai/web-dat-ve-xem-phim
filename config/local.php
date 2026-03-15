<?php

return [
    // Base URL for local XAMPP access through the project root rewrite.
    'APP_URL' => 'http://localhost/web-dat-ve-xem-phim',

    // VNPay credentials supplied for local integration.
    'VNPAY_TMN_CODE' => 'PCDCFU5B',
    'VNPAY_HASH_SECRET' => 'VLZNUQLVWFLJDKK7P3SAP2K940I5JIGH',
    'VNPAY_PAY_URL' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',

    // Callback endpoints can stay as-is for local development.
    'VNPAY_RETURN_URL' => 'http://localhost/web-dat-ve-xem-phim/api/payments/vnpay/return',
    'VNPAY_IPN_URL' => 'http://localhost/web-dat-ve-xem-phim/api/payments/vnpay/ipn',
];
