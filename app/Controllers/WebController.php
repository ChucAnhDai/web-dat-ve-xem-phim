<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

class WebController
{
    public function showHomePage(Request $request, Response $response)
    {
        return $response->view('pages/home', [
            'title' => 'Trang chủ - CinemaX',
            'activePage' => 'home',
        ]);
    }

    public function showMoviesPage(Request $request, Response $response)
    {
        return $response->view('pages/movies', [
            'title' => 'Phim - CinemaX',
            'activePage' => 'movies',
        ]);
    }

    public function showShowtimesPage(Request $request, Response $response)
    {
        return $response->view('pages/showtimes', [
            'title' => 'Lịch chiếu - CinemaX',
            'activePage' => 'showtimes',
        ]);
    }

    public function showShopPage(Request $request, Response $response)
    {
        return $response->view('pages/shop', [
            'title' => 'Shop - CinemaX',
            'activePage' => 'shop',
        ]);
    }

    public function showProductDetailPage(Request $request, Response $response)
    {
        return $response->view('pages/product-detail', [
            'title' => 'Chi tiết sản phẩm - CinemaX',
            'activePage' => 'shop',
        ]);
    }

    public function showCartPage(Request $request, Response $response)
    {
        return $response->view('pages/cart', [
            'title' => 'Giỏ hàng - CinemaX',
            'activePage' => 'cart',
        ]);
    }

    public function showProfilePage(Request $request, Response $response)
    {
        return $response->view('pages/profile', [
            'title' => 'Hồ sơ - CinemaX',
            'activePage' => 'profile',
        ]);
    }

    public function showMyOrdersPage(Request $request, Response $response)
    {
        return $response->view('pages/my-orders', [
            'title' => 'Đơn hàng của tôi - CinemaX',
            'activePage' => 'my-orders',
        ]);
    }

    public function showMyTicketsPage(Request $request, Response $response)
    {
        return $response->view('pages/my-tickets', [
            'title' => 'Vé của tôi - CinemaX',
            'activePage' => 'my-tickets',
        ]);
    }

    public function showLoginForm(Request $request, Response $response)
    {
        return $response->view('auth/login', [
            'title' => 'Đăng nhập - CinemaX',
            'activePage' => 'login',
        ]);
    }

    public function showRegisterForm(Request $request, Response $response)
    {
        return $response->view('auth/register', [
            'title' => 'Đăng ký - CinemaX',
            'activePage' => 'register',
        ]);
    }
}
