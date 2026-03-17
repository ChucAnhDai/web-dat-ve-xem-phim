<?php

namespace Tests\Feature;

use App\Controllers\Admin\ProductManagementController;
use App\Core\Request;
use App\Core\Response;
use App\Services\ProductManagementService;
use PHPUnit\Framework\TestCase;

class ProductManagementControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        $_SERVER = [];
    }

    public function testListCategoriesReturnsDataPayload(): void
    {
        $service = new FeatureFakeProductManagementService();
        $service->result = [
            'status' => 200,
            'data' => [
                'items' => [['id' => 1, 'name' => 'Snacks']],
                'meta' => ['total' => 1, 'page' => 1, 'per_page' => 20, 'total_pages' => 1],
                'summary' => ['total' => 1, 'featured' => 1],
            ],
        ];
        $controller = new ProductManagementController($service);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['search' => 'snacks'];
        $response = new ProductFeatureCapturingResponse();

        $controller->listCategories(new Request(), $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame([['id' => 1, 'name' => 'Snacks']], $response->payload['data']['items']);
    }

    public function testCreateCategoryReturnsCreatedResponse(): void
    {
        $service = new FeatureFakeProductManagementService();
        $service->result = [
            'status' => 201,
            'data' => ['id' => 7, 'name' => 'Combos', 'slug' => 'combos'],
        ];
        $controller = new ProductManagementController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['name' => 'Combos'];
        $request = new Request();
        $request->setAttribute('auth', ['user_id' => 10, 'role' => 'admin']);
        $response = new ProductFeatureCapturingResponse();

        $controller->createCategory($request, $response);

        $this->assertSame(201, $response->statusCode);
        $this->assertSame('Product category created successfully', $response->payload['message']);
        $this->assertSame(7, $response->payload['data']['id']);
    }

    public function testArchiveCategoryReturnsErrors(): void
    {
        $service = new FeatureFakeProductManagementService();
        $service->result = [
            'status' => 409,
            'errors' => ['category' => ['Cannot archive product category while non-archived products are still assigned.']],
        ];
        $controller = new ProductManagementController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['_method' => 'DELETE'];
        $request = new Request();
        $request->setRouteParams(['id' => 9]);
        $request->setAttribute('auth', ['user_id' => 10, 'role' => 'admin']);
        $response = new ProductFeatureCapturingResponse();

        $controller->archiveCategory($request, $response);

        $this->assertSame(409, $response->statusCode);
        $this->assertSame(
            ['Cannot archive product category while non-archived products are still assigned.'],
            $response->payload['errors']['category']
        );
    }

    public function testListProductsReturnsDataPayload(): void
    {
        $service = new FeatureFakeProductManagementService();
        $service->result = [
            'status' => 200,
            'data' => [
                'items' => [['id' => 11, 'name' => 'Large Popcorn Combo', 'sku' => 'SKU-POP-001']],
                'meta' => ['total' => 1, 'page' => 1, 'per_page' => 20, 'total_pages' => 1],
                'summary' => ['total' => 1, 'in_stock' => 1],
            ],
        ];
        $controller = new ProductManagementController($service);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['search' => 'popcorn'];
        $response = new ProductFeatureCapturingResponse();

        $controller->listProducts(new Request(), $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('Large Popcorn Combo', $response->payload['data']['items'][0]['name']);
    }

    public function testCreateProductReturnsCreatedResponse(): void
    {
        $service = new FeatureFakeProductManagementService();
        $service->result = [
            'status' => 201,
            'data' => ['id' => 18, 'name' => 'Movie Mug', 'sku' => 'SKU-MUG-001'],
        ];
        $controller = new ProductManagementController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['name' => 'Movie Mug', 'sku' => 'SKU-MUG-001'];
        $request = new Request();
        $request->setAttribute('auth', ['user_id' => 10, 'role' => 'admin']);
        $response = new ProductFeatureCapturingResponse();

        $controller->createProduct($request, $response);

        $this->assertSame(201, $response->statusCode);
        $this->assertSame('Product created successfully', $response->payload['message']);
        $this->assertSame(18, $response->payload['data']['id']);
    }

    public function testListImagesReturnsDataPayload(): void
    {
        $service = new FeatureFakeProductManagementService();
        $service->result = [
            'status' => 200,
            'data' => [
                'items' => [['id' => 4, 'product_name' => 'Large Popcorn Combo', 'asset_type' => 'banner']],
                'meta' => ['total' => 1, 'page' => 1, 'per_page' => 20, 'total_pages' => 1],
                'summary' => ['total' => 1, 'banner' => 1],
            ],
        ];
        $controller = new ProductManagementController($service);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['asset_type' => 'banner'];
        $response = new ProductFeatureCapturingResponse();

        $controller->listImages(new Request(), $response);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('Large Popcorn Combo', $response->payload['data']['items'][0]['product_name']);
    }

    public function testCreateImageReturnsCreatedResponse(): void
    {
        $service = new FeatureFakeProductManagementService();
        $service->result = [
            'status' => 201,
            'data' => ['id' => 22, 'product_id' => 7, 'asset_type' => 'thumbnail'],
        ];
        $controller = new ProductManagementController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['product_id' => '7', 'asset_type' => 'thumbnail'];
        $request = new Request();
        $request->setAttribute('auth', ['user_id' => 10, 'role' => 'admin']);
        $response = new ProductFeatureCapturingResponse();

        $controller->createImage($request, $response);

        $this->assertSame(201, $response->statusCode);
        $this->assertSame('Product image created successfully', $response->payload['message']);
        $this->assertSame(22, $response->payload['data']['id']);
    }

    public function testCreateImageMapsUploadedFileToUploadKey(): void
    {
        $service = new FeatureFakeProductManagementService();
        $service->result = [
            'status' => 201,
            'data' => ['id' => 23, 'product_id' => 7, 'asset_type' => 'gallery'],
        ];
        $controller = new ProductManagementController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'product_id' => '7',
            'asset_type' => 'gallery',
            'source_type' => 'upload',
            'status' => 'active',
        ];
        $_FILES = [
            'image_file' => [
                'name' => 'gallery.png',
                'type' => 'image/png',
                'tmp_name' => 'C:\\temp\\gallery.png',
                'error' => UPLOAD_ERR_OK,
                'size' => 1024,
            ],
        ];
        $request = new Request();
        $request->setAttribute('auth', ['user_id' => 10, 'role' => 'admin']);
        $response = new ProductFeatureCapturingResponse();

        $controller->createImage($request, $response);

        $this->assertSame(201, $response->statusCode);
        $this->assertSame('image_file', $service->lastPayload['upload_key'] ?? null);
        $this->assertArrayHasKey('image_file', $service->lastPayload['_files'] ?? []);
        $this->assertSame(10, $service->lastActorId);
    }

    public function testCreateImagesBatchReturnsCreatedResponse(): void
    {
        $service = new FeatureFakeProductManagementService();
        $service->result = [
            'status' => 201,
            'data' => [
                'items' => [
                    ['id' => 31, 'product_id' => 7, 'asset_type' => 'gallery'],
                    ['id' => 32, 'product_id' => 7, 'asset_type' => 'gallery'],
                ],
                'count' => 2,
            ],
        ];
        $controller = new ProductManagementController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'items_manifest' => json_encode([
                [
                    'product_id' => 7,
                    'asset_type' => 'gallery',
                    'source_type' => 'upload',
                    'upload_key' => 'image_file_0',
                    'status' => 'active',
                ],
                [
                    'product_id' => 7,
                    'asset_type' => 'gallery',
                    'source_type' => 'upload',
                    'upload_key' => 'image_file_1',
                    'status' => 'active',
                ],
            ]),
        ];
        $_FILES = [
            'image_file_0' => [
                'name' => 'gallery-1.png',
                'type' => 'image/png',
                'tmp_name' => 'C:\\temp\\gallery-1.png',
                'error' => UPLOAD_ERR_OK,
                'size' => 2048,
            ],
            'image_file_1' => [
                'name' => 'gallery-2.png',
                'type' => 'image/png',
                'tmp_name' => 'C:\\temp\\gallery-2.png',
                'error' => UPLOAD_ERR_OK,
                'size' => 4096,
            ],
        ];
        $request = new Request();
        $request->setAttribute('auth', ['user_id' => 10, 'role' => 'admin']);
        $response = new ProductFeatureCapturingResponse();

        $controller->createImagesBatch($request, $response);

        $this->assertSame(201, $response->statusCode);
        $this->assertSame('Product images created successfully', $response->payload['message']);
        $this->assertSame(2, $response->payload['data']['count']);
        $this->assertArrayHasKey('image_file_0', $service->lastPayload['_files'] ?? []);
        $this->assertArrayHasKey('image_file_1', $service->lastPayload['_files'] ?? []);
    }

    public function testArchiveImageReturnsErrors(): void
    {
        $service = new FeatureFakeProductManagementService();
        $service->result = [
            'status' => 404,
            'errors' => ['image' => ['Product image not found.']],
        ];
        $controller = new ProductManagementController($service);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['_method' => 'DELETE'];
        $request = new Request();
        $request->setRouteParams(['id' => 44]);
        $request->setAttribute('auth', ['user_id' => 10, 'role' => 'admin']);
        $response = new ProductFeatureCapturingResponse();

        $controller->archiveImage($request, $response);

        $this->assertSame(404, $response->statusCode);
        $this->assertSame(['Product image not found.'], $response->payload['errors']['image']);
    }
}

