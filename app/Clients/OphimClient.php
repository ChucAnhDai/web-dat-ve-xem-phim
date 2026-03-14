<?php

namespace App\Clients;

use App\Core\Logger;
use RuntimeException;
use Throwable;

class OphimClient
{
    private const DEFAULT_BASE_URL = 'https://ophim1.com/v1/api';

    private string $baseUrl;
    private string $cacheDir;
    private int $cacheTtl;
    private Logger $logger;

    public function __construct(
        ?string $baseUrl = null,
        ?Logger $logger = null,
        ?string $cacheDir = null,
        int $cacheTtl = 300
    ) {
        $this->baseUrl = rtrim($baseUrl ?: (getenv('OPHIM_API_BASE') ?: self::DEFAULT_BASE_URL), '/');
        $this->logger = $logger ?? new Logger();
        $this->cacheDir = $cacheDir ?? __DIR__ . '/../../storage/cache/ophim';
        $this->cacheTtl = max(0, $cacheTtl);
    }

    public function listBySlug(string $slug, array $query = []): array
    {
        return $this->requestJson('/danh-sach/' . rawurlencode(trim($slug)), $query, 300);
    }

    public function searchMovies(string $keyword, array $query = []): array
    {
        $normalizedKeyword = trim($keyword);
        $normalizedQuery = array_merge(['keyword' => $normalizedKeyword], $query);

        return $this->requestJson('/tim-kiem', $normalizedQuery, 180);
    }

    public function getMovieDetail(string $slug): array
    {
        return $this->requestJson('/phim/' . rawurlencode(trim($slug)), [], 900);
    }

    public function getMovieImages(string $slug): array
    {
        return $this->requestJson('/phim/' . rawurlencode(trim($slug)) . '/images', [], 900);
    }

    private function requestJson(string $path, array $query, int $ttl): array
    {
        $url = $this->buildUrl($path, $query);
        $cacheFile = $this->cacheFile($url);

        $cached = $this->readCache($cacheFile, $ttl ?: $this->cacheTtl);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $body = $this->performHttpRequest($url);
            $payload = json_decode($body, true);

            if (!is_array($payload)) {
                throw new RuntimeException('Invalid JSON payload returned from OPhim.');
            }

            $this->writeCache($cacheFile, $payload);

            return $payload;
        } catch (Throwable $exception) {
            $stale = $this->readStaleCache($cacheFile);
            if ($stale !== null) {
                $this->logger->info('Using stale OPhim cache after request failure', [
                    'url' => $url,
                    'error' => $exception->getMessage(),
                ]);

                return $stale;
            }

            throw new RuntimeException(
                'Failed to communicate with OPhim API.',
                0,
                $exception
            );
        }
    }

    private function performHttpRequest(string $url): string
    {
        if (function_exists('curl_init')) {
            return $this->performCurlRequest($url);
        }

        return $this->performStreamRequest($url);
    }

    private function performCurlRequest(string $url): string
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: CinemaX/1.0',
            ],
        ]);

        $body = curl_exec($handle);
        $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $curlError = curl_error($handle);
        curl_close($handle);

        if ($body === false) {
            throw new RuntimeException($curlError !== '' ? $curlError : 'Unknown cURL request failure.');
        }

        if ($httpCode >= 400 || $httpCode < 200) {
            throw new RuntimeException(sprintf('OPhim responded with HTTP %d.', $httpCode));
        }

        return (string) $body;
    }

    private function performStreamRequest(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
                'header' => "Accept: application/json\r\nUser-Agent: CinemaX/1.0\r\n",
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new RuntimeException('Failed to fetch OPhim response via streams.');
        }

        $statusLine = $http_response_header[0] ?? '';
        if (preg_match('/\s(\d{3})\s/', $statusLine, $matches) === 1) {
            $httpCode = (int) $matches[1];
            if ($httpCode >= 400 || $httpCode < 200) {
                throw new RuntimeException(sprintf('OPhim responded with HTTP %d.', $httpCode));
            }
        }

        return $body;
    }

    private function buildUrl(string $path, array $query): string
    {
        $normalizedPath = '/' . ltrim($path, '/');
        $queryString = http_build_query(array_filter($query, static function ($value): bool {
            return $value !== null && $value !== '';
        }));

        return $this->baseUrl . $normalizedPath . ($queryString !== '' ? '?' . $queryString : '');
    }

    private function cacheFile(string $url): string
    {
        return rtrim($this->cacheDir, '/\\') . DIRECTORY_SEPARATOR . sha1($url) . '.json';
    }

    private function readCache(string $cacheFile, int $ttl): ?array
    {
        if ($ttl <= 0 || !is_file($cacheFile)) {
            return null;
        }

        $payload = $this->decodeCacheFile($cacheFile);
        if ($payload === null) {
            return null;
        }

        $cachedAt = (int) ($payload['cached_at'] ?? 0);
        if ($cachedAt <= 0 || ($cachedAt + $ttl) < time()) {
            return null;
        }

        return is_array($payload['payload'] ?? null) ? $payload['payload'] : null;
    }

    private function readStaleCache(string $cacheFile): ?array
    {
        if (!is_file($cacheFile)) {
            return null;
        }

        $payload = $this->decodeCacheFile($cacheFile);

        return is_array($payload['payload'] ?? null) ? $payload['payload'] : null;
    }

    private function writeCache(string $cacheFile, array $payload): void
    {
        $directory = dirname($cacheFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($cacheFile, json_encode([
            'cached_at' => time(),
            'payload' => $payload,
        ], JSON_UNESCAPED_UNICODE));
    }

    private function decodeCacheFile(string $cacheFile): ?array
    {
        $raw = @file_get_contents($cacheFile);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $payload = json_decode($raw, true);

        return is_array($payload) ? $payload : null;
    }
}
