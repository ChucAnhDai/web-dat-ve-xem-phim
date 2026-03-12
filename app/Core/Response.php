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
}
