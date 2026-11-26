# PHP server + Node.js client

This local-only demo runs the framework-independent PHP server package with the
Node.js client SDK. The PHP server issues a challenge, verifies the request
proof, and only then accepts the protected order request.

## Requirements

- PHP 8.2 or newer with `pdo_sqlite` and OpenSSL
- Composer 2
- Node.js 22 or newer

## Run it

From this directory:

```text
composer install
php setup.php
```

In one terminal, start the demo server:

```text
php -S 127.0.0.1:8080 router.php
```

In another terminal, send an authenticated order request:

```text
node client.js
```

The client should print an HTTP 200 response with `authenticated: true`.
Restarting the server does not lose challenges or keys because they are stored
in the local SQLite database.

## What this demonstrates

- Key-pool generation and AES-256-GCM encrypted server-side key storage
- A local client export consumed by the JavaScript SDK
- Challenge issuance bound to the exact method, request target, and body hash
- HMAC proof verification and one-time challenge consumption

The demo generates a fresh key pool and master key locally. `data/` contains
the plaintext client export and master key; it is ignored by Git and must be
treated as secret even though these are demo credentials. Do not copy these
files into a real deployment.
On Windows, the data directory inherits its filesystem permissions; restrict
access to it with your operating system's file permissions.

This is a development example, not a production server configuration. It binds
only to loopback and uses SQLite-backed demo rate limiting. It does not provide
TLS, deployment hardening, or production secret management. Do not expose it to
a network or use its generated credentials in production.
