<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/autoloader.php';

use App\Services\HomeService;

try {
    $service = new HomeService();
    $data = $service->getHomeData();
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
