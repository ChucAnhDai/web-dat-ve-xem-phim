<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Core\Request;
use App\Core\Response;
use App\Controllers\Api\Admin\AdminAddressController;
use App\Controllers\Api\Admin\AdminRoleController;
use App\Core\Database;
use App\Repositories\AddressRepository;
use App\Repositories\RoleRepository;

class AdminAddressRoleTest extends TestCase
{
    private $db;
    private $addressController;
    private $roleController;

    protected function setUp(): void
    {
        $this->db = Database::getInstance();
        $this->addressController = new AdminAddressController();
        $this->roleController = new AdminRoleController();
    }

    public function testListAddresses()
    {
        $request = $this->createMock(Request::class);
        $request->expects($this->any())->method('getBody')->willReturn(['page' => 1, 'limit' => 5]);
        
        $response = $this->createMock(Response::class);
        $response->expects($this->once())
                 ->method('json')
                 ->with($this->callback(function($result) {
                     return isset($result['data']['addresses']) && isset($result['data']['pagination']);
                 }));

        $this->addressController->listAddresses($request, $response);
    }

    public function testListRoles()
    {
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        
        $response->expects($this->once())
                 ->method('json')
                 ->with($this->callback(function($result) {
                     return isset($result['data']) && is_array($result['data']) && count($result['data']) >= 1;
                 }));

        $this->roleController->listRoles($request, $response);
    }

    public function testCreateAndUpdateAddress()
    {
        $request = $this->createMock(Request::class);
        $request->expects($this->any())->method('getBody')->willReturn([
            'user_id' => 1, 
            'address' => 'Test Street',
            'city' => 'Test City',
            'label' => 'Home',
            'status' => 'Verified',
            'is_primary' => 1
        ]);

        $response = new class extends Response {
            public $data;
            public function json($data, $status = 200): void { $this->data = $data; }
            public function error($message, $errors = [], $status = 400): void { $this->data = ['error' => $message]; }
        };

        // Create
        $this->addressController->createAddress($request, $response);
        $this->assertArrayHasKey('data', $response->data);
        $addressId = $response->data['data']['id'];

        // Update
        $updateRequest = $this->createMock(Request::class);
        $updateRequest->expects($this->any())->method('getBody')->willReturn([
            'address' => 'Updated Street',
            'city' => 'Updated City',
            'label' => 'Office',
            'status' => 'Verified',
            'is_primary' => 0,
            'phone' => '123456789'
        ]);
        $this->addressController->updateAddress($updateRequest, $response, ['id' => $addressId]);
        $this->assertEquals('Address updated successfully.', $response->data['data']['message']);

        // Delete
        $this->addressController->deleteAddress($updateRequest, $response, ['id' => $addressId]);
        $this->assertEquals('Address deleted successfully.', $response->data['data']['message']);
    }
}
