<?php

namespace App\Support;

use App\Core\Request;
use App\Core\Response;

class TicketSessionManager
{
    public const COOKIE_NAME = 'cinemax_ticket_session';

    public function resolve(Request $request): ?string
    {
        $token = trim((string) $request->cookie(self::COOKIE_NAME, ''));

        return $this->isValid($token) ? $token : null;
    }

    public function ensure(Request $request, Response $response): string
    {
        $existing = $this->resolve($request);
        if ($existing !== null) {
            return $existing;
        }

        $token = bin2hex(random_bytes(24));
        $response->setCookie(self::COOKIE_NAME, $token, [
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        return $token;
    }

    public function isValid(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        return (bool) preg_match('/^[a-f0-9]{48}$/', $token);
    }
}
