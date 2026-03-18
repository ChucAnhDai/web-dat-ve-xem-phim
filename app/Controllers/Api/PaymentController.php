<?php

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\PaymentService;
use App\Support\TicketSessionManager;

class PaymentController
{
    private PaymentService $service;
    private TicketSessionManager $sessions;
    private Auth $auth;

    public function __construct(
        ?PaymentService $service = null,
        ?TicketSessionManager $sessions = null,
        ?Auth $auth = null
    ) {
        $this->service = $service ?? new PaymentService();
        $this->sessions = $sessions ?? new TicketSessionManager();
        $this->auth = $auth ?? new Auth();
    }

    public function createTicketVnpayIntent(Request $request, Response $response)
    {
        $sessionToken = $this->sessions->resolve($request);
        if ($sessionToken === null) {
            return $response->json(['errors' => ['hold' => ['Seat hold is missing or expired.']]], 409);
        }

        $result = $this->service->createTicketVnpayIntent(
            $request->getBody(),
            $sessionToken,
            $this->optionalUserId($request),
            [
                'base_url' => $this->baseUrl($request),
                'client_ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ]
        );

        return $this->respondJson($response, $result, 'VNPay payment intent created successfully');
    }

    public function handleVnpayReturn(Request $request, Response $response)
    {
        $result = $this->service->handleVnpayReturn($request->getBody());
        if (isset($result['errors'])) {
            $query = http_build_query([
                'status' => 'issue',
                'message' => $this->firstErrorMessage($result['errors'], 'Payment result could not be verified.'),
            ]);

            return $response->redirect($request->appBasePath() . '/payment-result?' . $query);
        }

        $payload = $result['data'] ?? [];
        $status = (string) ($payload['status'] ?? 'issue');
        $orderType = (string) ($payload['order_type'] ?? 'ticket');
        $orderCode = (string) ($payload['order_code'] ?? '');
        $paymentStatus = (string) ($payload['payment_status'] ?? '');
        $message = (string) ($payload['message'] ?? 'Payment result was returned from VNPay.');

        $query = http_build_query([
            'status' => $status,
            'order_type' => $orderType,
            'order_code' => $orderCode,
            'payment_status' => $paymentStatus,
            'message' => $message,
        ]);

        return $response->redirect($request->appBasePath() . '/payment-result?' . $query);
    }

    public function handleVnpayIpn(Request $request, Response $response)
    {
        $result = $this->service->handleVnpayIpn($request->getBody());
        $status = (int) ($result['status'] ?? 200);
        if (isset($result['errors'])) {
            return $response->json($result['errors'], $status);
        }

        return $response->json($result['data'] ?? [], $status);
    }

    private function respondJson(Response $response, array $result, ?string $successMessage = null)
    {
        $status = (int) ($result['status'] ?? 200);
        if (isset($result['errors'])) {
            return $response->json(['errors' => $result['errors']], $status);
        }

        $payload = ['data' => $result['data'] ?? []];
        if ($successMessage !== null) {
            $payload['message'] = $successMessage;
        }

        return $response->json($payload, $status);
    }

    private function optionalUserId(Request $request): ?int
    {
        $token = $request->bearerToken();
        if (!is_string($token) || trim($token) === '') {
            return null;
        }

        try {
            $payload = $this->auth->verifyToken($token);
            $userId = $payload['user_id'] ?? null;

            return $userId !== null ? (int) $userId : null;
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function baseUrl(Request $request): string
    {
        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return rtrim($scheme . '://' . $host . $request->appBasePath(), '/');
    }

    private function firstErrorMessage(array $errors, string $fallback): string
    {
        foreach ($errors as $messages) {
            if (is_array($messages) && $messages !== []) {
                return (string) $messages[0];
            }
        }

        return $fallback;
    }
}
