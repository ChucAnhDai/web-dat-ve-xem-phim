<?php

namespace App\Controllers\Api\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\Admin\AdminAddressService;
use Exception;

class AdminAddressController
{
    private AdminAddressService $addressService;

    public function __construct()
    {
        $this->addressService = new AdminAddressService();
    }

    public function listAddresses(Request $request, Response $response): void
    {
        $params = $request->getBody();
        $result = $this->addressService->listAddresses($params);
        $response->json(['data' => $result]);
    }

    public function createAddress(Request $request, Response $response): void
    {
        $data = $request->getBody();
        
        $errors = Validator::required($data, ['user_id', 'address', 'city']);
        
        if (!empty($errors)) {
            $response->error('Validation failed', $errors, 422);
            return;
        }

        try {
            $result = $this->addressService->createAddress($data);
            $response->json(['data' => $result]);
        } catch (Exception $e) {
            $response->error($e->getMessage(), [], 500);
        }
    }

    public function updateAddress(Request $request, Response $response, array $args): void
    {
        $id = (int)($args['id'] ?? 0);
        $data = $request->getBody();

        try {
            $result = $this->addressService->updateAddress($id, $data);
            $response->json(['data' => $result]);
        } catch (Exception $e) {
            $response->error($e->getMessage(), [], 500);
        }
    }

    public function deleteAddress(Request $request, Response $response, array $params): void
    {
        $id = (int)$params['id'];
        $result = $this->addressService->deleteAddress($id);
        $response->json($result);
    }

    public function getStats(Request $request, Response $response): void
    {
        $result = $this->addressService->getStats();
        $response->json($result);
    }
}
