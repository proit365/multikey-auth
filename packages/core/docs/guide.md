# Core Package Guide

`proit365/multikey-auth-core` contains the framework-independent cryptographic,
key-management, export, and storage primitives used by the MKAP PHP packages.
It does not register HTTP routes or provide a complete client or server.

## Requirements and installation

- PHP `^8.2`
- `ext-openssl`
- `ext-pdo` or `ext-mysqli` only when using the corresponding database key
  repository

Install the package:

```shell
composer require proit365/multikey-auth-core:^1.0
```

Classes use the `ProIT365\MultiKeyAuth\Core\` namespace and are loaded through
Composer's PSR-4 autoloader.

## Key pools and identifiers

`PoolMetadata` describes a pool using its public identifier, version,
identifier length and alphabet, total key count, and challenge selection
count. Versions and counts must be positive, the selection count cannot exceed
the pool size, and identifier alphabets cannot contain duplicate characters.
The default identifier alphabet is lowercase ASCII letters and digits; the
default identifier length is six.

`IdentifierGenerator` uses a `RandomGeneratorInterface` implementation to
produce unique identifiers. `SecureRandomGenerator` is the production
implementation. `PoolGenerator` creates 32-byte `KeyMaterial` values and
`ManagedKey` records; it supports progress callbacks and cancellation. Pool
generation is limited to 1,000,000 keys.

```php
use ProIT365\MultiKeyAuth\Core\Key\IdentifierGenerator;
use ProIT365\MultiKeyAuth\Core\Key\PoolGenerator;
use ProIT365\MultiKeyAuth\Core\Key\PoolMetadata;
use ProIT365\MultiKeyAuth\Core\Random\SecureRandomGenerator;

$random = new SecureRandomGenerator();
$metadata = new PoolMetadata(
    poolId: 'POOL_001',
    poolVersion: 1,
    identifierLength: 6,
    identifierAlphabet: IdentifierGenerator::DEFAULT_ALPHABET,
    keyCount: 1000,
    selectionCount: 3
);
$generator = new PoolGenerator(
    $random,
    new IdentifierGenerator(
        $random,
        $metadata->identifierAlphabet,
        $metadata->identifierLength
    )
);
$keys = $generator->generate($metadata);
```

Keep generated `KeyMaterial` and exports secret. Identifiers are public
references; they are not substitutes for the secret bytes.

## Cryptographic and encoding primitives

- `HkdfSha256::derive($ikm, $salt, $info, $length = 32)` derives key material.
  Input key material must not be empty, the salt must be exactly 32 bytes, and
  the output length must be between 1 and 8,160 bytes.
- `HmacSha256::generate($message, $key)` requires a 32-byte key and returns a
  32-byte binary proof. `verify()` uses a constant-time comparison.
- `BodyHasher::sha256()` returns the raw 32-byte SHA-256 digest;
  `sha256Hex()` returns its hexadecimal representation.
- `CanonicalMessageEncoder` constructs the MKAP v1 HKDF context and proof
  message. Use it rather than application-specific serialization.
- `BinaryEncoder` writes network-byte-order integer and length-prefixed fields.
  `Base64Url` provides the protocol's unpadded base64url representation.
- `RequestMetadataValidator` validates protocol client identifiers, HTTP
  methods, request targets, and binary body hashes.

The exact field ordering and wire requirements are defined by the
[MKAP v1 specification](https://github.com/proit365/multikey-auth/blob/main/specification/mkap-v1.md)
and its [test vectors](https://github.com/proit365/multikey-auth/blob/main/specification/test-vectors-v1.json).
Do not change canonical encoding in an application adapter.

## Secret encryption and storage

`MasterKey` accepts exactly 32 bytes and a positive key version. Obtain those
bytes from an external key-management system; do not hard-code or store the
master key beside encrypted records.

`SecretCipher` encrypts 32-byte MKAP secrets with AES-256-GCM using a fresh
12-byte nonce and a 16-byte authentication tag. Supply the same associated
data when decrypting. For stored keys, use
`KeyAssociatedData::forKey($poolIdentifier, $keyIdentifier)` so ciphertext is
bound to its pool and identifier.

`EncryptedSecret::toBinary()` and `fromBinary()` serialize the envelope
(format version, encryption-key version, nonce, tag, and ciphertext). Store
the envelope and encryption-key version, not plaintext key bytes.

`KeyRepositoryInterface` defines storage operations for inserting keys,
finding active keys, selecting active identifiers, revoking keys, and counting
active keys. Implementations are provided for PDO and mysqli; an in-memory
implementation is intended for tests and development. The database adapters
expect the MKAP schema. See the [MySQL schema](https://github.com/proit365/multikey-auth/tree/main/database/mysql)
or [SQLite schema](https://github.com/proit365/multikey-auth/tree/main/database/sqlite).

## Export format

`KeyExport` represents an MKAP v1 manifest. It includes format and protocol
versions, client and pool identifiers, pool/export versions, creation time,
identifier rules, secret length, key count, and key records. Each key record
contains a public identifier and base64url-encoded secret.

`ExportValidator::validate()` checks required metadata, key count, identifier
format and uniqueness, and decodes each 32-byte secret. `ExportManager` can
serialize plaintext JSON, calculate its SHA-256 checksum, and create or open an
AES-GCM protected envelope.

The primitives do not mean every client can load protected envelopes:
currently, the PHP, JavaScript, and Flutter client loaders support plaintext
JSON exports only. The Laravel server integration therefore disables protected
export creation and labels plaintext exports as development-only. Never treat
a checksum or encryption primitive as a substitute for secure export delivery
and storage.

## Testing

Run the package test suite from a clean checkout:

```shell
composer install
composer test
```

The tests include protocol known-answer vectors. The monorepo's
cross-package checks are separate from this package suite.

## Security and support

Do not log, commit, or expose secrets, plaintext exports, or master-key bytes.
Use HTTPS for authentication requests, protect client exports at rest, and
review the threat model before deployment. This package has not received an
independent security review and is not production certification.

For the complete project specification and security guidance, see the
[monorepo documentation](https://github.com/proit365/multikey-auth/tree/main/docs)
and [issue tracker](https://github.com/proit365/multikey-auth/issues).
