# ProIT365 MultiKey Auth Core

`proit365/multikey-auth-core` provides the framework-independent primitives
used by the MKAP v1 client and server packages.

For installation, API, key-management, storage, and export details, see the
[complete Core package guide](docs/guide.md).

## Requirements

- PHP `^8.2`
- OpenSSL PHP extension (`ext-openssl`)
- `ext-pdo` or `ext-mysqli` only when using the corresponding database-backed
  key repository

## Installation

Install [`proit365/multikey-auth-core` from Packagist](https://packagist.org/packages/proit365/multikey-auth-core):

```shell
composer require proit365/multikey-auth-core:^1.0
```

In the monorepo, local package dependencies are managed by the root
`composer.dev.json`; do not add path repositories to this package manifest.

## Responsibilities

- Secure random generation, key identifiers, pool metadata, and key lifecycle
- HKDF-SHA-256, HMAC-SHA-256, body hashing, and canonical message encoding
- Base64url and binary encoding helpers
- AES-256-GCM secret encryption and key-repository interfaces/adapters
- MKAP key-export validation and protected-envelope primitives

The Core package exposes primitives; it does not provide HTTP routing or a
complete client/server integration. Use the Server, PHP Client, or Laravel
adapter package for those workflows. See the [MKAP specification](https://github.com/proit365/multikey-auth/tree/main/specification)
for protocol requirements.

## Example

```php
use ProIT365\MultiKeyAuth\Core\Cryptography\HkdfSha256;
use ProIT365\MultiKeyAuth\Core\Cryptography\HmacSha256;

$derivedKey = (new HkdfSha256())->derive($inputKeyMaterial, $salt, $context);
$proof = (new HmacSha256())->generate($canonicalMessage, $derivedKey);
```

The HKDF salt must be exactly 32 bytes and the input key material must not be
empty. The default output is 32 bytes, which is also the required HMAC key
length. Follow the protocol specification when constructing the context and
canonical message; do not substitute application-specific serialization.

Secrets and plaintext exports are sensitive. Keep master keys in an external
secret manager, protect exported client credentials, and do not commit real
secrets or exports.

## Tests

From a clean package checkout, install development dependencies and run:

```shell
composer install
composer test
```

The package test suite includes protocol known-answer vectors. The monorepo's
cross-package checks are run separately with `php tests/phase13-smoke.php`.
The published v1.0.1 PHPUnit suite passed with 9 tests and 20 assertions.
MySQL 8.0.46 repository checks also passed for both PDO and mysqli adapters.
These results are development verification, not production certification.

## Source and support

Project website: [https://www.proit365.com](https://www.proit365.com).

The monorepo is the development source of truth:
[github.com/proit365/multikey-auth](https://github.com/proit365/multikey-auth).
Report issues through its [issue tracker](https://github.com/proit365/multikey-auth/issues).