class FeatureFakeProductManagementService extends ProductManagementService
{
    public array $result = [];
    public array $lastPayload = [];
    public ?int $lastActorId = null;

    public function __construct()
    {
    }

    public function listCategories(array $filters): array
    {
        return $this->result;
    }

    public function createCategory(array $payload, ?int $actorId = null): array
    {
        return $this->result;
    }

    public function archiveCategory(int $id, ?int $actorId = null): array
    {
        return $this->result;
    }

    public function listProducts(array $filters): array
    {
        return $this->result;
    }

    public function createProduct(array $payload, ?int $actorId = null): array
    {
        $this->lastPayload = $payload;
        $this->lastActorId = $actorId;

        return $this->result;
    }

    public function listImages(array $filters): array
    {
        return $this->result;
    }

    public function createImage(array $payload, ?int $actorId = null): array
    {
        $this->lastPayload = $payload;
        $this->lastActorId = $actorId;

        return $this->result;
    }

    public function createImagesBatch(array $payload, ?int $actorId = null): array
    {
        $this->lastPayload = $payload;
        $this->lastActorId = $actorId;

        return $this->result;
    }

    public function archiveImage(int $id, ?int $actorId = null): array
    {
        return $this->result;
    }
}

class ProductFeatureCapturingResponse extends Response
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
