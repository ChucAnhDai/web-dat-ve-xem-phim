<?php

use App\Controllers\Auth\AuthController;
use App\Controllers\Admin\MovieManagementController;
use App\Controllers\Api\MovieCatalogController;
use App\Controllers\Api\ShowtimeCatalogController;
use App\Middlewares\AdminMiddleware;
use App\Middlewares\AuthMiddleware;

/** @var \App\Core\Application $app */

$app->router->post('/api/auth/register', [AuthController::class, 'register']);
$app->router->post('/api/auth/login', [AuthController::class, 'login']);
$app->router->get('/api/auth/profile', [AuthController::class, 'profile'], [AuthMiddleware::class]);
$app->router->post('/api/auth/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);
$app->router->post('/api/auth/update-password', [AuthController::class, 'updatePassword'], [AuthMiddleware::class]);

$app->router->get('/api/movies', [MovieCatalogController::class, 'listMovies']);
$app->router->get('/api/movies/{slug}', [MovieCatalogController::class, 'getMovieDetail']);
$app->router->get('/api/showtimes/{id}/seat-map', [ShowtimeCatalogController::class, 'getSeatMap']);

$app->router->post('/api/admin/auth/login', [AuthController::class, 'adminLogin']);
$app->router->post('/api/admin/auth/logout', [AuthController::class, 'adminLogout'], [AdminMiddleware::class]);
$app->router->get('/api/admin/auth/profile', [AuthController::class, 'profile'], [AdminMiddleware::class]);

$app->router->get('/api/admin/movies', [MovieManagementController::class, 'listMovies'], [AdminMiddleware::class]);
$app->router->post('/api/admin/movies/import-ophim', [MovieManagementController::class, 'importMovieFromOphim'], [AdminMiddleware::class]);
$app->router->post('/api/admin/movies/import-ophim-list', [MovieManagementController::class, 'importMovieListFromOphim'], [AdminMiddleware::class]);
$app->router->get('/api/admin/movies/{id}', [MovieManagementController::class, 'getMovie'], [AdminMiddleware::class]);
$app->router->post('/api/admin/movies', [MovieManagementController::class, 'createMovie'], [AdminMiddleware::class]);
$app->router->put('/api/admin/movies/{id}', [MovieManagementController::class, 'updateMovie'], [AdminMiddleware::class]);
$app->router->delete('/api/admin/movies/{id}', [MovieManagementController::class, 'archiveMovie'], [AdminMiddleware::class]);

$app->router->get('/api/admin/movie-categories', [MovieManagementController::class, 'listCategories'], [AdminMiddleware::class]);
$app->router->get('/api/admin/movie-categories/{id}', [MovieManagementController::class, 'getCategory'], [AdminMiddleware::class]);
$app->router->post('/api/admin/movie-categories', [MovieManagementController::class, 'createCategory'], [AdminMiddleware::class]);
$app->router->put('/api/admin/movie-categories/{id}', [MovieManagementController::class, 'updateCategory'], [AdminMiddleware::class]);
$app->router->delete('/api/admin/movie-categories/{id}', [MovieManagementController::class, 'deactivateCategory'], [AdminMiddleware::class]);

$app->router->get('/api/admin/movie-assets', [MovieManagementController::class, 'listAssets'], [AdminMiddleware::class]);
$app->router->get('/api/admin/movie-assets/{id}', [MovieManagementController::class, 'getAsset'], [AdminMiddleware::class]);
$app->router->post('/api/admin/movie-assets', [MovieManagementController::class, 'createAsset'], [AdminMiddleware::class]);
$app->router->put('/api/admin/movie-assets/{id}', [MovieManagementController::class, 'updateAsset'], [AdminMiddleware::class]);
$app->router->delete('/api/admin/movie-assets/{id}', [MovieManagementController::class, 'archiveAsset'], [AdminMiddleware::class]);

$app->router->get('/api/admin/movie-reviews', [MovieManagementController::class, 'listReviews'], [AdminMiddleware::class]);
$app->router->get('/api/admin/movie-reviews/{id}', [MovieManagementController::class, 'getReview'], [AdminMiddleware::class]);
$app->router->put('/api/admin/movie-reviews/{id}/moderate', [MovieManagementController::class, 'moderateReview'], [AdminMiddleware::class]);
