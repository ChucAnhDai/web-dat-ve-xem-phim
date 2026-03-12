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
$app->router->get('/login', [WebController::class, 'showLoginForm']);
$app->router->get('/register', [WebController::class, 'showRegisterForm']);

$app->run();