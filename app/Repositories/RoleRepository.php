<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class RoleRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM user_roles ORDER BY id ASC");
        $roles = $stmt->fetchAll();
        
        // Decode permissions JSON
        foreach ($roles as &$role) {
            if (isset($role['permissions']) && is_string($role['permissions'])) {
                $role['permissions'] = json_decode($role['permissions'], true) ?: [];
            }
        }
        
        return $roles;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM user_roles WHERE id = ?");
        $stmt->execute([$id]);
        $role = $stmt->fetch() ?: null;
        
        if ($role && isset($role['permissions']) && is_string($role['permissions'])) {
            $role['permissions'] = json_decode($role['permissions'], true) ?: [];
        }
        
        return $role;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("INSERT INTO user_roles (role_name, color, description, status, permissions) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['role_name'],
            $data['color'] ?? 'blue',
            $data['description'] ?? '',
            $data['status'] ?? 'Active',
            json_encode($data['permissions'] ?? [])
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("UPDATE user_roles SET role_name = ?, color = ?, description = ?, status = ?, permissions = ? WHERE id = ?");
        return $stmt->execute([
            $data['role_name'],
            $data['color'],
            $data['description'],
            $data['status'],
            json_encode($data['permissions']),
            $id
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM user_roles WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getRoleCounts(): array
    {
        // This is a bit tricky since users table currently uses ENUM role ('admin', 'user')
        // While user_roles defines 'Admin', 'Staff', etc.
        // For now, I'll map the ENUM to the roles if names match or just return 0 for others.
        
        $sql = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
        $stmt = $this->db->query($sql);
        $counts = $stmt->fetchAll();
        
        $mappedCounts = [];
        foreach ($counts as $row) {
            $mappedCounts[strtolower($row['role'])] = (int)$row['count'];
        }
        
        return $mappedCounts;
    }
}
