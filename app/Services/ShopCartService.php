<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\CartItemRepository;
use App\Repositories\CartRepository;
use App\Repositories\ProductRepository;
use App\Support\AssetUrlResolver;
use App\Validators\ShopCartValidator;
use PDO;
use Throwable;

class ShopCartService
{
    private const STOCK_NOT_ENOUGH_MESSAGE = 'Số lượng sản phẩm còn lại không đủ.';
    private const OUT_OF_STOCK_MESSAGE = 'Sản phẩm đã hết hàng.';

    private PDO $db;
    private CartRepository $carts;
    private CartItemRepository $items;
    private ProductRepository $products;
    private ShopCartValidator $validator;
    private Logger $logger;
    private AssetUrlResolver $assetUrlResolver;
    private array $config;

    public function __construct(
        ?PDO $db = null,
        ?CartRepository $carts = null,
        ?CartItemRepository $items = null,
        ?ProductRepository $products = null,
        ?ShopCartValidator $validator = null,
        ?Logger $logger = null,
        ?array $config = null,
        ?AssetUrlResolver $assetUrlResolver = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->config = $config ?? require dirname(__DIR__, 2) . '/config/shop.php';
        $this->carts = $carts ?? new CartRepository($this->db);
        $this->items = $items ?? new CartItemRepository($this->db);
        $this->products = $products ?? new ProductRepository($this->db);
        $this->validator = $validator ?? new ShopCartValidator($this->config);
        $this->logger = $logger ?? new Logger();
        $this->assetUrlResolver = $assetUrlResolver ?? new AssetUrlResolver((string) (getenv('APP_URL') ?: ''));
    }

    public function cartCookieName(): string
    {
        return $this->validator->cartCookieName();
    }

