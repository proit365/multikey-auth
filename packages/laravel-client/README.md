# Laravel Client Package

`proit365/laravel-multikey-auth` integrates the PHP MKAP client with Laravel
12. It requires PHP `^8.2` and Laravel 12, publishes configuration, and exposes
the client through dependency injection or the `MKAP` facade.

For configuration, facade and dependency-injection usage, request behavior,
and security details, see the
[complete Laravel Client guide](docs/guide.md).

## Installation

Install [`proit365/laravel-multikey-auth` from Packagist](https://packagist.org/packages/proit365/laravel-multikey-auth). Its PHP client dependency is also available on Packagist:

```shell
composer require proit365/laravel-multikey-auth:^1.0
```

Then publish its configuration:

```shell
php artisan vendor:publish --tag=mkap-client-config
```

Set `MKAP_BASE_URI`, `MKAP_CLIENT_ID`, `MKAP_POOL_VERSION`,
`MKAP_IDENTIFIER_LENGTH`, `MKAP_IDENTIFIER_ALPHABET`, and `MKAP_EXPORT_PATH`.
The export must contain the active client pool secrets and must be readable only
by the application service account.

The package does not use Laravel's HTTP redirects. Laravel's HTTP transport is
used only to send the challenge and signed API request. Application callers
serialize the exact body once and pass those bytes:

```php
use ProIT365\MultiKeyAuth\LaravelClient\Facades\MKAP;

$body = json_encode(['status' => 'pending'], JSON_THROW_ON_ERROR);
$response = MKAP::post('/api/orders', $body);

if ($response->statusCode !== 200) {
    throw new RuntimeException('API request failed.');
}
```

Dependency-injection use:

```php
public function handle(
    \ProIT365\MultiKeyAuth\LaravelClient\LaravelMultiKeyClient $client
) {
    return $client->get('/api/orders');
}
```

The package exposes challenge and malformed-response exceptions from the PHP
client. API `401` responses are returned unchanged and are not replayed.

## Tests

From a clean package checkout, install development dependencies and run:

```shell
composer install
composer test
```

The published v1.0.1 integration suite passed with 5 tests and 9 assertions,
including an authenticated request that received HTTP 200 from the local
Laravel test server. This loopback test is not a production HTTPS validation.

## Source and support

Project website: [https://www.proit365.com](https://www.proit365.com).

The monorepo is the development source of truth:
[github.com/proit365/multikey-auth](https://github.com/proit365/multikey-auth).
Report issues through its [issue tracker](https://github.com/proit365/multikey-auth/issues).
