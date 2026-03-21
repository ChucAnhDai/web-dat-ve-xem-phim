<?php

namespace App\Core;

class Logger
{
    private string $logFile;
    private string $fallbackLogFile;

    public function __construct(?string $logFile = null)
    {
        $root = dirname(__DIR__, 2);
        $this->logFile = $logFile ?? $root . '/storage/logs/app.log';
        $this->fallbackLogFile = $root . '/tmp/logs/app.log';
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    private function write(string $level, string $message, array $context = []): void
    {
        $payload = $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $line = sprintf("[%s] %s: %s%s\n", date('c'), $level, $message, $payload);
        $target = $this->resolveWritableLogFile($this->logFile)
            ?? $this->resolveWritableLogFile($this->fallbackLogFile);

        if ($target === null) {
            return;
        }

        @error_log($line, 3, $target);
    }

    private function resolveWritableLogFile(string $path): ?string
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            return null;
        }

        return $path;
    }
}
