<?php

// Front Controller
// All requests are routed through this file.

require_once __DIR__ . '/../config/autoloader.php';
require_once __DIR__ . '/../app/Core/Router.php';
require_once __DIR__ . '/../app/Core/Request.php';
require_once __DIR__ . '/../app/Core/Response.php';

use App\Core\Application;

$app = new Application();

// Load routes
require_once __DIR__ . '/../config/routes.php';

$app->run();