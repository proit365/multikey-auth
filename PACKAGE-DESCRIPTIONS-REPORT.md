# Package Descriptions Report

## Inventory

The monorepo contains eight distributable packages under `packages/`. The
`examples/end-to-end/composer.json` manifest was inspected but is not an
additional distributable SDK or package: it declares `"type": "project"` and
is a local end-to-end example.

## Package results

| Package | Manifest | Original description | New description | Rationale |
|---|---|---|---|---|
| `proit365/multikey-auth-core` | `packages/core/composer.json` | `Framework-independent cryptographic core for ProIT365 MultiKey Auth MKAP v1` | `PHP cryptographic and key-management core for ProIT365 MultiKey Auth (MKAP), implementing protocol primitives, key pools, exports, and storage adapters.` | The core exposes cryptography, key lifecycle, export, encoding, random generation, and repository primitives. It does not itself provide HTTP routing or a complete client/server integration. |
| `proit365/multikey-auth-server` | `packages/server/composer.json` | `Framework-independent MKAP challenge issuer and authentication verifier` | `Framework-independent PHP server library for ProIT365 MultiKey Auth (MKAP), providing challenge issuance and request proof verification.` | The implementation issues challenges, verifies request-bound proofs, consumes challenges once, checks replay and expiration, and provides framework-independent repositories and rate-limiter boundaries. |
| `proit365/multikey-auth` | `packages/client-php/composer.json` | `PHP client SDK for ProIT365 MultiKey Auth MKAP v1` | `PHP client SDK for ProIT365 MultiKey Auth (MKAP), issuing challenges and creating request-bound proofs for authenticated API calls.` | The PHP client loads key exports and sends challenge and authenticated API requests, using the exact supplied method, target, and body. |
| `proit365/laravel-multikey-auth-server` | `packages/laravel-server/composer.json` | `Laravel 12 server adapter for ProIT365 MultiKey Auth MKAP v1` | `Laravel 12 server integration for ProIT365 MultiKey Auth (MKAP), with challenge routes, authentication middleware, and key administration commands.` | The package registers Laravel middleware and opt-in challenge routes and adds pool, key, export, and challenge administration commands. It is an adapter around the server package, not a standalone protocol implementation. |
| `proit365/laravel-multikey-auth` | `packages/laravel-client/composer.json` | `Laravel 12 client integration for ProIT365 MultiKey Auth MKAP v1` | `Laravel 12 client integration for ProIT365 MultiKey Auth (MKAP), providing a configured PHP client and facade for challenge-response API authentication.` | It wraps the PHP client with Laravel configuration, dependency injection, HTTP transport, and an `MKAP` facade. It does not implement server-side verification. |
| `proit365/multikey-auth-cli` | `packages/cli/composer.json` | `Framework-independent administrative CLI for ProIT365 MultiKey Auth` | `Administrative PHP CLI for ProIT365 MultiKey Auth (MKAP), with client and pool management, key lifecycle operations, exports, challenge cleanup, and audit records.` | The actual CLI commands cover client and pool operations, key revoke/rotate, export creation and validation, challenge cleanup, and audit listing. |
| `proit365_multikey_auth` | `packages/flutter/pubspec.yaml` | `MKAP v1 client SDK for Dart and Flutter applications.` | `Dart and Flutter client SDK for ProIT365 MultiKey Auth (MKAP), implementing multi-key challenge-response authentication for APIs.` | The implementation is a Dart/Flutter MKAP client that loads an export, obtains challenges, and creates request-bound proofs while leaving secure storage and transport replaceable. |
| `@proit365/multikey-auth` | `packages/javascript/package.json` | `Server-side JavaScript SDK for ProIT365 MultiKey Auth MKAP v1` | `Node.js client SDK for ProIT365 MultiKey Auth (MKAP), creating challenge-response proofs for authenticated API requests.` | The JavaScript entry point exports `MkapClient` and transport/export/cryptography helpers for Node.js API clients. It is not a server implementation or server middleware package. |

## Validation

### Composer

The following command was run for all six Composer packages:

```text
composer validate --no-check-publish --working-dir <package>
```

Actual result: all six manifests passed and reported `./composer.json is valid`.

### Flutter

Commands run:

```text
flutter pub get
flutter pub publish --dry-run
flutter test
```

Actual results:

- `flutter pub get`: passed; dependencies resolved.
- `flutter pub publish --dry-run`: package archive and validation completed, but pub reported one warning because `pubspec.yaml` is modified in git and exited with code 65. It did not publish.
- `flutter test`: passed; both interoperability tests passed.

### JavaScript

Commands run:

```text
npm pkg get name description
npm test
```

Actual result:

- The repository uses npm 6.14.4, which does not support `npm pkg get`; that command printed npm usage instead of package metadata.
- A direct `node -e` manifest read confirmed name `@proit365/multikey-auth` and the new description.
- `npm test` passed: `Phase 10 JavaScript interoperability tests passed.`

## Skipped packages

None of the eight listed distributable packages was skipped.

The `examples/end-to-end/composer.json` project was identified but excluded
from the package audit because it is explicitly a local example project
(`"type": "project"`), not a distributable SDK or package.
