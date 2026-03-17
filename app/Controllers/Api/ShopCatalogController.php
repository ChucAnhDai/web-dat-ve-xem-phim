<?php

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\ShopCatalogService;

class ShopCatalogController
{
    private ShopCatalogService $service;

    public function __construct(?ShopCatalogService $service = null)
    {
        $this->service = $service ?? new ShopCatalogService();
    }

    public function listCategories(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->listCategories($request->getBody()));
    }

    public function listProducts(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->listProducts($request->getBody()));
    }

    public function getProductDetail(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->getProductDetail((string) $request->getRouteParam('slug')));
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
