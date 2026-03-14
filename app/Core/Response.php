<?php

namespace App\Core;

class Response
{
    public function setStatusCode(int $code): void
    {
        http_response_code($code);
    }

    public function json($data, int $statusCode = 200): void
    {
        $this->setStatusCode($statusCode);
        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function success(string $message, array $data = [], int $statusCode = 200): void
    {
        $this->json([
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    public function error(string $message, array $errors = [], int $statusCode = 400): void
    {
        $this->json([
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }

    public function redirect(string $path, int $statusCode = 302): void
    {
        $this->setStatusCode($statusCode);
        header('Location: ' . $path, true, $statusCode);
        exit;
    }

    public function setCookie(string $name, string $value, array $options = []): void
    {
        $defaults = [
            'expires' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        $settings = array_merge($defaults, $options);

        setcookie($name, $value, $settings);
        $_COOKIE[$name] = $value;
    }

    public function clearCookie(string $name, array $options = []): void
    {
        $this->setCookie($name, '', array_merge($options, [
            'expires' => time() - 3600,
        ]));
        unset($_COOKIE[$name]);
    }

    public function view(string $viewPath, array $params = []): void
    {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $publicBase = rtrim(dirname($scriptName), '/');
        $publicBase = $publicBase === '.' ? '' : $publicBase;
        $appBase = preg_replace('#/public$#', '', $publicBase) ?: '';

        $params['publicBase'] = $params['publicBase'] ?? $publicBase;
        $params['appBase'] = $params['appBase'] ?? $appBase;

        extract($params);

        $layout = $params['layout'] ?? 'layouts/main';

        ob_start();
        require_once __DIR__ . '/../../views/' . ltrim($viewPath, '/') . '.php';
        $content = ob_get_clean();

        require_once __DIR__ . '/../../views/' . ltrim($layout, '/') . '.php';
        exit;
    }
}
