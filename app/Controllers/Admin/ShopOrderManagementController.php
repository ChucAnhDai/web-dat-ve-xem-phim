<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Services\AdminShopOrderManagementService;

class ShopOrderManagementController
{
    private AdminShopOrderManagementService $service;

    public function __construct(?AdminShopOrderManagementService $service = null)
    {
        $this->service = $service ?? new AdminShopOrderManagementService();
    }

    public function listShopOrders(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->listShopOrders($request->getBody()));
    }

    public function getShopOrder(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->getShopOrder((int) $request->getRouteParam('id')));
    }

    public function updateShopOrderStatus(Request $request, Response $response)
    {
        $auth = $request->getAttribute('auth', []);
        $actorId = is_array($auth) && isset($auth['user_id']) && is_numeric($auth['user_id'])
            ? (int) $auth['user_id']
            : null;

        return $this->respond($response, $this->service->updateShopOrderStatus(
            (int) $request->getRouteParam('id'),
            $request->getBody(),
            $actorId
        ));
    }

    public function listOrderDetails(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->listOrderDetails($request->getBody()));
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
