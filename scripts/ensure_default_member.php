<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/autoloader.php';

use App\Services\DefaultMemberProvisioningService;

$email = trim((string) ($argv[1] ?? 'member@example.com'));
$password = (string) ($argv[2] ?? 'member123');
$name = trim((string) ($argv[3] ?? 'Local Member'));
$phone = trim((string) ($argv[4] ?? '0900000001'));

try {
    $result = (new DefaultMemberProvisioningService())->provision([
        'email' => $email,
        'password' => $password,
        'name' => $name,
        'phone' => $phone,
    ]);

    $action = $result['created'] ? 'created' : 'updated';
    echo sprintf(
        "Default member %s successfully. Identifier: %s | User ID: %d%s",
        $action,
        $result['email'],
        $result['id'],
        PHP_EOL
    );
} catch (Throwable $exception) {
    fwrite(STDERR, 'Failed to provision default member: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
