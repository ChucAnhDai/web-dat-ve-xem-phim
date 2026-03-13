<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;

class AdminController
{
    private function renderPage(Response $response, string $view, string $title): void
    {
        $response->view($view, [
            'title' => $title,
            'layout' => 'admin/layouts/main',
        ]);
    }

    public function showDashboard(Request $request, Response $response): void
    {
        $this->renderPage($response, 'admin/pages/dashboard', 'Dashboard — CineShop Admin');
    }

    public function showMovies(Request $request, Response $response): void
    {
        $this->renderPage($response, 'admin/pages/movies', 'Movies — CineShop Admin');
    }

    public function showCinemas(Request $request, Response $response): void
    {
        $this->renderPage($response, 'admin/pages/cinemas', 'Cinemas — CineShop Admin');
    }

    public function showPayments(Request $request, Response $response): void
    {
        $this->renderPage($response, 'admin/pages/payments', 'Payments — CineShop Admin');
    }

    public function showProducts(Request $request, Response $response): void
    {
        $this->renderPage($response, 'admin/pages/products', 'Products — CineShop Admin');
    }

    public function showPromotions(Request $request, Response $response): void
    {
        $this->renderPage($response, 'admin/pages/promotions', 'Promotions — CineShop Admin');
    }

    public function showSeats(Request $request, Response $response): void
    {
        $this->renderPage($response, 'admin/pages/seats', 'Seats — CineShop Admin');
    }

    public function showShopOrders(Request $request, Response $response): void
    {
        $this->renderPage($response, 'admin/pages/shop-orders', 'Shop Orders — CineShop Admin');
    }

    public function showShowtimes(Request $request, Response $response): void
    {
        $this->renderPage($response, 'admin/pages/showtimes', 'Showtimes — CineShop Admin');
    }

    public function showTicketOrders(Request $request, Response $response): void
    {
        $this->renderPage($response, 'admin/pages/ticket-orders', 'Ticket Orders — CineShop Admin');
    }

    public function showUsers(Request $request, Response $response): void
    {
        $this->renderPage($response, 'admin/pages/users', 'Users — CineShop Admin');
    }

    public function showTest(Request $request, Response $response): void
    {
        $this->renderPage($response, 'admin/pages/test', 'Admin Test — CineShop Admin');
    }
}
