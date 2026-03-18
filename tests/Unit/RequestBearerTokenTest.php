<?php

namespace Tests\Unit;

use App\Core\Request;
use PHPUnit\Framework\TestCase;

class RequestBearerTokenTest extends TestCase
{
    protected function tearDown(): void
    {
        $_COOKIE = [];
        $_SERVER = [];
    }

    public function testBearerTokenReturnsAuthorizationHeaderWhenPresent(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer header-token';
        $_COOKIE['cinemax_admin_token'] = 'admin-cookie-token';

        $request = new Request();

        $this->assertSame('header-token', $request->bearerToken());
    }

    public function testBearerTokenAcceptsAdminCookieFallback(): void
    {
        $_COOKIE['cinemax_admin_token'] = 'admin-cookie-token';

        $request = new Request();

        $this->assertSame('admin-cookie-token', $request->bearerToken());
    }

    public function testBearerTokenIgnoresLegacyUserCookieFallback(): void
    {
        $_COOKIE['cinemax_token'] = 'legacy-user-cookie-token';

        $request = new Request();

        $this->assertNull($request->bearerToken());
    }
}
