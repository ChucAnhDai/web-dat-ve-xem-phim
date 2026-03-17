<?php

namespace App\Core;

class Request
{
    private array $attributes = [];

    public function publicBasePath(): string
    {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $publicBase = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

        return $publicBase === '.' ? '' : $publicBase;
    }

    public function appBasePath(): string
    {
        return preg_replace('#/public$#', '', $this->publicBasePath()) ?: '';
    }

    public function getPath(): string
    {
        if (isset($_GET['url'])) {
            $path = '/' . trim((string) $_GET['url'], '/');
            return $path === '' ? '/' : $path;
        }

        $path = $_SERVER['REQUEST_URI'] ?? '/';
        $position = strpos($path, '?');
        if ($position !== false) {
            $path = substr($path, 0, $position);
        }

        $publicBase = $this->publicBasePath();
        $appBase = $this->appBasePath();

        foreach ([$publicBase, $appBase] as $basePath) {
            if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
                $path = substr($path, strlen($basePath));
                break;
            }
        }

        $path = '/' . ltrim($path, '/');
        $path = rtrim($path, '/');

        return $path === '' ? '/' : $path;
    }

    public function method(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST') {
            $override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? ($_POST['_method'] ?? null);
            if (is_string($override) && $override !== '') {
                $method = strtoupper($override);
            }
        }

        return strtolower($method);
    }

    public function getBody(): array
    {
        $body = [];
        $serverMethod = strtolower($_SERVER['REQUEST_METHOD'] ?? 'get');

        if ($serverMethod === 'get') {
            foreach ($_GET as $key => $value) {
                $body[$key] = $this->sanitizeInputValue($value);
            }
        }
        if ($serverMethod === 'post') {
            foreach ($_POST as $key => $value) {
                $body[$key] = $this->sanitizeInputValue($value);
            }
        }

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if (is_array($data)) {
            $body = array_merge($body, $this->sanitizeInputValue($data));
        }

        return $body;
    }

    public function getFiles(): array
    {
        $normalized = [];

        foreach ($_FILES as $field => $fileSpec) {
            if (!is_array($fileSpec)) {
                continue;
            }

            $normalized[$field] = $this->normalizeFileSpec($fileSpec);
        }

        return $normalized;
    }

    public function getFile(string $field)
    {
        $files = $this->getFiles();

        return $files[$field] ?? null;
    }

    private function sanitizeInputValue($value)
    {
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $item) {
                $sanitized[$key] = $this->sanitizeInputValue($item);
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return $value;
        }

        return $value;
    }

    private function normalizeFileSpec(array $fileSpec)
    {
        if (!array_key_exists('name', $fileSpec)) {
            $normalized = [];
            foreach ($fileSpec as $key => $value) {
                if (is_array($value)) {
                    $normalized[$key] = $this->normalizeFileSpec($value);
                }
            }

            return $normalized;
        }

        if (!is_array($fileSpec['name'])) {
            return [
                'name' => $fileSpec['name'] ?? null,
                'type' => $fileSpec['type'] ?? null,
                'tmp_name' => $fileSpec['tmp_name'] ?? null,
                'error' => $fileSpec['error'] ?? UPLOAD_ERR_NO_FILE,
                'size' => $fileSpec['size'] ?? 0,
            ];
        }

        $normalized = [];
        foreach (array_keys($fileSpec['name']) as $key) {
            $normalized[$key] = $this->normalizeFileSpec([
                'name' => $fileSpec['name'][$key] ?? null,
                'type' => $fileSpec['type'][$key] ?? null,
                'tmp_name' => $fileSpec['tmp_name'][$key] ?? null,
                'error' => $fileSpec['error'][$key] ?? UPLOAD_ERR_NO_FILE,
                'size' => $fileSpec['size'][$key] ?? 0,
            ]);
        }

        return $normalized;
    }

    public function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!$header && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header = $headers['Authorization'] ?? '';
        }
        if (stripos($header, 'Bearer ') === 0) {
            return trim(substr($header, 7));
        }

        foreach (['cinemax_admin_token', 'cinemax_token'] as $cookieName) {
            $cookieValue = $this->cookie($cookieName);
            if (is_string($cookieValue) && trim($cookieValue) !== '') {
                return trim($cookieValue);
            }
        }

        return null;
    }

    public function cookie(string $key, $default = null)
    {
        return $_COOKIE[$key] ?? $default;
    }

    public function setAttribute(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    public function setRouteParams(array $params): void
    {
        $this->attributes['routeParams'] = $params;
    }

    public function getRouteParams(): array
    {
        $params = $this->attributes['routeParams'] ?? [];

        return is_array($params) ? $params : [];
    }

    public function getRouteParam(string $key, $default = null)
    {
        return $this->getRouteParams()[$key] ?? $default;
    }
}
