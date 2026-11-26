# MKAP Interoperability and Test Status

The common fixture is `specification/test-vectors-v1.json`. It covers the
HKDF-SHA256 context and output, canonical proof message, HMAC-SHA256 output,
and base64url request identifier, nonce, and proof.

## Recorded published-package tests (2026-11-26)

The table below records the versions that were installed and tested on that
date. It is historical evidence, not a claim that every package release now
published has passed these tests.

| Published package | Version | Test and result |
|---|---:|---|
| `proit365/multikey-auth-core` | 1.0.1 | Automated smoke test passed. |
| `proit365/multikey-auth-server` | 1.0.1 | Automated smoke test passed. |
| `proit365/multikey-auth-cli` | 1.0.1 | Automated CLI smoke test passed; secret values were not printed. |
| `proit365/multikey-auth` (PHP client) | 1.0.2 | Live challenge-response test passed; protected endpoint returned HTTP 200. |
| `proit365/laravel-multikey-auth-server` | 1.0.2 | Composer test suite passed: 4 tests, 9 assertions. |
| `proit365/laravel-multikey-auth` (Laravel client) | 1.0.1 | Composer test suite passed: 5 tests, 9 assertions, including live HTTP 200. |
| `@proit365/multikey-auth` | 1.0.2 | npm test passed: 2 tests, including live HTTP 200. |
| `proit365_multikey_auth` | 1.0.3 | Dart test passed: 2 tests, including live HTTP 200; `dart analyze` found no issues. |

Public-registry snapshot on 2026-11-26: npm `@proit365/multikey-auth` is
1.0.3, pub.dev `proit365_multikey_auth` is 1.0.4, Packagist
`proit365/multikey-auth` is 1.0.3,
`proit365/laravel-multikey-auth-server` is 1.0.3, and
`proit365/laravel-multikey-auth` is 1.0.2. The newer Flutter, npm, PHP client,
and Laravel server releases were not rerun in this cleanup.

The PHP, JavaScript, and Dart clients reached the Laravel test server at
`127.0.0.1:8001`; each test asserted HTTP 200 from the protected endpoint.
These are automated published-package/live-HTTP tests, not production
deployment tests.

## Local source-package tests (2026-11-26)

| Test type | Command / working directory | Result |
|---|---|---|
| PHP smoke and syntax checks | `php tests/phase13-smoke.php` | Passed |
| CLI administration smoke | `php packages/cli/tests/manual-phase12.php` | Passed |
| Core unit tests | `vendor/bin/phpunit.bat --bootstrap vendor/autoload.php packages/core/tests` | Passed; 9 tests, 20 assertions |
| Laravel Server integration tests | `vendor/bin/phpunit.bat --bootstrap vendor/autoload.php packages/laravel-server/tests/Feature` | Passed; 3 tests, 16 assertions |
| Laravel Client integration tests | `vendor/bin/phpunit.bat --bootstrap vendor/autoload.php packages/laravel-client/tests/Feature` | Passed; 4 tests, 6 assertions |
| JavaScript interoperability | `npm.cmd --prefix packages/javascript test` | Passed |
| Flutter static analysis | `flutter analyze --no-pub` from `packages/flutter` | Passed; no issues |
| Flutter unit/interoperability tests | `flutter test --no-pub` from `packages/flutter` | Passed; 2 tests |

These source-package tests ran against the repository checkout. They are
separate from the published-package tests above.

## Other recorded validation

- The source test report records MySQL 8.0.46 migration, PDO/mysqli repository,
  and Laravel MySQL tests as passed on 2026-11-25. They were not rerun during
  this cleanup because no MySQL listener or test credentials were available.
- The Composer release report records 12 clean Packagist installs of the six
  Composer packages at v1.0.0 (unversioned and `^1.0`) on 2026-11-22. This is
  historical evidence, not a clean-install matrix for all current registry
  versions. Clean npm and pub.dev consumer installs were not run here.
- The source test results above do not imply independent review or production
  readiness.
- No manual production verification is claimed.

## Not run or blocked

| Check | Status |
|---|---|
| Multi-worker replay/concurrency and shared rate limiting | Not run |
| Production HTTPS and transport/application request-size limits | Not run |
| Protected-export interoperability | Blocked; protected export creation remains disabled |
| Android/iOS device-backed secure storage | Not run |
| Independent security review | Not run |
| Complete clean-install matrix for current versions of all registries | Not run |

Browser JavaScript and device-backed secure storage remain outside these
interoperability claims. Passing automated tests does not establish production
readiness.
