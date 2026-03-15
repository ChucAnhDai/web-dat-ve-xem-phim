<?php

require __DIR__ . '/../config/autoloader.php';

use App\Core\Database;
use App\Services\DemoDatasetMaintenanceService;

$pdo = Database::getInstance();

try {
    $summary = (new DemoDatasetMaintenanceService($pdo))->cleanupDemoTicketFixtures();

    echo "Demo ticket cleanup completed." . PHP_EOL;
    foreach ($summary as $key => $value) {
        echo '- ' . $key . ': ' . $value . PHP_EOL;
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Failed to clean demo ticket fixtures: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
