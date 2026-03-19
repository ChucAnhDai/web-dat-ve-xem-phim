<?php

namespace App\Controllers\Api\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\Admin\AdminRoleService;
use Exception;

class AdminRoleController
{
    private AdminRoleService $roleService;

    public function __construct()
    {
        $this->roleService = new AdminRoleService();
    }

    public function listRoles(Request $request, Response $response): void
    {
        $result = $this->roleService->listRoles();
        $response->json(['data' => $result]);
    }

    public function createRole(Request $request, Response $response): void
    {
        $data = $request->getBody();
        
        $errors = Validator::required($data, ['role_name']);

        if (!empty($errors)) {
            $response->error('Validation failed', $errors, 422);
            return;
        }

        try {
            $result = $this->roleService->createRole($data);
            $response->json(['data' => $result]);
        } catch (Exception $e) {
            $response->error($e->getMessage(), [], 500);
        }
    }

    public function updateRole(Request $request, Response $response, array $args): void
    {
        $id = (int)($args['id'] ?? 0);
        $data = $request->getBody();

        try {
            $result = $this->roleService->updateRole($id, $data);
            $response->json(['data' => $result]);
        } catch (Exception $e) {
            $response->error($e->getMessage(), [], 500);
        }
    }

    public function deleteRole(Request $request, Response $response, array $args): void
    {
        $id = (int)($args['id'] ?? 0);
        try {
            $result = $this->roleService->deleteRole($id);
            $response->json(['data' => $result]);
        } catch (Exception $e) {
            $response->error($e->getMessage(), [], 500);
        }
    }
}
