# Framework-independent MKAP Server

This package provides challenge issuance, request-bound proof verification,
one-time challenge consumption, client/pool and repository interfaces, and
rate-limiter boundaries. Framework routing and middleware belong in the
framework adapters.

## Installation

Install [`proit365/multikey-auth-server` from Packagist](https://packagist.org/packages/proit365/multikey-auth-server). Its Core dependency is also available on Packagist:

```shell
composer require proit365/multikey-auth-server:^1.0
```

The package requires PHP `^8.2`. The PDO challenge repository additionally
requires `ext-pdo` and a configured PDO driver. The Core package requires
`ext-openssl`.

The package requires the `proit365/multikey-auth-core` package. The monorepo
uses a separate development workspace manifest for local path repositories.
The PDO challenge repository uses the [MySQL schema](https://github.com/proit365/multikey-auth/tree/main/database/mysql)
or [SQLite schema](https://github.com/proit365/multikey-auth/tree/main/database/sqlite)
from the source monorepo.

The included fixed-window rate limiter stores state in process memory and is
for tests or single-process development only. Production deployments must
provide a shared, atomic rate limiter. Configure HTTP request body limits at
the transport boundary as well as the verifier's maximum body size.

Server key selection uses secure random ranks and indexed database lookups; it
does not load the complete key pool into application memory per challenge.
Master encryption keys must be provided through an external secret store.

## Source and support

Project website: [https://www.proit365.com](https://www.proit365.com).

The monorepo is the development source of truth:
[github.com/proit365/multikey-auth](https://github.com/proit365/multikey-auth).
Report issues through its [issue tracker](https://github.com/proit365/multikey-auth/issues).
