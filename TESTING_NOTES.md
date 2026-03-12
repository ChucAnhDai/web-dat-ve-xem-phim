# Testing Notes

## Summary of Changes

- Added dependency injection to `AuthService` so repositories/auth/logger can be mocked in tests.
- Added dependency injection to `AuthController` for easier controller testing.
- Added PHPUnit setup:
  - `composer.json` (dev dependency `phpunit/phpunit`)
  - `phpunit.xml`
  - `tests/bootstrap.php` (loads Composer autoload if present, falls back to project autoloader)
- Added unit tests for `AuthService` in `tests/Unit/AuthServiceTest.php`.
- Added feature-style tests for `AuthController` in `tests/Feature/AuthControllerTest.php`.

## Next Steps

1. Run `composer install` to install PHPUnit (dev-only).
2. Execute tests with `vendor/bin/phpunit`.
3. If you want deeper integration tests, add router-level tests that hit `/api/auth/*` using a test HTTP layer.
4. Consider adding more test cases (token expiration, role-specific behavior, phone field validation).
