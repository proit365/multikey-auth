# Laravel Server Guide

`proit365/laravel-multikey-auth-server` integrates the framework-independent
MKAP server with Laravel 12. It provides an opt-in challenge route, request
authentication middleware, database-backed repositories, Laravel cache
rate-limiting, and guarded key-management commands.

## Requirements and installation

- PHP `^8.2`
- Laravel `^12.0`
- `ext-pdo` and a configured PDO database driver
- Composer access to the Core and framework-independent Server packages

```shell
composer require proit365/laravel-multikey-auth-server:^1.0
php artisan vendor:publish --tag=mkap-config
php artisan migrate
```

Laravel package discovery registers the service provider. Its migration is
loaded automatically; `vendor:publish` copies the configuration file so the
application can customize it.

## Configure the server

The published file is `config/multikey-auth-server.php`.

| Setting | Default | Purpose |
|---|---:|---|
| `database_connection` | `null` | Laravel database connection used by the MKAP repositories |
| `master_key_provider` | `null` | Application class implementing `MasterKeyProviderInterface` |
| `client_pool_provider` | `null` | Optional custom `ClientPoolProviderInterface` |
| `challenge_lifetime_seconds` | `30` | Challenge lifetime; valid values are 1–3,600 seconds |
| `maximum_challenge_body_bytes` | `65536` | Maximum challenge request body |
| `maximum_authenticated_body_bytes` | `1048576` | Maximum protected API body |
| `rate_limits.challenge` | `60` | Challenge requests per decay interval and client |
| `rate_limits.authenticate` | `120` | Authenticated requests per decay interval and client |
| `rate_limits.decay_seconds` | `60` | Rate-limit interval in seconds |
| `routes.enabled` | `false` | Whether to register the package's challenge route |
| `routes.challenge_path` | `v1/auth/challenges` | Challenge route path |
| `routes.middleware` | `['api']` | Middleware applied to that route |

Set the `MKAP_DATABASE_CONNECTION` and `MKAP_MASTER_KEY_PROVIDER` environment
variables as appropriate for the app. The configured master-key provider must
implement `ProIT365\MultiKeyAuth\Core\Server\MasterKeyProviderInterface` and
return a versioned, 32-byte `MasterKey` from an external secret manager. The
package intentionally does not create, store, or default a master key.

The default client-pool provider reads the package's database tables. Provide
a custom provider only when the application has a different client/pool
lookup policy. A client must resolve to exactly one active pool.

## Database and initial provisioning

The package migration creates the client, pool, key, export, challenge,
generation-batch, authentication-log, and key-rotation tables. Use supported
Laravel database drivers and keep schema changes under normal migration
control. The PDO repositories use the configured Laravel connection.

Create a client record before generating a pool. This package does not expose
a client-creation Artisan command; use the CLI package or your application's
administrative workflow. For example, with the
[MKAP CLI](https://github.com/proit365/multikey-auth-cli):

```shell
mkap client create CLIENT_001 --name="Checkout service" --confirm
```

Generate a pool and its encrypted keys:

```shell
php artisan mkap:pools:generate CLIENT_001 POOL_001 \
  --count=1000 --selection=3 --pool-version=1
php artisan mkap:pools:inspect POOL_001
```

The generator prompts for confirmation by default. `--force` skips that
prompt; use it only in a controlled administrative workflow. Pool generation
requires an existing client and rejects a duplicate pool identifier/version.
Secrets are encrypted before storage and are not printed.

## Enable challenge issuance

The challenge route is disabled by default. Set `routes.enabled` to `true`
after configuring the database and master-key provider. The package registers
a `POST` route at the configured path (default:
`/v1/auth/challenges`) with the configured middleware.

The endpoint accepts JSON containing `version`, `client_id`, `method`,
`target`, and `body_sha256`. `body_sha256` is the unpadded base64url encoding
of the 32-byte SHA-256 hash of the exact API body. A successful response
contains `version`, `request_id`, `nonce`, `key_ids`, `key_count`, `id_length`,
`expires_in`, and absolute `expires_at`. The response is marked `Cache-Control:
no-store`.

See the [MKAP v1 specification](https://github.com/proit365/multikey-auth/blob/main/specification/mkap-v1.md)
for canonical encoding and client/server message requirements.

## Protect application routes

The service provider registers the `multikey.auth` middleware alias. Attach it
only to routes that should require MKAP:

```php
use Illuminate\Support\Facades\Route;

Route::middleware('multikey.auth')->group(function (): void {
    Route::post('/api/orders', [OrderController::class, 'store']);
});
```

The middleware authenticates the method, `getRequestUri()` target, and raw
body, then calls the next middleware/controller only after proof verification.
Ensure upstream middleware does not rewrite these values before MKAP
authentication. Unprotected routes remain unprotected; the package does not
globally secure application routes.

## Key and export commands

| Command | Purpose |
|---|---|
| `mkap:pools:generate <client_id> <pool_id> [--count=1000] [--selection=3] [--pool-version=1] [--identifier-length=6] [--alphabet=...] [--force]` | Create a pool and encrypted random keys |
| `mkap:pools:inspect <pool_id>` | Show pool metadata and active/revoked key counts |
| `mkap:keys:revoke <pool_id> <identifier> [--force]` | Immediately revoke an active key |
| `mkap:keys:rotate <pool_id> <identifier> [--reason=...] [--force]` | Atomically revoke a key and create its replacement |
| `mkap:exports:create <pool_id> [--client=...] --output=<path> [--development-json] [--force]` | Write a client export to a file |
| `mkap:challenges:prune [--dry-run]` | Remove expired challenges that have no retained audit record |

Revocation and rotation affect authentication immediately. After rotating a
key, issue a new client export before clients use the replacement key.
`--force` bypasses interactive confirmation; it does not bypass validation.

Protected export creation is currently disabled because supported client
loaders do not open authenticated envelopes. The only available export mode
is `--development-json`, which writes plaintext secrets. Use it only for
development and secure the resulting file; never treat it as a production
credential-delivery mechanism. The command writes to the requested file and
does not print secret values.

## Rate limiting and HTTP limits

The package's rate limiter uses Laravel's cache limiter. Configure a shared
cache store for multiple workers or servers; a process-local cache does not
provide shared enforcement. The configured counts apply separately to
`challenge` and `authenticate` operations per client.

Enforce request-size limits at the web server/proxy and PHP layers, in addition
to the configured challenge and authenticated-body limits. Use HTTPS
end-to-end and ensure TLS termination/proxy configuration preserves the
authenticated request target and body.

## Errors and responses

Errors use a JSON object with an `error` code and `Cache-Control: no-store`.
Known protocol failures map to 400 (bad request), 401 (unauthorized), 403
(forbidden), 404 (not found), 409 (conflict/replay), 413 (payload too large),
and 429 (rate limited). Unexpected failures return 500 and are reported
through Laravel's reporting mechanism.

Do not expose secret values, expected proofs, or sensitive database details in
application logs or custom error handlers.

## Testing and production readiness

Run package tests with:

```shell
composer install
composer test
```

The package tests do not verify your production HTTPS, request-size settings,
shared cache configuration, or multi-worker deployment. Those must be tested
in the target environment. The protocol has not received an independent
security review.

For architecture, protocol, schema, and security details, see the
[monorepo documentation](https://github.com/proit365/multikey-auth/tree/main/docs).
