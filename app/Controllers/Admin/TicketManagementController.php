<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Services\AdminTicketManagementService;

class TicketManagementController
{
    private AdminTicketManagementService $service;

    public function __construct(?AdminTicketManagementService $service = null)
    {
        $this->service = $service ?? new AdminTicketManagementService();
    }

    public function listTicketOrders(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->listTicketOrders($request->getBody()));
    }

    public function getTicketOrder(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->getTicketOrder((int) $request->getRouteParam('id')));
    }

    public function listTicketDetails(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->listTicketDetails($request->getBody()));
    }

    public function getTicketDetail(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->getTicketDetail((int) $request->getRouteParam('id')));
    }

    public function listActiveHolds(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->listActiveHolds($request->getBody()));
    }

    private function respond(Response $response, array $result)
    {
        $status = (int) ($result['status'] ?? 200);
        if (isset($result['errors'])) {
            return $response->json(['errors' => $result['errors']], $status);
        }

        return $response->json(['data' => $result['data'] ?? []], $status);
    }
}
