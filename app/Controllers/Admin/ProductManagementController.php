<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Services\ProductManagementService;

class ProductManagementController
{
    private ProductManagementService $service;

    public function __construct(?ProductManagementService $service = null)
    {
        $this->service = $service ?? new ProductManagementService();
    }

    public function listCategories(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->listCategories($request->getBody()));
    }

    public function getCategory(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->getCategory((int) $request->getRouteParam('id')));
    }

    public function createCategory(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->createCategory($request->getBody(), $this->actorId($request)),
            'Product category created successfully'
        );
    }

    public function updateCategory(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->updateCategory((int) $request->getRouteParam('id'), $request->getBody(), $this->actorId($request)),
            'Product category updated successfully'
        );
    }

    public function archiveCategory(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->archiveCategory((int) $request->getRouteParam('id'), $this->actorId($request)),
            'Product category archived successfully'
        );
    }

    public function listProducts(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->listProducts($request->getBody()));
    }

    public function getProduct(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->getProduct((int) $request->getRouteParam('id')));
    }

    public function createProduct(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->createProduct($this->buildPayload($request), $this->actorId($request)),
            'Product created successfully'
        );
    }

    public function updateProduct(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->updateProduct((int) $request->getRouteParam('id'), $this->buildPayload($request), $this->actorId($request)),
            'Product updated successfully'
        );
    }

    public function archiveProduct(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->archiveProduct((int) $request->getRouteParam('id'), $this->actorId($request)),
            'Product archived successfully'
        );
    }

    public function listImages(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->listImages($request->getBody()));
    }

    public function getImage(Request $request, Response $response)
    {
        return $this->respond($response, $this->service->getImage((int) $request->getRouteParam('id')));
    }

    public function createImage(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->createImage($this->buildPayload($request), $this->actorId($request)),
            'Product image created successfully'
        );
    }

    public function createImagesBatch(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->createImagesBatch($this->buildPayload($request), $this->actorId($request)),
            'Product images created successfully'
        );
    }

    public function updateImage(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->updateImage((int) $request->getRouteParam('id'), $this->buildPayload($request), $this->actorId($request)),
            'Product image updated successfully'
        );
    }

    public function archiveImage(Request $request, Response $response)
    {
        return $this->respond(
            $response,
            $this->service->archiveImage((int) $request->getRouteParam('id'), $this->actorId($request)),
            'Product image archived successfully'
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
        $userId = $auth['user_id'] ?? null;

        return $userId !== null ? (int) $userId : null;
    }

    private function buildPayload(Request $request): array
    {
        $payload = $request->getBody();
        $payload['_files'] = $request->getFiles();

        if (
            ($payload['source_type'] ?? null) === 'upload'
            && empty($payload['upload_key'])
            && array_key_exists('image_file', $payload['_files'])
        ) {
            $payload['upload_key'] = 'image_file';
        }

        $payload['_app_base_path'] = $request->appBasePath();
        $payload['_public_base_path'] = $request->publicBasePath();

        return $payload;
    }
}
