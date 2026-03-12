<?php

use App\Controllers\Auth\AuthController;
use App\Middlewares\AuthMiddleware;

/** @var \App\Core\Application $app */

$app->router->post('/api/auth/register', [AuthController::class, 'register']);
$app->router->post('/api/auth/login', [AuthController::class, 'login']);
$app->router->get('/api/auth/profile', [AuthController::class, 'profile'], [AuthMiddleware::class]);
