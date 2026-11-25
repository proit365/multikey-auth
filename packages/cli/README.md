# MKAP Administrative CLI

The framework-independent CLI wraps the core and server packages. It uses
`MKAP_CLI_DSN`, `MKAP_CLI_DB_USERNAME`, `MKAP_CLI_DB_PASSWORD`, and
`MKAP_CLI_AUDIT_LOG`.

For database setup, command reference, master-key input, and safety behavior,
see the [Administrative CLI guide in Markdown](docs/guide.md) or
[HTML](docs/guide.html).

## Installation

Install [`proit365/multikey-auth-cli` from Packagist](https://packagist.org/packages/proit365/multikey-auth-cli). Its dependencies are also available on Packagist:

```shell
composer require proit365/multikey-auth-cli:^1.0
vendor/bin/mkap help
```

The package requires PHP `^8.2` and `ext-pdo`. The selected database driver
must also be enabled in PHP. Running the package test script additionally
requires the `ext-pdo_sqlite` development dependency.

Examples:

```shell
vendor/bin/mkap pool list
vendor/bin/mkap client create CLIENT_001 --confirm
vendor/bin/mkap pool create CLIENT_001 POOL_001 --count=1000 --selection=3 --dry-run
vendor/bin/mkap pool generate POOL_001 --confirm
vendor/bin/mkap pool inspect POOL_001
vendor/bin/mkap key revoke POOL_001 a8f31x --confirm
vendor/bin/mkap key rotate POOL_001 a8f31x --confirm
vendor/bin/mkap export create POOL_001 --output=exports/client.json --development-json --confirm
vendor/bin/mkap export validate exports/client.json
vendor/bin/mkap challenge cleanup --dry-run
vendor/bin/mkap audit list --limit=50
```

Destructive operations require `--confirm` and do not run from `--dry-run`.
Secret values are never printed. Master-key values must not be supplied with
`--master-key-hex`; use `MKAP_CLI_MASTER_KEY_HEX` or `--master-key-stdin`.
Only explicitly selected `--development-json` exports are currently supported.
They are plaintext and must remain development-only. Protected export creation
is disabled until every supported client SDK implements authenticated-envelope
opening. Do not use this CLI to create production client exports.

## Tests

From a clean package checkout, install development dependencies and run:

```shell
composer install
composer test
```

The CLI test uses an in-memory SQLite database and the package's schema
fixture.
The published v1.0.1 CLI smoke test passed. This does not certify production
export handling or deployment readiness.

## Source and support

Project website: [https://www.proit365.com](https://www.proit365.com).

The monorepo is the development source of truth:
[github.com/proit365/multikey-auth](https://github.com/proit365/multikey-auth).
Report issues through its [issue tracker](https://github.com/proit365/multikey-auth/issues).
