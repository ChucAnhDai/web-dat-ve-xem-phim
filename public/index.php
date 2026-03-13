<?php

// Front Controller
// All requests are routed through this file.

require_once __DIR__ . '/../config/autoloader.php';
require_once __DIR__ . '/../app/Core/Router.php';
require_once __DIR__ . '/../app/Core/Request.php';
require_once __DIR__ . '/../app/Core/Response.php';

use App\Core\Application;
use App\Controllers\WebController;
use App\Controllers\Admin\AdminController;

$app = new Application();

// Load API routes
require_once __DIR__ . '/../config/routes.php';

// Web routes
$app->router->get('/', [WebController::class, 'showHomePage']);
$app->router->get('/home', [WebController::class, 'showHomePage']);
$app->router->get('/movies', [WebController::class, 'showMoviesPage']);
$app->router->get('/showtimes', [WebController::class, 'showShowtimesPage']);
$app->router->get('/shop', [WebController::class, 'showShopPage']);
$app->router->get('/shop/product-detail', [WebController::class, 'showProductDetailPage']);
$app->router->get('/cart', [WebController::class, 'showCartPage']);
$app->router->get('/profile', [WebController::class, 'showProfilePage']);
$app->router->get('/login', [WebController::class, 'showLoginForm']);
$app->router->get('/register', [WebController::class, 'showRegisterForm']);

// Admin routes
$app->router->get('/admin', [AdminController::class, 'showDashboard']);
$app->router->get('/admin/dashboard', [AdminController::class, 'showDashboard']);
$app->router->get('/admin/movies', [AdminController::class, 'showMovies']);
$app->router->get('/admin/cinemas', [AdminController::class, 'showCinemas']);
$app->router->get('/admin/payments', [AdminController::class, 'showPayments']);
$app->router->get('/admin/products', [AdminController::class, 'showProducts']);
$app->router->get('/admin/promotions', [AdminController::class, 'showPromotions']);
$app->router->get('/admin/seats', [AdminController::class, 'showSeats']);
$app->router->get('/admin/shop-orders', [AdminController::class, 'showShopOrders']);
$app->router->get('/admin/showtimes', [AdminController::class, 'showShowtimes']);
$app->router->get('/admin/ticket-orders', [AdminController::class, 'showTicketOrders']);
$app->router->get('/admin/users', [AdminController::class, 'showUsers']);
$app->router->get('/admin/test', [AdminController::class, 'showTest']);

$app->run();
