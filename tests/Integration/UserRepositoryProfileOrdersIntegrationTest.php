<?php

namespace Tests\Integration;

use App\Repositories\UserRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class UserRepositoryProfileOrdersIntegrationTest extends TestCase
{
    private PDO $db;
    private UserRepository $repository;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->createSchema();
        $this->seedData();

        $this->repository = new UserRepository($this->db);
    }

    public function testGetRecentOrdersReturnsSourceOrderIdsAndPortableFallbackCodes(): void
    {
        $orders = $this->repository->getRecentOrders(5, 10);

        $this->assertCount(2, $orders);
        $this->assertSame(11, (int) $orders[0]['order_id']);
        $this->assertSame('T-11', $orders[0]['order_code']);
        $this->assertSame('ticket', $orders[0]['order_type']);
        $this->assertSame(2, (int) $orders[0]['items_count']);

        $this->assertSame(22, (int) $orders[1]['order_id']);
        $this->assertSame('S-22', $orders[1]['order_code']);
        $this->assertSame('shop', $orders[1]['order_type']);
        $this->assertSame(3, (int) $orders[1]['items_count']);
    }

    private function createSchema(): void
    {
        $this->db->exec('CREATE TABLE ticket_orders (id INTEGER PRIMARY KEY, user_id INTEGER, order_code TEXT NULL, order_date TEXT, total_price REAL, status TEXT)');
        $this->db->exec('CREATE TABLE ticket_details (id INTEGER PRIMARY KEY, order_id INTEGER)');
        $this->db->exec('CREATE TABLE shop_orders (id INTEGER PRIMARY KEY, user_id INTEGER, order_code TEXT NULL, order_date TEXT, total_price REAL, status TEXT)');
        $this->db->exec('CREATE TABLE order_details (id INTEGER PRIMARY KEY, order_id INTEGER, quantity INTEGER)');
    }

    private function seedData(): void
    {
        $this->db->exec("INSERT INTO ticket_orders (id, user_id, order_code, order_date, total_price, status) VALUES (11, 5, '', '2026-03-18 15:00:00', 180000, 'paid')");
        $this->db->exec("INSERT INTO ticket_details (id, order_id) VALUES (101, 11), (102, 11)");

        $this->db->exec("INSERT INTO shop_orders (id, user_id, order_code, order_date, total_price, status) VALUES (22, 5, NULL, '2026-03-17 12:00:00', 255000, 'shipping')");
        $this->db->exec("INSERT INTO order_details (id, order_id, quantity) VALUES (201, 22, 1), (202, 22, 2)");
    }
}