    public function getCart(?int $userId = null, ?string $sessionToken = null): array
    {
        $startedAt = microtime(true);

        try {
            $context = $this->resolveCartContext($userId, $sessionToken);
            $payload = $this->buildCartPayload($context['cart'], $userId, $context['sync']);
        } catch (Throwable $exception) {
            $this->logger->error('Shop cart load failed', [
                'user_id' => $userId,
                'session_token' => $this->sessionTokenPreview($sessionToken),
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to load cart.']], 500);
        }

        return $this->success($payload, 200, $context['session_token']);
    }

    public function addItem(array $payload, ?int $userId = null, ?string $sessionToken = null): array
    {
        $startedAt = microtime(true);
        $validation = $this->validator->validateAddItemPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $data = $validation['data'];

        try {
            $context = $this->resolveCartContext($userId, $sessionToken);
            $product = $this->requirePurchasableProduct((int) $data['product_id']);

            $context['cart'] = $this->transactional(function () use ($context, $userId, $data, $product): array {
                $cart = $context['cart'] ?? $this->createCart($userId, $context['session_token']);
                $existingItems = $this->items->listByCartId((int) $cart['id']);
                $existingItem = $this->findCartItemByProductId($existingItems, (int) $data['product_id']);

                if ($existingItem === null && count($existingItems) >= $this->validator->maxCartItems()) {
                    throw new ShopCartDomainException([
                        'cart' => ['Cart item limit has been reached.'],
                    ], 409);
                }

                $targetQuantity = (int) $data['quantity'];
                if ($existingItem !== null) {
                    $targetQuantity += (int) ($existingItem['quantity'] ?? 0);
                }

                $validatedQuantity = $this->validateRequestedQuantity($product, $targetQuantity);
                $price = round((float) ($product['price'] ?? 0), 2);

                if ($existingItem !== null) {
                    $this->items->updateQuantityAndPrice((int) $existingItem['id'], $validatedQuantity, $price);
                } else {
                    $this->items->create([
                        'cart_id' => (int) $cart['id'],
                        'product_id' => (int) $data['product_id'],
                        'quantity' => $validatedQuantity,
                        'price' => $price,
                    ]);
                }

                $this->touchCart((int) $cart['id']);

                return $this->reloadCart((int) $cart['id'], $userId);
            });

            $payload = $this->buildCartPayload($context['cart'], $userId, $context['sync']);
        } catch (ShopCartDomainException $exception) {
            $this->logBusinessRuleBlock('Shop cart add item blocked', [
                'user_id' => $userId,
                'session_token' => $this->sessionTokenPreview($sessionToken),
                'product_id' => $data['product_id'] ?? null,
                'quantity' => $data['quantity'] ?? null,
            ], $exception, $startedAt);

            return $this->error($exception->errors(), $exception->status());
        } catch (Throwable $exception) {
            $this->logger->error('Shop cart add item failed', [
                'user_id' => $userId,
                'session_token' => $this->sessionTokenPreview($sessionToken),
                'product_id' => $data['product_id'] ?? null,
                'quantity' => $data['quantity'] ?? null,
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to add item to cart.']], 500);
        }

        $this->logger->info('Shop cart item added', [
            'user_id' => $userId,
            'session_token' => $this->sessionTokenPreview($context['session_token']),
            'cart_id' => $context['cart']['id'] ?? null,
            'product_id' => $data['product_id'],
            'quantity' => $data['quantity'],
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success($payload, 201, $context['session_token']);
    }

    public function updateItemQuantity(int $productId, array $payload, ?int $userId = null, ?string $sessionToken = null): array
    {
        $startedAt = microtime(true);
        $normalizedProductId = $this->validator->normalizeProductId($productId);
        if ($normalizedProductId === null) {
            return $this->error(['product_id' => ['Product ID must be a positive integer.']], 422);
        }

        $validation = $this->validator->validateUpdateItemPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $data = $validation['data'];

        try {
            $context = $this->resolveCartContext($userId, $sessionToken);
            if ($context['cart'] === null) {
                return $this->error(['cart' => ['Cart item not found.']], 404);
            }

            $product = $this->requirePurchasableProduct($normalizedProductId);

            $context['cart'] = $this->transactional(function () use ($context, $userId, $normalizedProductId, $data, $product): array {
                $cartId = (int) ($context['cart']['id'] ?? 0);
                $existingItem = $this->items->findByCartAndProduct($cartId, $normalizedProductId);
                if ($existingItem === null) {
                    throw new ShopCartDomainException([
                        'cart' => ['Cart item not found.'],
                    ], 404);
                }

                $validatedQuantity = $this->validateRequestedQuantity($product, (int) $data['quantity']);
                $this->items->updateQuantityAndPrice(
                    (int) $existingItem['id'],
                    $validatedQuantity,
                    round((float) ($product['price'] ?? 0), 2)
                );
                $this->touchCart($cartId);

                return $this->reloadCart($cartId, $userId);
            });

            $payload = $this->buildCartPayload($context['cart'], $userId, $context['sync']);
        } catch (ShopCartDomainException $exception) {
            $this->logBusinessRuleBlock('Shop cart update item blocked', [
                'user_id' => $userId,
                'session_token' => $this->sessionTokenPreview($sessionToken),
                'product_id' => $normalizedProductId,
                'quantity' => $data['quantity'] ?? null,
            ], $exception, $startedAt);

            return $this->error($exception->errors(), $exception->status());
        } catch (Throwable $exception) {
            $this->logger->error('Shop cart update item failed', [
                'user_id' => $userId,
                'session_token' => $this->sessionTokenPreview($sessionToken),
                'product_id' => $normalizedProductId,
                'quantity' => $data['quantity'] ?? null,
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to update cart item.']], 500);
        }

        $this->logger->info('Shop cart item updated', [
            'user_id' => $userId,
            'session_token' => $this->sessionTokenPreview($context['session_token']),
            'cart_id' => $context['cart']['id'] ?? null,
            'product_id' => $normalizedProductId,
            'quantity' => $data['quantity'],
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success($payload, 200, $context['session_token']);
    }

    public function removeItem(int $productId, ?int $userId = null, ?string $sessionToken = null): array
    {
        $startedAt = microtime(true);
        $normalizedProductId = $this->validator->normalizeProductId($productId);
        if ($normalizedProductId === null) {
            return $this->error(['product_id' => ['Product ID must be a positive integer.']], 422);
        }

        try {
            $context = $this->resolveCartContext($userId, $sessionToken);
            if ($context['cart'] === null) {
                return $this->error(['cart' => ['Cart item not found.']], 404);
            }

            $context['cart'] = $this->transactional(function () use ($context, $userId, $normalizedProductId): array {
                $cartId = (int) ($context['cart']['id'] ?? 0);
                $existingItem = $this->items->findByCartAndProduct($cartId, $normalizedProductId);
                if ($existingItem === null) {
                    throw new ShopCartDomainException([
                        'cart' => ['Cart item not found.'],
                    ], 404);
                }

                $this->items->deleteByCartAndProduct($cartId, $normalizedProductId);
                $this->touchCart($cartId);

                return $this->reloadCart($cartId, $userId);
            });

            $payload = $this->buildCartPayload($context['cart'], $userId, $context['sync']);
        } catch (ShopCartDomainException $exception) {
            $this->logBusinessRuleBlock('Shop cart remove item blocked', [
                'user_id' => $userId,
                'session_token' => $this->sessionTokenPreview($sessionToken),
                'product_id' => $normalizedProductId,
            ], $exception, $startedAt);

            return $this->error($exception->errors(), $exception->status());
        } catch (Throwable $exception) {
            $this->logger->error('Shop cart remove item failed', [
                'user_id' => $userId,
                'session_token' => $this->sessionTokenPreview($sessionToken),
                'product_id' => $normalizedProductId,
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to remove cart item.']], 500);
        }

        $this->logger->info('Shop cart item removed', [
            'user_id' => $userId,
            'session_token' => $this->sessionTokenPreview($context['session_token']),
            'cart_id' => $context['cart']['id'] ?? null,
            'product_id' => $normalizedProductId,
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success($payload, 200, $context['session_token']);
    }

    public function clearCart(?int $userId = null, ?string $sessionToken = null): array
    {
        $startedAt = microtime(true);

        try {
            $context = $this->resolveCartContext($userId, $sessionToken);
            if ($context['cart'] !== null) {
                $context['cart'] = $this->transactional(function () use ($context, $userId): array {
                    $cartId = (int) ($context['cart']['id'] ?? 0);
                    $this->items->deleteByCartId($cartId);
                    $this->touchCart($cartId);

                    return $this->reloadCart($cartId, $userId);
                });
            }

            $payload = $this->buildCartPayload($context['cart'], $userId, $context['sync']);
        } catch (Throwable $exception) {
            $this->logger->error('Shop cart clear failed', [
                'user_id' => $userId,
                'session_token' => $this->sessionTokenPreview($sessionToken),
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $this->error(['server' => ['Failed to clear cart.']], 500);
        }

        $this->logger->info('Shop cart cleared', [
            'user_id' => $userId,
            'session_token' => $this->sessionTokenPreview($context['session_token']),
            'cart_id' => $context['cart']['id'] ?? null,
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success($payload, 200, $context['session_token']);
    }

    private function resolveCartContext(?int $userId, ?string $sessionToken): array
    {
        $resolvedSessionToken = $this->normalizeSessionToken($sessionToken);
        if ($resolvedSessionToken === null) {
            $resolvedSessionToken = $this->generateSessionToken();
        }

        $guestCart = $this->carts->findActiveGuestBySessionToken($resolvedSessionToken);
        if ($guestCart !== null && $this->isCartExpired($guestCart)) {
            $this->carts->updateStatus((int) $guestCart['id'], 'abandoned');
            $guestCart = null;
        }

        $userCart = null;
        if ($userId !== null) {
            $userCart = $this->carts->findActiveUserCartByUserId($userId);
            if ($userCart !== null && $this->isCartExpired($userCart)) {
                $this->carts->updateStatus((int) $userCart['id'], 'abandoned');
                $userCart = null;
            }
        }

        $sync = [
            'merged_guest_cart' => 0,
            'adjusted_items' => 0,
            'removed_items' => 0,
        ];

        if ($userId !== null && $guestCart !== null) {
            if ($userCart === null) {
                $this->transactional(function () use ($guestCart, $userId): void {
                    $this->carts->assignGuestCartToUser((int) $guestCart['id'], $userId);
                    $this->touchCart((int) $guestCart['id']);
                });
                $userCart = $this->reloadCart((int) $guestCart['id'], $userId);
                $guestCart = null;
                $sync['merged_guest_cart'] = 1;
            } elseif ((int) $userCart['id'] !== (int) $guestCart['id']) {
                $this->transactional(function () use ($guestCart, $userCart): void {
                    $this->mergeGuestCartIntoUserCart((int) $guestCart['id'], (int) $userCart['id']);
                    $this->carts->updateStatus((int) $guestCart['id'], 'abandoned');
                    $this->touchCart((int) $userCart['id']);
                });
                $userCart = $this->reloadCart((int) $userCart['id'], $userId);
                $guestCart = null;
                $sync['merged_guest_cart'] = 1;
            }
        }

        $activeCart = $userId !== null ? $userCart : $guestCart;
        if ($activeCart !== null) {
            $reconciled = $this->reconcileCart($activeCart, $userId);
            $activeCart = $reconciled['cart'];
            $sync['adjusted_items'] += $reconciled['sync']['adjusted_items'];
            $sync['removed_items'] += $reconciled['sync']['removed_items'];
        }

        return [
            'cart' => $activeCart,
            'session_token' => $resolvedSessionToken,
            'sync' => $sync,
        ];
    }

    private function createCart(?int $userId, string $sessionToken): array
    {
        $cartId = $this->carts->create([
            'user_id' => $userId,
            'session_token' => $userId === null ? $sessionToken : null,
            'currency' => (string) ($this->config['currency'] ?? 'VND'),
            'status' => 'active',
            'expires_at' => $this->cartExpiryValue(),
        ]);

        return $this->reloadCart($cartId, $userId);
    }

    private function mergeGuestCartIntoUserCart(int $guestCartId, int $userCartId): void
    {
        $userItems = $this->items->listByCartId($userCartId);
        $userItemsByProduct = [];
        foreach ($userItems as $item) {
            $userItemsByProduct[(int) ($item['product_id'] ?? 0)] = $item;
        }

        $guestItems = $this->items->listByCartId($guestCartId);
        $productsById = $this->mapProductsById($this->products->listPublicCartProductsByIds(
            array_map(static function (array $item): int {
                return (int) ($item['product_id'] ?? 0);
            }, $guestItems),
            $this->lowStockThreshold()
        ));

        $lineCount = count($userItems);
        foreach ($guestItems as $guestItem) {
            $productId = (int) ($guestItem['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $product = $productsById[$productId] ?? null;
            if ($product === null) {
                continue;
            }

            $allowedQuantity = $this->maxQuantityAvailable($product);
            if ($allowedQuantity <= 0) {
                continue;
            }

            $currentPrice = round((float) ($product['price'] ?? $guestItem['price'] ?? 0), 2);
            if (isset($userItemsByProduct[$productId])) {
                $existing = $userItemsByProduct[$productId];
                $mergedQuantity = min(
                    $allowedQuantity,
                    (int) ($existing['quantity'] ?? 0) + (int) ($guestItem['quantity'] ?? 0)
                );
                $this->items->updateQuantityAndPrice(
                    (int) $existing['id'],
                    $mergedQuantity,
                    $currentPrice
                );
                continue;
            }

            if ($lineCount >= $this->validator->maxCartItems()) {
                continue;
            }

            $this->items->create([
                'cart_id' => $userCartId,
                'product_id' => $productId,
                'quantity' => min($allowedQuantity, (int) ($guestItem['quantity'] ?? 1)),
                'price' => $currentPrice,
            ]);
            $lineCount += 1;
        }
    }

    private function reconcileCart(array $cart, ?int $userId): array
    {
        $sync = [
            'adjusted_items' => 0,
            'removed_items' => 0,
        ];
        $cartId = (int) ($cart['id'] ?? 0);
        if ($cartId <= 0) {
            return ['cart' => $cart, 'sync' => $sync];
        }

        $items = $this->items->listByCartId($cartId);
        if ($items === []) {
            $this->touchCart($cartId);

            return [
                'cart' => $this->reloadCart($cartId, $userId),
                'sync' => $sync,
            ];
        }

        $productsById = $this->mapProductsById($this->products->listPublicCartProductsByIds(
            array_map(static function (array $item): int {
                return (int) ($item['product_id'] ?? 0);
            }, $items),
            $this->lowStockThreshold()
        ));

        $mutations = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $product = $productsById[$productId] ?? null;

            if ($product === null) {
                $mutations[] = [
                    'type' => 'remove',
                    'product_id' => $productId,
                ];
                $sync['removed_items'] += 1;
                continue;
            }

            $trackInventory = (int) ($product['track_inventory'] ?? 1) === 1;
            $storedQuantity = (int) ($item['quantity'] ?? 0);
            $storedPrice = round((float) ($item['price'] ?? 0), 2);
            $currentPrice = round((float) ($product['price'] ?? 0), 2);
            $allowedQuantity = $this->maxQuantityAvailable($product);

            if ($trackInventory && $allowedQuantity <= 0) {
                $mutations[] = [
                    'type' => 'remove',
                    'product_id' => $productId,
                ];
                $sync['removed_items'] += 1;
                continue;
            }

            $targetQuantity = min($storedQuantity, $allowedQuantity);

            if ($targetQuantity <= 0) {
                $mutations[] = [
                    'type' => 'remove',
                    'product_id' => $productId,
                ];
                $sync['removed_items'] += 1;
                continue;
            }

            if ($targetQuantity !== $storedQuantity || abs($storedPrice - $currentPrice) > 0.001) {
                $mutations[] = [
                    'type' => 'update',
                    'id' => (int) ($item['id'] ?? 0),
                    'quantity' => $targetQuantity,
                    'price' => $currentPrice,
                ];
                $sync['adjusted_items'] += 1;
            }
        }

        if ($mutations !== []) {
            $this->transactional(function () use ($cartId, $mutations): void {
                foreach ($mutations as $mutation) {
                    if ($mutation['type'] === 'remove') {
                        $this->items->deleteByCartAndProduct($cartId, (int) $mutation['product_id']);
                        continue;
                    }

                    $this->items->updateQuantityAndPrice(
                        (int) $mutation['id'],
                        (int) $mutation['quantity'],
                        (float) $mutation['price']
                    );
                }
                $this->touchCart($cartId);
            });
        } else {
            $this->touchCart($cartId);
        }

        return [
            'cart' => $this->reloadCart($cartId, $userId),
            'sync' => $sync,
        ];
    }

    private function buildCartPayload(?array $cart, ?int $userId, array $sync): array
    {
        if ($cart === null) {
            return [
                'cart' => [
                    'id' => null,
                    'user_id' => $userId,
                    'currency' => (string) ($this->config['currency'] ?? 'VND'),
                    'status' => 'active',
                    'expires_at' => null,
                    'line_count' => 0,
                    'item_count' => 0,
                    'subtotal_price' => 0.0,
                    'discount_amount' => 0.0,
                    'fee_amount' => 0.0,
                    'total_price' => 0.0,
                    'is_empty' => true,
                    'items' => [],
                ],
                'sync' => $sync,
            ];
        }

        $items = $this->items->listByCartId((int) $cart['id']);
        $productsById = $this->mapProductsById($this->products->listPublicCartProductsByIds(
            array_map(static function (array $item): int {
                return (int) ($item['product_id'] ?? 0);
            }, $items),
            $this->lowStockThreshold()
        ));

        $mappedItems = [];
        $itemCount = 0;
        $subtotal = 0.0;

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $product = $productsById[$productId] ?? null;
            if ($product === null) {
                continue;
            }

            $quantity = (int) ($item['quantity'] ?? 0);
            $unitPrice = round((float) ($item['price'] ?? 0), 2);
            $lineTotal = round($unitPrice * $quantity, 2);
            $itemCount += $quantity;
            $subtotal += $lineTotal;

            $mappedItems[] = [
                'id' => (int) ($item['id'] ?? 0),
                'product_id' => $productId,
                'slug' => $product['slug'] ?? null,
                'sku' => $product['sku'] ?? null,
                'name' => $product['name'] ?? null,
                'summary' => $product['short_description'] ?? ($product['detail_description'] ?? $product['description'] ?? null),
                'category_name' => $product['category_name'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'currency' => $product['currency'] ?? ($this->config['currency'] ?? 'VND'),
                'stock' => (int) ($product['stock'] ?? 0),
                'stock_state' => $product['stock_state'] ?? 'in_stock',
                'track_inventory' => (int) ($product['track_inventory'] ?? 1),
                'max_quantity_available' => $this->maxQuantityAvailable($product),
                'primary_image_url' => $this->assetUrlResolver->resolve($product['primary_image_url'] ?? null),
                'primary_image_alt' => $product['primary_image_alt'] ?? ($product['name'] ?? 'Product'),
            ];
        }

        $subtotal = round($subtotal, 2);

        return [
            'cart' => [
                'id' => (int) ($cart['id'] ?? 0),
                'user_id' => isset($cart['user_id']) && $cart['user_id'] !== null ? (int) $cart['user_id'] : null,
                'currency' => $cart['currency'] ?? ($this->config['currency'] ?? 'VND'),
                'status' => $cart['status'] ?? 'active',
                'expires_at' => $cart['expires_at'] ?? null,
                'line_count' => count($mappedItems),
                'item_count' => $itemCount,
                'subtotal_price' => $subtotal,
                'discount_amount' => 0.0,
                'fee_amount' => 0.0,
                'total_price' => $subtotal,
                'is_empty' => $mappedItems === [],
                'items' => $mappedItems,
            ],
            'sync' => $sync,
        ];
    }

    private function requirePurchasableProduct(int $productId): array
    {
        $rows = $this->products->listPublicCartProductsByIds([$productId], $this->lowStockThreshold());
        $product = $rows[0] ?? null;
        if ($product === null) {
            throw new ShopCartDomainException([
                'product' => ['Product not found or unavailable.'],
            ], 404);
        }

        return $product;
    }

    private function validateRequestedQuantity(array $product, int $quantity): int
    {
        $trackInventory = (int) ($product['track_inventory'] ?? 1) === 1;
        $allowedQuantity = $this->maxQuantityAvailable($product);

        if ($trackInventory && $allowedQuantity <= 0) {
            throw new ShopCartDomainException([
                'stock' => [self::OUT_OF_STOCK_MESSAGE],
            ], 409);
        }

        if ($quantity > $allowedQuantity) {
            throw new ShopCartDomainException([
                'quantity' => [$trackInventory
                    ? self::STOCK_NOT_ENOUGH_MESSAGE
                    : 'Quantity exceeds the per-item cart limit.'],
            ], 409);
        }

        return max(1, $quantity);
    }

    private function transactional(callable $callback)
    {
        $startedTransaction = !$this->db->inTransaction();
        if ($startedTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $result = $callback();
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }

            return $result;
        } catch (Throwable $exception) {
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    private function reloadCart(int $cartId, ?int $userId): ?array
    {
        $cart = $this->carts->findById($cartId);
        if ($cart === null || ($cart['status'] ?? 'active') !== 'active') {
            return null;
        }

        if ($userId !== null && isset($cart['user_id']) && (int) $cart['user_id'] !== $userId) {
            return null;
        }

        return $cart;
    }

    private function touchCart(int $cartId): void
    {
        $this->carts->updateExpiry($cartId, $this->cartExpiryValue());
    }

    private function isCartExpired(array $cart): bool
    {
        $expiresAt = trim((string) ($cart['expires_at'] ?? ''));
        if ($expiresAt === '') {
            return false;
        }

        $timestamp = strtotime($expiresAt);

        return $timestamp !== false && $timestamp < time();
    }

    private function cartExpiryValue(): string
    {
        return date('Y-m-d H:i:s', time() + ($this->validator->cartTtlMinutes() * 60));
    }

    private function sessionCookieExpiresAt(): int
    {
        return time() + ($this->validator->cartTtlMinutes() * 60);
    }

    private function normalizeSessionToken(?string $value): ?string
    {
        $normalized = strtolower(trim((string) ($value ?? '')));
        if ($normalized === '') {
            return null;
        }

        return preg_match('/^[a-f0-9]{32,128}$/', $normalized) === 1 ? $normalized : null;
    }

    private function generateSessionToken(): string
    {
        return bin2hex(random_bytes($this->validator->cartSessionTokenBytes()));
    }

    private function sessionTokenPreview(?string $value): ?string
    {
        $normalized = $this->normalizeSessionToken($value);
        if ($normalized === null) {
            return null;
        }

        return substr($normalized, 0, 12);
    }

    private function mapProductsById(array $rows): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[(int) ($row['id'] ?? 0)] = $row;
        }

        return $mapped;
    }

    private function maxQuantityAvailable(array $product): int
    {
        if ((int) ($product['track_inventory'] ?? 1) === 1) {
            return max(0, (int) ($product['stock'] ?? 0));
        }

        return $this->validator->maxQuantityPerItem();
    }

    private function logBusinessRuleBlock(
        string $message,
        array $context,
        ShopCartDomainException $exception,
        float $startedAt
    ): void {
        $this->logger->info($message, array_merge($context, [
            'errors' => $exception->errors(),
            'status' => $exception->status(),
            'duration_ms' => $this->durationMs($startedAt),
        ]));
    }

    private function findCartItemByProductId(array $items, int $productId): ?array
    {
        foreach ($items as $item) {
            if ((int) ($item['product_id'] ?? 0) === $productId) {
                return $item;
            }
        }

        return null;
    }

    private function lowStockThreshold(): int
    {
        return max(1, (int) ($this->config['products']['low_stock_threshold'] ?? 10));
    }

    private function success(array $data, int $status = 200, ?string $sessionToken = null): array
    {
        return [
            'status' => $status,
            'data' => $data,
            'session_token' => $sessionToken,
            'session_cookie_expires_at' => $this->sessionCookieExpiresAt(),
        ];
    }

    private function error(array $errors, int $status): array
    {
        return [
            'status' => $status,
            'errors' => $errors,
        ];
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
