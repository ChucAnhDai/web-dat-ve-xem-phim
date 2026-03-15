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
use App\Controllers\Admin\AdminAuthController;
use App\Middlewares\AdminPageMiddleware;

$app = new Application();

// Load API routes
require_once __DIR__ . '/../config/routes.php';

// Web routes
$app->router->get('/', [WebController::class, 'showHomePage']);
$app->router->get('/home', [WebController::class, 'showHomePage']);
$app->router->get('/movies', [WebController::class, 'showMoviesPage']);
$app->router->get('/movie-detail', [WebController::class, 'showMovieDetailPage']);
$app->router->get('/showtimes', [WebController::class, 'showShowtimesPage']);
$app->router->get('/shop', [WebController::class, 'showShopPage']);
$app->router->get('/shop/product-detail', [WebController::class, 'showProductDetailPage']);
$app->router->get('/cart', [WebController::class, 'showCartPage']);
$app->router->get('/profile', [WebController::class, 'showProfilePage']);
$app->router->get('/my-orders', [WebController::class, 'showMyOrdersPage']);
$app->router->get('/my-tickets', [WebController::class, 'showMyTicketsPage']);
$app->router->get('/login', [WebController::class, 'showLoginForm']);
$app->router->get('/register', [WebController::class, 'showRegisterForm']);
$app->router->get('/seat-selection', [WebController::class, 'showSeatSelectionPage']);
$app->router->get('/checkout', [WebController::class, 'showCheckoutPage']);
$app->router->get('/payment-result', [WebController::class, 'showPaymentResultPage']);

// Admin routes
$app->router->get('/admin/login', [AdminAuthController::class, 'showLogin']);
$app->router->post('/admin/login', [AdminAuthController::class, 'login']);
$app->router->get('/admin/logout', [AdminAuthController::class, 'logout']);
$app->router->get('/admin', [AdminController::class, 'showDashboard'], [AdminPageMiddleware::class]);
$app->router->get('/admin/dashboard', [AdminController::class, 'showDashboard'], [AdminPageMiddleware::class]);
$app->router->get('/admin/movies', [AdminController::class, 'showMovies'], [AdminPageMiddleware::class]);
$app->router->get('/admin/cinemas', [AdminController::class, 'showCinemas'], [AdminPageMiddleware::class]);
$app->router->get('/admin/payments', [AdminController::class, 'showPayments'], [AdminPageMiddleware::class]);
$app->router->get('/admin/products', [AdminController::class, 'showProducts'], [AdminPageMiddleware::class]);
$app->router->get('/admin/promotions', [AdminController::class, 'showPromotions'], [AdminPageMiddleware::class]);
$app->router->get('/admin/seats', [AdminController::class, 'showSeats'], [AdminPageMiddleware::class]);
$app->router->get('/admin/shop-orders', [AdminController::class, 'showShopOrders'], [AdminPageMiddleware::class]);
$app->router->get('/admin/showtimes', [AdminController::class, 'showShowtimes'], [AdminPageMiddleware::class]);
$app->router->get('/admin/ticket-orders', [AdminController::class, 'showTicketOrders'], [AdminPageMiddleware::class]);
$app->router->get('/admin/users', [AdminController::class, 'showUsers'], [AdminPageMiddleware::class]);
$app->router->get('/admin/test', [AdminController::class, 'showTest'], [AdminPageMiddleware::class]);

$app->run();
