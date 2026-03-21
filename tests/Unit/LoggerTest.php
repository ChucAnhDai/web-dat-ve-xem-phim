<?php

namespace Tests\Unit;

use App\Core\Logger;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    private array $cleanupTargets = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanupTargets as $target) {
            $this->deletePath($target);
        }

        $this->cleanupTargets = [];
    }

    public function testInfoCreatesMissingDirectoryAndWritesLogLine(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'logger-test-' . bin2hex(random_bytes(6));
        $logFile = $root . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'app.log';
        $this->cleanupTargets[] = $root;

        $logger = new Logger($logFile);
        $logger->info('VNPay redirect prepared', ['order_code' => 'SHP-TEST-001']);

        $this->assertFileExists($logFile);
        $contents = (string) file_get_contents($logFile);

        $this->assertStringContainsString('INFO: VNPay redirect prepared', $contents);
        $this->assertStringContainsString('"order_code":"SHP-TEST-001"', $contents);
    }

    private function deletePath(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $this->deletePath($path . DIRECTORY_SEPARATOR . $item);
        }

        @rmdir($path);
    }
}
