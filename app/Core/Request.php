<?php

namespace App\Core;

class Request
{
    private array $attributes = [];

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

        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $publicBase = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        $publicBase = $publicBase === '.' ? '' : $publicBase;
        $appBase = preg_replace('#/public$#', '', $publicBase) ?: '';

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
        return strtolower($_SERVER['REQUEST_METHOD'] ?? 'get');
    }

    public function getBody(): array
    {
        $body = [];
        if ($this->method() === 'get') {
            foreach ($_GET as $key => $value) {
                $body[$key] = filter_input(INPUT_GET, $key, FILTER_SANITIZE_SPECIAL_CHARS);
            }
        }
        if ($this->method() === 'post') {
            foreach ($_POST as $key => $value) {
                $body[$key] = filter_input(INPUT_POST, $key, FILTER_SANITIZE_SPECIAL_CHARS);
            }
        }

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if (is_array($data)) {
            $body = array_merge($body, $data);
        }

        return $body;
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

        return null;
    }

    public function setAttribute(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }
}
