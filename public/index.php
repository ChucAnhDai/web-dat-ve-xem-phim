<?php

// Front Controller
// All requests are routed through this file.

require_once __DIR__ . '/../config/autoloader.php';
require_once __DIR__ . '/../app/Core/Router.php';
require_once __DIR__ . '/../app/Core/Request.php';
require_once __DIR__ . '/../app/Core/Response.php';

use App\Core\Application;
use App\Controllers\WebController;

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

$app->run();
