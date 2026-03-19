<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Services\AdminPaymentManagementService;

class PaymentManagementController
{
    private AdminPaymentManagementService $service;

    public function __construct(?AdminPaymentManagementService $service = null)
    {
        $this->service = $service ?? new AdminPaymentManagementService();
    }

    public function listPayments(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->listPayments($request->getBody()));
    }

    public function getPayment(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->getPayment((int) $request->getRouteParam('id')));
    }

    public function listPaymentMethods(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->listPaymentMethods($request->getBody()));
    }

    public function getPaymentMethod(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->getPaymentMethod((int) $request->getRouteParam('id')));
    }

    public function createPaymentMethod(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->createPaymentMethod($request->getBody(), $this->actorId($request)),
            'Payment method created successfully'
        );
    }

    public function updatePaymentMethod(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->updatePaymentMethod((int) $request->getRouteParam('id'), $request->getBody(), $this->actorId($request)),
            'Payment method updated successfully'
        );
    }

    public function archivePaymentMethod(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->archivePaymentMethod((int) $request->getRouteParam('id'), $this->actorId($request)),
            'Payment method archived successfully'
        );
    }

    private function respond(Response $response, array $result, ?string $successMessage = null)
    {
        $status = (int) ($result['status'] ?? 200);
        if (isset($result['errors'])) {
            return $response->json(['errors' => $result['errors']], $status);
        }

        $payload = ['data' => $result['data'] ?? null];
        if ($successMessage !== null) {
            $payload['message'] = $successMessage;
        }

        return $response->json($payload, $status);
    }

    private function actorId(Request $request): ?int
    {
        $auth = $request->getAttribute('auth', []);
        $userId = is_array($auth) ? ($auth['user_id'] ?? null) : null;

        return is_numeric($userId) ? (int) $userId : null;
    }
}
