# Laravel Client Guide

`proit365/laravel-multikey-auth` wraps the PHP MKAP client with Laravel
configuration, a Laravel HTTP transport, dependency injection, and the
`MKAP` facade. It authenticates outbound requests; it does not protect incoming
application routes.

## Requirements and installation

- PHP `^8.2`
- Laravel `^12.0`
- `proit365/multikey-auth:^1.0`

```shell
composer require proit365/laravel-multikey-auth:^1.0
php artisan vendor:publish --tag=mkap-client-config
```

Laravel package discovery registers the service provider and `MKAP` facade.
The published config file is `config/multikey-auth-client.php`.

## Configure the client

The following configuration keys are used:

| Key | Environment variable | Default | Description |
|---|---|---:|---|
| `base_uri` | `MKAP_BASE_URI` | none | HTTPS API origin; no path, query, credentials, or fragment |
| `client_id` | `MKAP_CLIENT_ID` | none | Client identifier present in the export |
| `pool_version` | `MKAP_POOL_VERSION` | `1` | Active pool version |
| `identifier_length` | `MKAP_IDENTIFIER_LENGTH` | `6` | Fixed key-identifier width |
| `identifier_alphabet` | `MKAP_IDENTIFIER_ALPHABET` | lowercase letters and digits | Identifier alphabet |
| `export_path` | `MKAP_EXPORT_PATH` | none | Readable plaintext JSON client export |
| `require_https` | `MKAP_REQUIRE_HTTPS` | `true` | Require HTTPS; keep enabled outside local tests |
| `maximum_challenge_response_bytes` | — | `65536` | Maximum accepted challenge response size |

Example environment entries:

```dotenv
MKAP_BASE_URI=https://api.example.test
MKAP_CLIENT_ID=CLIENT_001
MKAP_POOL_VERSION=1
MKAP_IDENTIFIER_LENGTH=6
MKAP_IDENTIFIER_ALPHABET=abcdefghijklmnopqrstuvwxyz0123456789
MKAP_EXPORT_PATH=/secure/application-data/mkap/client-export.json
MKAP_REQUIRE_HTTPS=true
```

The export path must be readable by the PHP service account. Keep the export
outside the public web root, restrict filesystem permissions, and do not
include it in source control, backups without encryption, or application logs.
The client loads and validates the export when `LaravelMultiKeyClient` is
first resolved from the container.

Protected export envelopes are not currently supported by the PHP client
loader. The available export format contains plaintext secrets and must be
handled as a credential.

## Make requests with the facade

Serialize the body once and pass the exact bytes that should be sent:

```php
use ProIT365\MultiKeyAuth\LaravelClient\Facades\MKAP;

$body = json_encode(['status' => 'pending'], JSON_THROW_ON_ERROR);
$response = MKAP::post('/api/orders', $body);

if ($response->statusCode !== 201) {
    throw new RuntimeException('Order request did not succeed.');
}
```

The facade supports `request`, `get`, `post`, `put`, `patch`, and `delete`.
All targets must be origin-form paths and must match the request target that
the server authenticates. Do not re-encode or mutate a body after signing.

## Dependency injection

Resolve `LaravelMultiKeyClient` directly when a facade is not preferred:

```php
use ProIT365\MultiKeyAuth\Client\HttpResponse;
use ProIT365\MultiKeyAuth\LaravelClient\LaravelMultiKeyClient;

final class OrderGateway
{
    public function __construct(
        private readonly LaravelMultiKeyClient $client
    ) {
    }

    public function submit(string $body): HttpResponse
    {
        return $this->client->post('/api/orders', $body);
    }
}
```

`HttpResponse` exposes `statusCode`, `headers`, and `body`. The SDK returns
target API responses, including non-2xx statuses, instead of treating them as
transport exceptions.

## Request and error behavior

For each target request, the package obtains a challenge, derives a proof,
and sends the original method, target, body, and MKAP headers using Laravel's
HTTP client. Keep TLS certificate verification enabled and apply appropriate
network timeouts in the application. Do not add redirects or request rewriting
that changes signed fields.

Challenge endpoint failures throw `ChallengeRequestFailedException` with the
HTTP status code. Malformed, oversized, or inconsistent challenge responses
throw `InvalidChallengeResponseException`. Invalid config or an unreadable
export fails when the client is constructed.

An API `401` is returned unchanged and is not retried. It may be an
application-level response after the server has already consumed and accepted
the authentication challenge.

## Pool rotation and deployment

When a server key is revoked or rotated, update the export and pool version
through the approved administrative process, securely distribute the new
export, and ensure the client configuration matches its metadata. A stale
export cannot prove possession of a newly selected key.

Use HTTPS, protect exports as secrets, avoid logging request proofs or
credential contents, and test the complete network path against the server.
Loopback integration tests do not establish production TLS or deployment
readiness. The protocol has not received an independent security review.

## Testing and support

Run the Laravel client package tests with:

```shell
composer install
composer test
```

For protocol, configuration, troubleshooting, and security material, see the
[monorepo documentation](https://github.com/proit365/multikey-auth/tree/main/docs)
and the [project issue tracker](https://github.com/proit365/multikey-auth/issues).
