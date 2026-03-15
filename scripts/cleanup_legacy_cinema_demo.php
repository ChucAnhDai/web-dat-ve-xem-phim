<?php

require __DIR__ . '/../config/autoloader.php';

use App\Services\DemoDatasetMaintenanceService;

$service = new DemoDatasetMaintenanceService();

try {
    $summary = $service->cleanupLegacyCinemaFixtures();

    echo "Legacy cinema demo cleanup completed." . PHP_EOL;
    foreach ($summary as $key => $value) {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        echo '- ' . $key . ': ' . $value . PHP_EOL;
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Failed to clean legacy cinema demo data: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
