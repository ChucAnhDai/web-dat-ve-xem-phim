<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;

class AdminController
{
    private function renderPage(Response $response, string $view, string $title, string $activePage): void
    {
        $response->view($view, [
            'title' => $title,
            'layout' => 'admin/layouts/main',
            'activePage' => $activePage,
        ]);
    }

    private function resolveActivePage(Request $request, string $defaultPage, array $allowedPages = []): string
    {
        $body = $request->getBody();
        $section = $body['section'] ?? '';

        if (is_string($section) && in_array($section, $allowedPages, true)) {
            return $section;
        }

        return $defaultPage;
    }

    public function showDashboard(Request $request, Response $response): void
    {
        $activePage = $this->resolveActivePage($request, 'dashboard', ['dashboard', 'banners', 'notifications', 'system-settings', 'admin-profile']);
        $titles = [
            'dashboard' => 'Dashboard - CineShop Admin',
            'banners' => 'Banners - CineShop Admin',
            'notifications' => 'Notifications - CineShop Admin',
            'system-settings' => 'System Settings - CineShop Admin',
            'admin-profile' => 'Admin Profile - CineShop Admin',
        ];

        $this->renderPage($response, 'admin/pages/dashboard/index', $titles[$activePage] ?? $titles['dashboard'], $activePage);
    }

    public function showMovies(Request $request, Response $response): void
    {
        $activePage = $this->resolveActivePage($request, 'movies', ['movies', 'categories', 'movie-images', 'reviews']);
        $titles = [
            'movies' => 'Movies - CineShop Admin',
            'categories' => 'Categories - CineShop Admin',
            'movie-images' => 'Movie Images - CineShop Admin',
            'reviews' => 'Reviews - CineShop Admin',
        ];

        $this->renderPage($response, 'admin/pages/movies/index', $titles[$activePage] ?? $titles['movies'], $activePage);
    }

    public function showCinemas(Request $request, Response $response): void
    {
        $activePage = $this->resolveActivePage($request, 'cinemas', ['cinemas', 'rooms']);
        $titles = [
            'cinemas' => 'Cinemas - CineShop Admin',
            'rooms' => 'Rooms - CineShop Admin',
        ];

        $this->renderPage($response, 'admin/pages/cinemas/index', $titles[$activePage] ?? $titles['cinemas'], $activePage);
    }

    public function showPayments(Request $request, Response $response): void
    {
        $activePage = $this->resolveActivePage($request, 'payments', ['payments', 'payment-methods']);
        $titles = [
            'payments' => 'Payments - CineShop Admin',
            'payment-methods' => 'Payment Methods - CineShop Admin',
        ];

        $this->renderPage($response, 'admin/pages/payments/index', $titles[$activePage] ?? $titles['payments'], $activePage);
    }

    public function showProducts(Request $request, Response $response): void
    {
        $activePage = $this->resolveActivePage($request, 'products', ['products', 'product-categories', 'product-images']);
        $titles = [
            'products' => 'Products - CineShop Admin',
            'product-categories' => 'Product Categories - CineShop Admin',
            'product-images' => 'Product Images - CineShop Admin',
        ];

        $this->renderPage($response, 'admin/pages/products/index', $titles[$activePage] ?? $titles['products'], $activePage);
    }

    public function showPromotions(Request $request, Response $response): void
    {
        $activePage = $this->resolveActivePage($request, 'promotions', ['promotions', 'product-promotions']);
        $titles = [
            'promotions' => 'Promotions - CineShop Admin',
            'product-promotions' => 'Product Promotions - CineShop Admin',
        ];

        $this->renderPage($response, 'admin/pages/promotions/index', $titles[$activePage] ?? $titles['promotions'], $activePage);
    }

    public function showSeats(Request $request, Response $response): void
    {
        $this->renderPage($response, 'admin/pages/seats', 'Seats - CineShop Admin', 'seats');
    }

    public function showShopOrders(Request $request, Response $response): void
    {
        $activePage = $this->resolveActivePage($request, 'shop-orders', ['shop-orders', 'order-details']);
        $titles = [
            'shop-orders' => 'Shop Orders - CineShop Admin',
            'order-details' => 'Order Details - CineShop Admin',
        ];

        $this->renderPage($response, 'admin/pages/shop-orders/index', $titles[$activePage] ?? $titles['shop-orders'], $activePage);
    }

    public function showShowtimes(Request $request, Response $response): void
    {
        $this->renderPage($response, 'admin/pages/showtimes', 'Showtimes - CineShop Admin', 'showtimes');
    }

    public function showTicketOrders(Request $request, Response $response): void
    {
        $activePage = $this->resolveActivePage($request, 'ticket-orders', ['ticket-orders', 'ticket-details']);
        $titles = [
            'ticket-orders' => 'Ticket Orders - CineShop Admin',
            'ticket-details' => 'Ticket Details - CineShop Admin',
        ];

        $this->renderPage($response, 'admin/pages/ticket-orders/index', $titles[$activePage] ?? $titles['ticket-orders'], $activePage);
    }

    public function showUsers(Request $request, Response $response): void
    {
        $activePage = $this->resolveActivePage($request, 'users', ['users', 'user-addresses', 'roles']);
        $titles = [
            'users' => 'Users - CineShop Admin',
            'user-addresses' => 'User Addresses - CineShop Admin',
            'roles' => 'Roles - CineShop Admin',
        ];

        $this->renderPage($response, 'admin/pages/users/index', $titles[$activePage] ?? $titles['users'], $activePage);
    }

    public function showTest(Request $request, Response $response): void
    {
        $this->renderPage($response, 'admin/pages/test', 'Admin Test - CineShop Admin', 'test');
    }
}
