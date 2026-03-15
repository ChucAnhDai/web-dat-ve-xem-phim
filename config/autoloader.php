<?php

date_default_timezone_set('Asia/Saigon');

/**
 * Load local environment-style settings without hardcoding secrets in source files.
 * The file returns a flat array like ['APP_URL' => 'http://localhost/app'].
 */
$localConfigFile = __DIR__ . '/local.php';
if (file_exists($localConfigFile)) {
    $localSettings = require $localConfigFile;
    if (is_array($localSettings)) {
        foreach ($localSettings as $key => $value) {
            if (!is_string($key) || trim($key) === '') {
                continue;
            }

            if (getenv($key) !== false && getenv($key) !== '') {
                continue;
            }

            $stringValue = is_scalar($value) ? (string) $value : '';
            putenv($key . '=' . $stringValue);
            $_ENV[$key] = $stringValue;
            $_SERVER[$key] = $stringValue;
        }
    }
}

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
