<?php

namespace Tests\Feature;

use App\Controllers\Api\ShopCatalogController;
use App\Core\Request;
use App\Core\Response;
use App\Services\ShopCatalogService;
use PHPUnit\Framework\TestCase;

class ShopCatalogControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
    }

    public function testListCategoriesReturnsDataPayload(): void
    {
        $service = new FeatureFakeShopCatalogService();
        $service->result = [
            'status' => 200,
            'data' => [
                'items' => [['id' => 1, 'slug' => 'snacks', 'name' => 'Snacks']],
                'meta' => ['total' => 1],
            ],
        ];
        $controller = new ShopCatalogController($service);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['search' => 'snacks'];
        $response = new ShopCatalogCapturingResponse();

        $controller->listCategories(new Request(), $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('Snacks', $response->payload['data']['items'][0]['name']);
    }

    public function testListProductsReturnsDataPayload(): void
    {
        $service = new FeatureFakeShopCatalogService();
        $service->result = [
            'status' => 200,
            'data' => [
                'items' => [['id' => 11, 'slug' => 'large-popcorn-combo', 'name' => 'Large Popcorn Combo']],
                'meta' => ['total' => 1, 'page' => 1, 'per_page' => 12, 'total_pages' => 1],
                'summary' => ['total' => 1, 'featured' => 1],
            ],
        ];
        $controller = new ShopCatalogController($service);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['search' => 'popcorn'];
        $response = new ShopCatalogCapturingResponse();

        $controller->listProducts(new Request(), $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('Large Popcorn Combo', $response->payload['data']['items'][0]['name']);
    }

    public function testGetProductDetailReturnsErrors(): void
    {
        $service = new FeatureFakeShopCatalogService();
        $service->result = [
            'status' => 404,
            'errors' => ['product' => ['Product not found.']],
        ];
        $controller = new ShopCatalogController($service);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $request = new Request();
        $request->setRouteParams(['slug' => 'missing-product']);
        $response = new ShopCatalogCapturingResponse();

        $controller->getProductDetail($request, $response);

        $this->assertSame(404, $response->statusCode);
        $this->assertSame(['Product not found.'], $response->payload['errors']['product']);
    }
}

class FeatureFakeShopCatalogService extends ShopCatalogService
{
    public array $result = [];

    public function __construct()
    {
    }

    public function listCategories(array $filters): array
    {
        return $this->result;
    }

    public function listProducts(array $filters): array
    {
        return $this->result;
    }

    public function getProductDetail(string $slug): array
    {
        return $this->result;
    }
}

class ShopCatalogCapturingResponse extends Response
{
    public int $statusCode = 200;
    public array $payload = [];

    public function setStatusCode(int $code): void
    {
        $this->statusCode = $code;
    }

    public function json($data, int $statusCode = 200): void
    {
        $this->setStatusCode($statusCode);
        $this->payload = $data;
    }
}
