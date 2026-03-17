<?php

namespace App\Support;

class AssetUrlResolver
{
    private string $appBasePath;
    private string $managedUploadPrefix;

    public function __construct(?string $appUrl = null, string $managedUploadPrefix = 'public/uploads/products')
    {
        $this->appBasePath = $this->normalizeBasePath((string) parse_url((string) ($appUrl ?? ''), PHP_URL_PATH));
        $this->managedUploadPrefix = trim(str_replace('\\', '/', $managedUploadPrefix), '/');
    }

    public function resolve(?string $value, ?string $fallbackAppBasePath = null): ?string
    {
        $normalized = trim((string) ($value ?? ''));
        if ($normalized === '') {
            return null;
        }

        if ($this->isAbsoluteUrl($normalized) || str_starts_with($normalized, 'data:')) {
            return $normalized;
        }

        $basePath = $this->appBasePath;
        if ($basePath === '') {
            $basePath = $this->normalizeBasePath((string) ($fallbackAppBasePath ?? ''));
        }

        if (str_starts_with($normalized, '/')) {
            if ($basePath !== '' && !$this->startsWithBasePath($normalized, $basePath)) {
                return $basePath . $normalized;
            }

            return $normalized;
        }

        return ($basePath !== '' ? $basePath . '/' : '/') . ltrim($normalized, '/');
    }

    public function isManagedUploadPath(?string $value): bool
    {
        $normalized = trim((string) ($value ?? ''));
        if ($normalized === '') {
            return false;
        }

        if ($this->isAbsoluteUrl($normalized)) {
            $normalized = (string) parse_url($normalized, PHP_URL_PATH);
        }

        $normalized = ltrim(str_replace('\\', '/', $normalized), '/');

        if ($this->managedUploadPrefix !== '' && str_starts_with($normalized, $this->managedUploadPrefix . '/')) {
            return true;
        }

        return str_contains('/' . $normalized, '/' . $this->managedUploadPrefix . '/');
    }

    private function isAbsoluteUrl(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    private function normalizeBasePath(string $path): string
    {
        $normalized = trim(str_replace('\\', '/', $path));
        if ($normalized === '' || $normalized === '/') {
            return '';
        }

        return '/' . trim($normalized, '/');
    }

    private function startsWithBasePath(string $value, string $basePath): bool
    {
        return $value === $basePath || str_starts_with($value, $basePath . '/');
    }
}
