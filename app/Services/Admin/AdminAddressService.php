<?php

namespace App\Services\Admin;

use App\Repositories\AddressRepository;
use App\Core\Logger;
use Exception;

class AdminAddressService
{
    private AddressRepository $addressRepository;
    private Logger $logger;

    public function __construct()
    {
        $this->addressRepository = new AddressRepository();
        $this->logger = new Logger();
    }

    public function listAddresses(array $params): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = max(1, min(100, (int)($params['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;
        
        $search = $params['search'] ?? '';
        $label = $params['label'] ?? '';
        $status = $params['status'] ?? '';

        $addresses = $this->addressRepository->findAllPaginated($offset, $limit, $search, $label, $status);
        $total = $this->addressRepository->countAll($search, $label, $status);

        return [
            'addresses' => $addresses,
            'pagination' => [
                'total_items' => $total,
                'total_pages' => ceil($total / $limit),
                'current_page' => $page,
                'limit' => $limit
            ]
        ];
    }

    public function createAddress(array $data): array
    {
        try {
            if ($data['is_primary'] ?? 0) {
                $this->addressRepository->clearPrimary($data['user_id']);
            }
            
            $id = $this->addressRepository->create($data);
            return ['id' => $id, 'message' => 'Address created successfully.'];
        } catch (Exception $e) {
            $this->logger->error("Failed to create address", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function updateAddress(int $id, array $data): array
    {
        $existing = $this->addressRepository->findById($id);
        if (!$existing) {
            throw new Exception("Address not found.");
        }

        try {
            if ($data['is_primary'] ?? 0) {
                $this->addressRepository->clearPrimary($existing['user_id']);
            }

            $this->addressRepository->update($id, $data);
            return ['message' => 'Address updated successfully.'];
        } catch (Exception $e) {
            $this->logger->error("Failed to update address", ['error' => $e->getMessage(), 'id' => $id]);
            throw $e;
        }
    }

    public function deleteAddress(int $id): array
    {
        try {
            $this->addressRepository->delete($id);
            return ['data' => ['message' => 'Address deleted successfully.']];
        } catch (Exception $e) {
            return ['errors' => ['server' => ['Failed to delete address.']]];
        }
    }

    public function getStats(): array
    {
        try {
            return ['data' => $this->addressRepository->getStats()];
        } catch (Exception $e) {
            return ['errors' => ['server' => ['Failed to fetch address stats.']]];
        }
    }
}
