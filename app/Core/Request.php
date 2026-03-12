<?php

namespace App\Core;

class Request
{
    private array $attributes = [];

    public function getPath(): string
    {
        // Ưu tiên lấy từ biến 'url' do .htaccess truyền vào (ví dụ: index.php?url=login)
        if (isset($_GET['url'])) {
            $path = '/' . trim($_GET['url'], '/');
            return $path === '' ? '/' : $path;
        }

        // Nếu không có, fallback về REQUEST_URI
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Loại bỏ phần base path nếu chạy trong thư mục con của XAMPP
        $path = str_replace('/web-dat-ve-xem-phim/public', '', $path);

        // Loại bỏ query string (phần sau dấu ?)
        $position = strpos($path, '?');
        if ($position !== false) {
            $path = substr($path, 0, $position);
        }

        // Loại bỏ slash dư thừa ở cuối
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
