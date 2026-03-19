<?php

namespace App\Controllers\Api\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Services\Admin\AdminUserService;

class AdminUserController
{
    private AdminUserService $userService;

    public function __construct(?AdminUserService $userService = null)
    {
        $this->userService = $userService ?? new AdminUserService();
    }

    public function listUsers(Request $request, Response $response): void
    {
        $queryParams = $request->getBody();
        $page = (int)($queryParams['page'] ?? 1);
        $limit = (int)($queryParams['limit'] ?? 10);
        $search = $queryParams['search'] ?? '';
        $role = $queryParams['role'] ?? '';
        $status = $queryParams['status'] ?? '';

        $result = $this->userService->listUsers($page, $limit, $search, $role, $status);

        if (isset($result['errors'])) {
            $response->json($result, 400);
            return;
        }

        $response->json($result);
    }

    public function getUser(Request $request, Response $response, array $params): void
    {
        $id = (int)$params['id'];
        $result = $this->userService->getUser($id);

        if (isset($result['errors'])) {
            $response->json($result, 404);
            return;
        }

        $response->json($result);
    }

    public function createUser(Request $request, Response $response): void
    {
        $data = $request->getBody();
        $result = $this->userService->createUser($data);

        if (isset($result['errors'])) {
            $response->json($result, 400);
            return;
        }

        $response->json($result, 201);
    }

    public function updateUser(Request $request, Response $response, array $params): void
    {
        $id = (int)$params['id'];
        $data = $request->getBody();
        $result = $this->userService->updateUser($id, $data);

        if (isset($result['errors'])) {
            $response->json($result, 400);
            return;
        }

        $response->json($result);
    }

    public function deleteUser(Request $request, Response $response, array $params): void
    {
        $id = (int)$params['id'];
        $result = $this->userService->deleteUser($id);
        $response->json($result);
    }

    public function getStats(Request $request, Response $response): void
    {
        $result = $this->userService->getStats();
        $response->json($result);
    }
}
