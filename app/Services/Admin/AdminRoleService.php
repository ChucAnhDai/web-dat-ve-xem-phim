<?php

namespace App\Services\Admin;

use App\Repositories\RoleRepository;
use App\Core\Logger;
use Exception;

class AdminRoleService
{
    private RoleRepository $roleRepository;
    private Logger $logger;

    public function __construct()
    {
        $this->roleRepository = new RoleRepository();
        $this->logger = new Logger();
    }

    public function listRoles(): array
    {
        $roles = $this->roleRepository->findAll();
        $counts = $this->roleRepository->getRoleCounts();
        
        foreach ($roles as &$role) {
            // Simple mapping for demo/sync purposes
            $key = strtolower($role['role_name']);
            $role['user_count'] = $counts[$key] ?? 0;
        }

        return $roles;
    }

    public function createRole(array $data): array
    {
        try {
            $id = $this->roleRepository->create($data);
            return ['id' => $id, 'message' => 'Role created successfully.'];
        } catch (Exception $e) {
            $this->logger->error("Failed to create role", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function updateRole(int $id, array $data): array
    {
        if (!$this->roleRepository->findById($id)) {
            throw new Exception("Role not found.");
        }

        try {
            $this->roleRepository->update($id, $data);
            return ['message' => 'Role updated successfully.'];
        } catch (Exception $e) {
            $this->logger->error("Failed to update role", ['error' => $e->getMessage(), 'id' => $id]);
            throw $e;
        }
    }

    public function deleteRole(int $id): array
    {
        if ($this->roleRepository->delete($id)) {
            return ['message' => 'Role deleted successfully.'];
        }
        throw new Exception("Failed to delete role.");
    }
}
