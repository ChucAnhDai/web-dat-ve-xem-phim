<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class AddressRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAllPaginated(int $offset, int $limit, string $search = '', string $label = '', string $status = ''): array
    {
        $sql = "SELECT a.*, u.name as user_name, u.phone as user_phone 
                FROM addresses a
                JOIN users u ON a.user_id = u.id
                WHERE 1=1";
        $params = [];

        if ($search) {
            $sql .= " AND (u.name LIKE ? OR a.address LIKE ? OR a.city LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if ($label) {
            $sql .= " AND a.label = ?";
            $params[] = $label;
        }

        if ($status) {
            $sql .= " AND a.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY a.created_at DESC LIMIT $limit OFFSET $offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countAll(string $search = '', string $label = '', string $status = ''): int
    {
        $sql = "SELECT COUNT(*) FROM addresses a JOIN users u ON a.user_id = u.id WHERE 1=1";
        $params = [];

        if ($search) {
            $sql .= " AND (u.name LIKE ? OR a.address LIKE ? OR a.city LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if ($label) {
            $sql .= " AND a.label = ?";
            $params[] = $label;
        }

        if ($status) {
            $sql .= " AND a.status = ?";
            $params[] = $status;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT a.*, u.name as user_name FROM addresses a JOIN users u ON a.user_id = u.id WHERE a.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("INSERT INTO addresses (user_id, address, city, district, label, phone, is_primary, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['user_id'],
            $data['address'],
            $data['city'],
            $data['district'] ?? '',
            $data['label'] ?? 'Home',
            $data['phone'] ?? null,
            $data['is_primary'] ?? 0,
            $data['status'] ?? 'Verified'
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("UPDATE addresses SET address = ?, city = ?, district = ?, label = ?, phone = ?, is_primary = ?, status = ? WHERE id = ?");
        return $stmt->execute([
            $data['address'],
            $data['city'],
            $data['district'] ?? '',
            $data['label'],
            $data['phone'],
            $data['is_primary'],
            $data['status'],
            $id
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM addresses WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function clearPrimary(int $userId): bool
    {
        $stmt = $this->db->prepare("UPDATE addresses SET is_primary = 0 WHERE user_id = ?");
        return $stmt->execute([$userId]);
    }

    public function getStats(): array
    {
        return [
            'total' => (int) $this->scalar('SELECT COUNT(*) FROM addresses'),
            'default' => (int) $this->scalar('SELECT COUNT(*) FROM addresses WHERE is_primary = 1'),
            'pickup' => (int) $this->scalar("SELECT COUNT(*) FROM addresses WHERE label = 'Pickup'"),
            'needs_review' => (int) $this->scalar("SELECT COUNT(*) FROM addresses WHERE status = 'Pending'")
        ];
    }

    private function scalar(string $sql, array $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
