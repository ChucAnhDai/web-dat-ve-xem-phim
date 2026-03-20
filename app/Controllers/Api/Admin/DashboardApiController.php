<?php

namespace App\Controllers\Api\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\DashboardRepository;

class DashboardApiController
{
    private DashboardRepository $dashboardRepository;

    public function __construct()
    {
        $this->dashboardRepository = new DashboardRepository();
    }

    public function getStats(Request $request, Response $response): void
    {
        try {
            $stats = $this->dashboardRepository->getStats();
            $ticketSales = $this->dashboardRepository->getTicketSalesChart();
            $revenueSplit = $this->dashboardRepository->getRevenueSplit();
            $recentTickets = $this->dashboardRepository->getRecentTicketOrders();
            $recentShopOrders = $this->dashboardRepository->getRecentShopOrders();
            $topMovies = $this->dashboardRepository->getTopMovies();
            $lowStock = $this->dashboardRepository->getLowStockProducts();

            $response->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'charts' => [
                        'ticketSales' => $ticketSales,
                        'revenueSplit' => $revenueSplit
                    ],
                    'recent' => [
                        'tickets' => $recentTickets,
                        'shop' => $recentShopOrders
                    ],
                    'topMovies' => $topMovies,
                    'lowStock' => $lowStock
                ]
            ]);
        } catch (\Exception $e) {
            $response->json([
                'success' => false,
                'message' => 'Lỗi khi lấy dữ liệu dashboard: ' . $e->getMessage()
            ], 500);
        }
    }
}
