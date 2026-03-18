<?php

return [
    'currency' => 'VND',
    'cart' => [
        'ttl_minutes' => 60 * 24 * 7,
        'max_items' => 50,
        'max_quantity_per_item' => 10,
        'cookie_name' => 'cinemax_cart',
        'session_token_bytes' => 32,
    ],
    'categories' => [
        'visibility' => ['featured', 'standard', 'hidden'],
        'statuses' => ['active', 'inactive', 'archived'],
    ],
    'products' => [
        'statuses' => ['draft', 'active', 'inactive', 'archived'],
        'visibility' => ['featured', 'standard', 'hidden'],
        'image_asset_types' => ['thumbnail', 'gallery', 'banner', 'lifestyle'],
        'editor_image_asset_types' => ['thumbnail', 'gallery'],
        'image_statuses' => ['draft', 'active', 'archived'],
        'low_stock_threshold' => 10,
        'max_gallery_items' => 12,
        'uploads' => [
            'public_directory' => 'public/uploads/products',
            'max_file_size_bytes' => 5 * 1024 * 1024,
            'allowed_mime_types' => [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
            ],
        ],
    ],
    'orders' => [
        'fulfillment_methods' => ['pickup', 'delivery'],
        'supported_payment_methods' => ['cash', 'vnpay'],
        'pickup_payment_methods' => ['cash', 'vnpay'],
        'delivery_payment_methods' => ['vnpay'],
        'statuses' => ['pending', 'confirmed', 'preparing', 'ready', 'shipping', 'completed', 'cancelled', 'expired', 'refunded'],
        'pending_payment_ttl_minutes' => 5,
        'default_shipping_amount' => 0.0,
    ],
    'promotions' => [
        'discount_types' => ['percent', 'fixed'],
        'statuses' => ['draft', 'scheduled', 'active', 'expired', 'archived'],
        'assignment_statuses' => ['active', 'archived'],
    ],
];
