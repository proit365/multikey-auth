# Laravel Client Package

`realmatrix/laravel-multikey-auth` integrates the PHP MKAP client with Laravel
12. It publishes configuration and exposes the client through dependency
injection or the `MKAP` facade.

## Installation

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
