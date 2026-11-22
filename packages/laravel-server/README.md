# Laravel Server Package

`proit365/laravel-multikey-auth-server` integrates the framework-independent
MKAP server with Laravel 12. It requires PHP 8.2 or newer; Laravel 13 is not
supported by this package version. Laravel integration tests are included but
could not be run in the current environment because Composer could not verify
Packagist's TLS certificate.

## Installation

Install the package in a Laravel 12 application. Laravel package discovery
registers `MultiKeyAuthServiceProvider`. Publish its configuration and migrate:

```shell
php artisan vendor:publish --tag=mkap-config
php artisan migrate
```

Configure `master_key_provider` with a class implementing
`MasterKeyProviderInterface`. The provider must retrieve versioned 32-byte
master keys from a KMS or another external secret store. The package does not
provide a hard-coded or database-backed master key.

Challenge routes are opt-in. Set `routes.enabled` to `true` to register the
configured `POST /v1/auth/challenges` endpoint. The route is not registered by
default. Attach `multikey.auth` only to routes that should require MKAP:

```php
Route::middleware('multikey.auth')->group(function () {
    Route::post('/api/orders', OrderController::class);
});
```

The middleware must run after any application middleware that intentionally
changes the HTTP method or request target. It binds the request method,
`getRequestUri()` target, and raw body bytes; application middleware must not
rewrite these values before authentication.

The database migration creates the MKAP tables for MySQL and SQLite. Each
client must have exactly one active key pool. The pool's identifier alphabet
and length are persisted with its metadata. Applications that need a different
client/pool lookup policy may configure a custom
`ClientPoolProviderInterface` implementation.

The package uses Laravel's cache-backed rate limiter. Configure a shared cache
store for multi-process or multi-server deployments. Configure web-server and
PHP request-size limits as well as `maximum_challenge_body_bytes` and
`maximum_authenticated_body_bytes`.

Run `php artisan mkap:challenges:prune --dry-run` to preview cleanup. The
command retains challenges referenced by audit records.

Key and pool administration is available through guarded commands:

```shell
php artisan mkap:pools:generate CLIENT_001 POOL_001 --count=1000 --selection=3
php artisan mkap:pools:inspect POOL_001
php artisan mkap:keys:revoke POOL_001 a8f31x
php artisan mkap:keys:rotate POOL_001 a8f31x --reason="Scheduled replacement"
php artisan mkap:exports:create POOL_001 --output=storage/mkap/client-export.bin
```

The export command never prints secret values. `--development-json --output=...`
writes a mode-`0600` plaintext export atomically for development only and
requires explicit confirmation. Protected export creation is currently disabled
until every supported client SDK implements authenticated-envelope opening.
Destructive commands require confirmation unless `--force` is supplied.

Only the challenge endpoint is registered by this package, and only when
explicitly enabled. Unrelated application routes are not protected
automatically.
