<?php

use App\Controllers\Auth\AuthController;
use App\Controllers\Admin\CinemaManagementController;
use App\Controllers\Admin\MovieManagementController;
use App\Controllers\Admin\ProductManagementController;
use App\Controllers\Admin\ShopOrderManagementController;
use App\Controllers\Admin\ShowtimeManagementController;
use App\Controllers\Admin\TicketManagementController;
use App\Controllers\Api\MovieCatalogController;
use App\Controllers\Api\PaymentController;
use App\Controllers\Api\ShopCatalogController;
use App\Controllers\Api\ShopCartController;
use App\Controllers\Api\ShopCheckoutController;
use App\Controllers\Api\ShowtimeCatalogController;
use App\Controllers\Api\TicketHoldController;
use App\Controllers\Api\TicketOrderController;
use App\Controllers\Api\UserShopOrderController;
use App\Controllers\Api\UserTicketController;
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
$app->router->get('/api/shop/categories', [ShopCatalogController::class, 'listCategories']);
$app->router->get('/api/shop/cart', [ShopCartController::class, 'getCart']);
$app->router->post('/api/shop/cart/items', [ShopCartController::class, 'addItem']);
$app->router->put('/api/shop/cart/items/{productId}', [ShopCartController::class, 'updateItem']);
$app->router->delete('/api/shop/cart/items/{productId}', [ShopCartController::class, 'removeItem']);
$app->router->delete('/api/shop/cart', [ShopCartController::class, 'clearCart']);
$app->router->get('/api/shop/checkout', [ShopCheckoutController::class, 'getCheckout']);
$app->router->post('/api/shop/checkout', [ShopCheckoutController::class, 'createCheckout']);
$app->router->get('/api/shop/products', [ShopCatalogController::class, 'listProducts']);
$app->router->get('/api/shop/products/{slug}', [ShopCatalogController::class, 'getProductDetail']);
$app->router->get('/api/showtimes', [ShowtimeCatalogController::class, 'listShowtimes']);
$app->router->get('/api/showtimes/{id}/seat-map', [ShowtimeCatalogController::class, 'getSeatMap']);
$app->router->post('/api/tickets/holds', [TicketHoldController::class, 'createHold']);
$app->router->delete('/api/tickets/holds/{showtimeId}', [TicketHoldController::class, 'releaseHold']);
$app->router->post('/api/ticket-orders/preview', [TicketOrderController::class, 'previewOrder']);
$app->router->post('/api/ticket-orders', [TicketOrderController::class, 'createOrder']);
$app->router->get('/api/ticket-orders/active-checkout', [TicketOrderController::class, 'activeCheckout']);
$app->router->post('/api/payments/ticket-intents', [PaymentController::class, 'createTicketVnpayIntent']);
$app->router->get('/api/payments/vnpay/return', [PaymentController::class, 'handleVnpayReturn']);
$app->router->get('/api/payments/vnpay/ipn', [PaymentController::class, 'handleVnpayIpn']);
$app->router->get('/api/me/tickets', [UserTicketController::class, 'listMyTickets'], [AuthMiddleware::class]);
$app->router->get('/api/me/ticket-orders', [UserTicketController::class, 'listMyOrders'], [AuthMiddleware::class]);
$app->router->get('/api/me/shop-orders', [UserShopOrderController::class, 'listMyOrders'], [AuthMiddleware::class]);
$app->router->get('/api/me/shop-orders/{id}', [UserShopOrderController::class, 'getMyOrder'], [AuthMiddleware::class]);
$app->router->post('/api/me/shop-orders/{id}/cancel', [UserShopOrderController::class, 'cancelMyOrder'], [AuthMiddleware::class]);
$app->router->get('/api/shop/orders/session', [UserShopOrderController::class, 'listSessionOrders']);
$app->router->get('/api/shop/orders/session/{id}', [UserShopOrderController::class, 'getSessionOrder']);
$app->router->post('/api/shop/orders/session/{id}/cancel', [UserShopOrderController::class, 'cancelSessionOrder']);
$app->router->post('/api/shop/orders/lookup', [UserShopOrderController::class, 'lookupGuestOrder']);
$app->router->post('/api/shop/orders/lookup/cancel', [UserShopOrderController::class, 'cancelGuestOrder']);


// admin

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

