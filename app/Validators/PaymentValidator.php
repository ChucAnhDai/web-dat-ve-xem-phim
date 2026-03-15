<?php

namespace App\Validators;

class PaymentValidator
{
    private const VNPAY_SUCCESS_CODE = '00';

    private TicketOrderValidator $orders;

    public function __construct(?TicketOrderValidator $orders = null)
    {
        $this->orders = $orders ?? new TicketOrderValidator();
    }

    public function validateTicketIntentPayload(array $payload): array
    {
        $result = $this->orders->validateCreatePayload($payload);
        $errors = $result['errors'];
        $data = $result['data'];

        if (($data['payment_method'] ?? null) !== 'vnpay') {
            $errors['payment_method'][] = 'VNPay payment intent requires payment_method = vnpay.';
        }

        $bankCode = strtoupper(trim((string) ($payload['bank_code'] ?? '')));
        if ($bankCode !== '' && !preg_match('/^[A-Z0-9_]{2,20}$/', $bankCode)) {
            $errors['bank_code'][] = 'Bank code is invalid.';
        }

        $data['bank_code'] = $bankCode !== '' ? $bankCode : null;

        return [
            'data' => $data,
            'errors' => $errors,
        ];
    }

    public function validateVnpayCallbackPayload(array $payload): array
    {
        $errors = [];
        $txnRef = trim((string) ($payload['vnp_TxnRef'] ?? ''));
        $amount = (int) preg_replace('/\D+/', '', (string) ($payload['vnp_Amount'] ?? '0'));
        $responseCode = trim((string) ($payload['vnp_ResponseCode'] ?? ''));
        $transactionStatus = trim((string) ($payload['vnp_TransactionStatus'] ?? ''));
        $secureHash = trim((string) ($payload['vnp_SecureHash'] ?? ''));
        $transactionNo = trim((string) ($payload['vnp_TransactionNo'] ?? ''));

        if ($txnRef === '') {
            $errors['vnp_TxnRef'][] = 'VNPay transaction reference is required.';
        }
        if ($amount <= 0) {
            $errors['vnp_Amount'][] = 'VNPay amount is invalid.';
        }
        if ($responseCode === '') {
            $errors['vnp_ResponseCode'][] = 'VNPay response code is required.';
        }
        if ($secureHash === '') {
            $errors['vnp_SecureHash'][] = 'VNPay secure hash is required.';
        }

        return [
            'data' => [
                'provider_order_ref' => $txnRef,
                'amount' => $amount,
                'response_code' => $responseCode,
                'transaction_status' => $transactionStatus,
                'secure_hash' => $secureHash,
                'provider_transaction_code' => $transactionNo !== '' ? $transactionNo : null,
                'message' => trim((string) ($payload['vnp_OrderInfo'] ?? '')),
                'raw_payload' => $payload,
            ],
            'errors' => $errors,
        ];
    }

    public function isSuccessfulVnpayResponse(array $data): bool
    {
        $responseCode = trim((string) ($data['response_code'] ?? ''));
        $transactionStatus = trim((string) ($data['transaction_status'] ?? ''));

        if ($responseCode !== self::VNPAY_SUCCESS_CODE) {
            return false;
        }

        return $transactionStatus === '' || $transactionStatus === self::VNPAY_SUCCESS_CODE;
    }
}
