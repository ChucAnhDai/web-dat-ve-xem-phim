<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/autoloader.php';

use App\Services\DefaultAdminProvisioningService;

try {
    $result = (new DefaultAdminProvisioningService())->provision([
        'email' => 'admin',
        'password' => 'admin',
        'name' => 'System Admin',
        'phone' => '0000000000',
    ]);

    $action = $result['created'] ? 'created' : 'updated';
    echo sprintf(
        "Default admin %s successfully. Identifier: %s | User ID: %d%s",
        $action,
        $result['email'],
        $result['id'],
        PHP_EOL
    );
} catch (Throwable $exception) {
    fwrite(STDERR, 'Failed to provision default admin: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
