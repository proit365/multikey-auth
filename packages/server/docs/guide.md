# Framework-independent Server Guide

`proit365/multikey-auth-server` implements MKAP v1 challenge issuance and
request-proof verification. It supplies interfaces and PDO/in-memory adapters,
but no HTTP router, middleware, or web-server configuration. Laravel
applications can use the
[Laravel server package](https://github.com/proit365/laravel-multikey-auth-server).

## Requirements and installation

- PHP `^8.2`
- `proit365/multikey-auth-core:^1.0`
- `ext-pdo` when using `PdoChallengeRepository`
- An installed PDO driver matching the selected database

```shell
composer require proit365/multikey-auth-server:^1.0
```

The package's server classes use the `ProIT365\MultiKeyAuth\Core\Server\`
namespace.

## Request lifecycle

The server has two operations:

1. Issue a short-lived challenge bound to a client's method, request target,
   and body hash.
2. Verify a proof for that challenge, then consume the challenge once.

The HTTP adapter is responsible for preserving the exact request method,
origin-form request target (including its query string), and raw body bytes
between challenge creation and authentication.

### Challenge request

Create a `ChallengeRequest` with protocol version `1`, the public client
identifier, uppercase HTTP method, request target, and the raw 32-byte
SHA-256 digest of the eventual request body.

The corresponding HTTP JSON representation is:

```json
{
  "version": 1,
  "client_id": "CLIENT_001",
  "method": "POST",
  "target": "/api/orders?draft=1",
  "body_sha256": "BASE64URL_OF_32_BYTE_SHA256"
}
```

The wire `body_sha256` value is unpadded base64url. Decode it before creating
the core `ChallengeRequest`.

`ChallengeIssuer::issue()` rate-limits the client, finds its active pool,
selects the configured number of active key identifiers, creates a random
16-byte request identifier and 32-byte nonce, stores the challenge, and
returns a `ChallengeResponse`. Challenge lifetime defaults to 30 seconds and
must be between 1 and 3,600 seconds.

The response fields are:

| Field | Meaning |
|---|---|
| `version` | Protocol version (`1`) |
| `request_id` | 16-byte request identifier, unpadded base64url |
| `nonce` | 32-byte nonce, unpadded base64url |
| `key_ids` | Selected fixed-width identifiers concatenated in selection order |
| `key_count` | Number of selected identifiers |
| `id_length` | Identifier width needed to split `key_ids` |
| `expires_in` | Challenge lifetime in seconds |
| `expires_at` | Absolute server-issued Unix timestamp |

Clients must use the server-provided `expires_at` and key order when building
the proof.

### Authentication request

After decoding the request identifier and proof from base64url, construct an
`AuthenticatedRequest` with the client identifier, binary request identifier,
binary 32-byte proof, uppercase method, exact target, raw request body, and
protocol version `1`.

`AuthenticationVerifier::verify()` checks challenge state and expiry, body
size, client/request binding, selected active keys, and the HMAC proof. It
consumes the challenge only after successful verification. An adapter must
call it before authorizing the protected operation.

The Laravel adapter uses these headers:

```text
X-MKAP-Version: 1
X-MKAP-Client: CLIENT_001
X-MKAP-Request: <base64url request_id>
X-MKAP-Proof: <base64url 32-byte proof>
```

## Composition interfaces

`ChallengeIssuer` and `AuthenticationVerifier` are composed from:

| Interface or service | Responsibility |
|---|---|
| `ClientPoolProviderInterface` | Resolve the client's single active pool |
| `KeyRepositoryInterface` | Fetch active key records and identifiers |
| `ChallengeRepositoryInterface` | Store, retrieve, and atomically consume challenges |
| `RateLimiterInterface` | Limit challenge and authentication operations |
| `MasterKeyProviderInterface` | Supply a versioned master key for a pool |
| `ClockInterface` | Provide server time |
| Core crypto services | Hash bodies, encode messages, derive keys, and verify HMAC |

The provided `PdoChallengeRepository` persists challenges and uses a conditional
update for consumption. It requires the MKAP challenge tables; use the
[MySQL](https://github.com/proit365/multikey-auth/tree/main/database/mysql)
or [SQLite](https://github.com/proit365/multikey-auth/tree/main/database/sqlite)
schema from the monorepo. A database-backed `KeyRepositoryInterface` is
provided by Core.

`InMemoryChallengeRepository`, `InMemoryClientPoolProvider`, and
`FixedWindowRateLimiter` are useful for tests and single-process development.
The fixed-window limiter keeps state in process memory and must not be used as
a shared production limiter. Production integrations need a shared,
appropriately atomic rate limiter and must validate concurrency behavior with
their actual database and cache deployment.

## Validation and error mapping

The core exposes typed exceptions so adapters can map failures consistently.
The Laravel adapter maps them as follows:

| Failure | HTTP status |
|---|---:|
| Malformed JSON or invalid/unsupported challenge metadata | 400 |
| Missing, invalid, expired, or request-mismatched proof | 401 |
| Unknown client | 403 |
| Unknown challenge | 404 |
| Replay or already-consumed challenge | 409 |
| Oversized request body | 413 |
| Rate limit exceeded | 429 |
| Unexpected internal failure | 500 |

The shipped Laravel adapter does not emit `422`; invalid challenge metadata is
currently mapped to `400`. Unavailable selected keys fail verification with
`401`.

Do not reveal secret material, expected proofs, or unnecessary internal
database details in client-facing errors.

## Limits and production responsibilities

`AuthenticationVerifier` defaults to a maximum authenticated body of 1 MiB.
The HTTP integration must impose its own request-size limits before reading
large bodies. The adapter must also configure an appropriate challenge-body
limit and challenge lifetime.

The core does not configure HTTPS, reverse proxies, shared caches, persistent
database schemas, master-key management, or operational monitoring. These are
deployment responsibilities. See the
[protocol specification](https://github.com/proit365/multikey-auth/blob/main/specification/mkap-v1.md)
and [security guide](https://github.com/proit365/multikey-auth/blob/main/docs/security.md).

## Testing

The standalone Server package does not currently declare a `composer test`
script or ship a package-local test suite. Use the monorepo's interoperability
and database test suites for cross-package verification. Passing tests do not
establish production readiness or multi-worker replay protection.