$app->router->get('/api/admin/product-categories', [ProductManagementController::class, 'listCategories'], [AdminMiddleware::class]);
$app->router->get('/api/admin/product-categories/{id}', [ProductManagementController::class, 'getCategory'], [AdminMiddleware::class]);
$app->router->post('/api/admin/product-categories', [ProductManagementController::class, 'createCategory'], [AdminMiddleware::class]);
$app->router->put('/api/admin/product-categories/{id}', [ProductManagementController::class, 'updateCategory'], [AdminMiddleware::class]);
$app->router->delete('/api/admin/product-categories/{id}', [ProductManagementController::class, 'archiveCategory'], [AdminMiddleware::class]);
$app->router->get('/api/admin/products', [ProductManagementController::class, 'listProducts'], [AdminMiddleware::class]);
$app->router->get('/api/admin/products/{id}', [ProductManagementController::class, 'getProduct'], [AdminMiddleware::class]);
$app->router->post('/api/admin/products', [ProductManagementController::class, 'createProduct'], [AdminMiddleware::class]);
$app->router->put('/api/admin/products/{id}', [ProductManagementController::class, 'updateProduct'], [AdminMiddleware::class]);
$app->router->delete('/api/admin/products/{id}', [ProductManagementController::class, 'archiveProduct'], [AdminMiddleware::class]);
$app->router->get('/api/admin/product-images', [ProductManagementController::class, 'listImages'], [AdminMiddleware::class]);
$app->router->post('/api/admin/product-images/bulk', [ProductManagementController::class, 'createImagesBatch'], [AdminMiddleware::class]);
$app->router->get('/api/admin/product-images/{id}', [ProductManagementController::class, 'getImage'], [AdminMiddleware::class]);
$app->router->post('/api/admin/product-images', [ProductManagementController::class, 'createImage'], [AdminMiddleware::class]);
$app->router->put('/api/admin/product-images/{id}', [ProductManagementController::class, 'updateImage'], [AdminMiddleware::class]);
$app->router->delete('/api/admin/product-images/{id}', [ProductManagementController::class, 'archiveImage'], [AdminMiddleware::class]);

$app->router->get('/api/admin/shop-orders', [ShopOrderManagementController::class, 'listShopOrders'], [AdminMiddleware::class]);
$app->router->get('/api/admin/shop-orders/{id}', [ShopOrderManagementController::class, 'getShopOrder'], [AdminMiddleware::class]);
$app->router->put('/api/admin/shop-orders/{id}/status', [ShopOrderManagementController::class, 'updateShopOrderStatus'], [AdminMiddleware::class]);
$app->router->get('/api/admin/order-details', [ShopOrderManagementController::class, 'listOrderDetails'], [AdminMiddleware::class]);

$app->router->get('/api/admin/cinemas', [CinemaManagementController::class, 'listCinemas'], [AdminMiddleware::class]);
$app->router->get('/api/admin/cinemas/{id}', [CinemaManagementController::class, 'getCinema'], [AdminMiddleware::class]);
$app->router->post('/api/admin/cinemas', [CinemaManagementController::class, 'createCinema'], [AdminMiddleware::class]);
$app->router->put('/api/admin/cinemas/{id}', [CinemaManagementController::class, 'updateCinema'], [AdminMiddleware::class]);
$app->router->delete('/api/admin/cinemas/{id}', [CinemaManagementController::class, 'archiveCinema'], [AdminMiddleware::class]);

$app->router->get('/api/admin/rooms', [CinemaManagementController::class, 'listRooms'], [AdminMiddleware::class]);
$app->router->get('/api/admin/rooms/{id}', [CinemaManagementController::class, 'getRoom'], [AdminMiddleware::class]);
$app->router->get('/api/admin/rooms/{id}/seats', [CinemaManagementController::class, 'getRoomSeats'], [AdminMiddleware::class]);
$app->router->post('/api/admin/rooms', [CinemaManagementController::class, 'createRoom'], [AdminMiddleware::class]);
$app->router->put('/api/admin/rooms/{id}', [CinemaManagementController::class, 'updateRoom'], [AdminMiddleware::class]);
$app->router->put('/api/admin/rooms/{id}/seats', [CinemaManagementController::class, 'replaceRoomSeats'], [AdminMiddleware::class]);
$app->router->delete('/api/admin/rooms/{id}', [CinemaManagementController::class, 'archiveRoom'], [AdminMiddleware::class]);

$app->router->get('/api/admin/showtimes', [ShowtimeManagementController::class, 'listShowtimes'], [AdminMiddleware::class]);
$app->router->get('/api/admin/showtimes/{id}', [ShowtimeManagementController::class, 'getShowtime'], [AdminMiddleware::class]);
$app->router->post('/api/admin/showtimes', [ShowtimeManagementController::class, 'createShowtime'], [AdminMiddleware::class]);
$app->router->put('/api/admin/showtimes/{id}', [ShowtimeManagementController::class, 'updateShowtime'], [AdminMiddleware::class]);
$app->router->delete('/api/admin/showtimes/{id}', [ShowtimeManagementController::class, 'archiveShowtime'], [AdminMiddleware::class]);
$app->router->get('/api/admin/ticket-orders', [TicketManagementController::class, 'listTicketOrders'], [AdminMiddleware::class]);
$app->router->get('/api/admin/ticket-orders/{id}', [TicketManagementController::class, 'getTicketOrder'], [AdminMiddleware::class]);
$app->router->get('/api/admin/ticket-details', [TicketManagementController::class, 'listTicketDetails'], [AdminMiddleware::class]);
$app->router->get('/api/admin/ticket-details/{id}', [TicketManagementController::class, 'getTicketDetail'], [AdminMiddleware::class]);
$app->router->get('/api/admin/ticket-holds', [TicketManagementController::class, 'listActiveHolds'], [AdminMiddleware::class]);
