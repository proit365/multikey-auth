# PHP Client SDK Guide

`proit365/multikey-auth` is the framework-independent PHP client for MKAP v1.
It loads a client export, obtains a challenge, derives a request-bound proof,
and sends the original API request through an application-provided HTTP
transport.

## Requirements and installation

- PHP `^8.2`
- `proit365/multikey-auth-core:^1.0`
- `ext-openssl` through the Core dependency

```shell
composer require proit365/multikey-auth:^1.0
```

Classes use the `ProIT365\MultiKeyAuth\Client\` namespace. For Laravel, the
[Laravel client package](https://github.com/proit365/laravel-multikey-auth)
provides configuration, dependency injection, and a facade.

## Load the client key export

The SDK accepts a plaintext MKAP v1 JSON export. Load it with `ExportLoader`;
the loader validates the manifest and decodes the base64url key values:

```php
use ProIT365\MultiKeyAuth\Client\ExportLoader;

$exportJson = file_get_contents('/secure/path/client-export.json');
if ($exportJson === false) {
    throw new RuntimeException('Unable to read MKAP export.');
}

$keyPool = (new ExportLoader())->fromJson($exportJson);
```

The default maximum export size is 64 MiB. The SDK keeps the decoded secrets
in process memory for the lifetime of the loaded pool. Store exports outside
the web root and restrict their access to the application service account.
Protected export envelopes are not currently supported by this loader.

## Configure and construct the client

`ClientConfig` requires:

| Argument | Meaning |
|---|---|
| `baseUri` | HTTPS origin only; no path, query, credentials, or fragment |
| `clientIdentifier` | Public identifier matching the export |
| `poolVersion` | Positive pool version matching the export |
| `identifierLength` | Identifier width matching the export |
| `identifierAlphabet` | Unique-character alphabet matching the export |
| `maximumChallengeResponseBytes` | Optional limit; defaults to 65,536 bytes |
| `requireHttps` | Defaults to `true`; disable only for local development/tests |

Supply an implementation of `HttpTransportInterface`. The transport sends an
`HttpRequest` and returns an `HttpResponse`; it must preserve the supplied
method, URI, headers, and body bytes. Do not follow redirects or rewrite the
request target/body after signing.

```php
use ProIT365\MultiKeyAuth\Client\ClientConfig;
use ProIT365\MultiKeyAuth\Client\ExportLoader;
use ProIT365\MultiKeyAuth\Client\PhpMultiKeyClient;
use ProIT365\MultiKeyAuth\Core\Key\IdentifierGenerator;

$pool = (new ExportLoader())->fromJson($exportJson);
$config = new ClientConfig(
    'https://api.example.test',
    'CLIENT_001',
    1,
    6,
    IdentifierGenerator::DEFAULT_ALPHABET
);
$client = new PhpMultiKeyClient($config, $pool, $httpTransport);
```

Construction fails if the client identifier or pool metadata differs from the
loaded export.

## Make an authenticated request

Pass the exact origin-form target, including its query string, and the exact
body string that the transport will send. Serialize an application payload
once before calling `request()`:

```php
$body = json_encode(['status' => 'pending'], JSON_THROW_ON_ERROR);
$response = $client->request('POST', '/api/orders?source=checkout', $body);

if ($response->statusCode < 200 || $response->statusCode >= 300) {
    // Handle the target API's response according to application policy.
}
```

The SDK hashes the body, requests a challenge at `/v1/auth/challenges`, derives
the proof using the server-selected key identifiers and challenge values, and
sends the original request with MKAP headers. The SDK does not serialize
payloads, add retries, or automatically replay API `401` responses.

`HttpResponse` exposes `statusCode`, `headers`, and `body`. Non-2xx responses
from the target API are returned normally. Challenge failures are different:

- `ChallengeRequestFailedException` is thrown for a non-2xx challenge response
  and includes its status code.
- `InvalidChallengeResponseException` is thrown for malformed, oversized, or
  inconsistent challenge data, including selected keys missing from the
  export.
- Invalid local request metadata or configuration throws
  `InvalidArgumentException`.

## Transport implementation requirements

Implement `HttpTransportInterface::send(HttpRequest $request): HttpResponse`
using the HTTP client chosen by your application. Ensure that the implementation:

1. Sends the method and URI supplied in the `HttpRequest`.
2. Sends every header supplied by the SDK.
3. Sends the body bytes unchanged, including an empty body.
4. Does not follow redirects or otherwise change the signed request.
5. Applies connection/read timeouts and TLS certificate verification suitable
   for the application.

The package deliberately does not choose an HTTP library or connection policy.

## Security and operational notes

- HTTPS is required by default. Never disable it in production.
- Protect exports as credentials; a complete export permits client
  impersonation.
- Do not log export contents, secrets, derived proof keys, or proof material.
- A `401` can come from the target application after successful
  authentication. The SDK returns it and does not retry the operation.
- Keep client and server pool versions, identifier rules, and exports aligned.
- This package has not received an independent security review; test the
  complete deployment and review the threat model.

## Testing and support

The standalone PHP Client manifest does not currently declare a Composer test
script. The monorepo contains the protocol specification, test vectors, and
cross-framework test suites at
[github.com/proit365/multikey-auth](https://github.com/proit365/multikey-auth).
Report issues through the
[project issue tracker](https://github.com/proit365/multikey-auth/issues).
