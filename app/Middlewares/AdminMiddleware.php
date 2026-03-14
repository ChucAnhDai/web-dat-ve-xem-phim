<?php

namespace App\Middlewares;

use App\Core\Request;
use App\Core\Response;

class AdminMiddleware
{
    private AuthMiddleware $authMiddleware;

    public function __construct(?AuthMiddleware $authMiddleware = null)
    {
        $this->authMiddleware = $authMiddleware ?? new AuthMiddleware();
    }

    public function handle(Request $request, Response $response): bool
    {
        if (!$this->authMiddleware->handle($request, $response)) {
            return false;
        }

        return $this->authMiddleware->requireRole(['admin'], $request, $response);
    }
}
