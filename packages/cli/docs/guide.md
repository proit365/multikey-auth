# Administrative CLI Guide

`proit365/multikey-auth-cli` provides the `mkap` command for client and pool
administration, key lifecycle operations, exports, challenge cleanup, and
audit listing. It uses PDO and the MKAP database schema; it does not create
the schema or configure a web server.

## Requirements and installation

- PHP `^8.2`
- `ext-pdo` and a PDO driver for the selected database
- `proit365/multikey-auth-core:^1.0`
- `proit365/multikey-auth-server:^1.0`
- `ext-pdo_sqlite` for the package's SQLite-based development test

Install it in the application:

```shell
composer require proit365/multikey-auth-cli:^1.0
vendor/bin/mkap help
```

Composer installs the `mkap` executable as `vendor/bin/mkap`. In a
single-project deployment, configure a restricted database account for the
CLI and run commands from the application's deployment environment.

## Database configuration

The executable reads these environment variables:

| Variable | Purpose |
|---|---|
| `MKAP_CLI_DSN` | PDO data-source name |
| `MKAP_CLI_DB_USERNAME` | Optional database username |
| `MKAP_CLI_DB_PASSWORD` | Optional database password |
| `MKAP_CLI_AUDIT_LOG` | Optional append-only JSON-lines audit-log path |
| `MKAP_CLI_MASTER_KEY_HEX` | 64 hexadecimal characters encoding a 32-byte master key |

Examples:

```text
MKAP_CLI_DSN=mysql:host=127.0.0.1;dbname=mkap;charset=utf8mb4
MKAP_CLI_DB_USERNAME=mkap_admin
MKAP_CLI_DB_PASSWORD=<provided by the deployment secret manager>
```

For SQLite, use a DSN such as `sqlite:/secure/path/mkap.sqlite`. Apply the MKAP
schema before using database commands. The schema is supplied by the Laravel
server package migration or the
[monorepo MySQL/SQLite schema files](https://github.com/proit365/multikey-auth/tree/main/database).
Use database credentials with only the permissions required for the selected
administrative tasks.

The CLI can also read a 32-byte hexadecimal master key from standard input
with `--master-key-stdin`. Do not pass master-key material on a command line:
`--master-key-hex` is intentionally rejected. Prefer a secret manager or
protected input channel for production. Environment variables are inherited by
child processes and may be exposed through process inspection, crash
diagnostics, or environment dumps; use them only when the deployment can
protect the process environment. Never commit the environment value or write
it to logs.

## Command reference

Use `mkap help` to print the supported command list.

### Clients

```text
mkap client create <client_id> [--name=<name>] [--dry-run] [--confirm]
mkap client inspect <client_id>
mkap client revoke <client_id> [--dry-run] [--confirm]
```

`create` defaults the display name to the client identifier. `inspect` prints
client metadata. `revoke` disables an active client. Create and revoke require
`--confirm` unless `--dry-run` is supplied.

### Pools

```text
mkap pool create <client_id> <pool_id> [--count=1000] [--selection=3]
  [--pool-version=1] [--identifier-length=6] [--alphabet=<characters>]
  [--dry-run] [--confirm]
mkap pool inspect <pool_id>
mkap pool list
mkap pool revoke <pool_id> [--dry-run] [--confirm]
mkap pool generate <pool_id> [--pool-version=1] [--master-key-stdin]
  [--dry-run] [--confirm]
```

`pool create` records metadata; it does not create secret keys. `pool generate`
creates the configured number of random keys and encrypts them with the
external master key. Supported pool counts are 1–1,000,000, selection count
must be between 1 and key count, pool version must be positive, and identifier
length must be 1–256.

Example:

```shell
mkap pool create CLIENT_001 POOL_001 --count=1000 --selection=3 --confirm
mkap pool generate POOL_001 --pool-version=1 --confirm --master-key-stdin
```

For the second command, provide the 64-character hexadecimal master key on
standard input using the deployment's protected secret-input mechanism or
retrieve it from an external secret manager. Avoid putting the key in an
environment variable for production deployments.
Secret bytes are never printed.

### Keys

```text
mkap key inspect <pool_id> <identifier>
mkap key revoke <pool_id> <identifier> [--dry-run] [--confirm]
mkap key rotate <pool_id> <identifier> [--master-key-stdin] [--dry-run]
  [--confirm] [--reason=<text>]
```

Inspection reports public metadata and status, not the encrypted secret.
Revocation stops use of the active key. Rotation transactionally revokes the
old key and creates a replacement; issue and securely distribute a new client
export afterward. Both operations require `--confirm` unless `--dry-run` is
used. Rotation records a reason, defaulting to `CLI key replacement`.

### Exports

```text
mkap export create <pool_id> --output=<path> [--master-key-stdin]
  [--development-json] [--dry-run] [--confirm]
mkap export validate <path>
```

The currently supported export is plaintext JSON selected with
`--development-json`. Protected export opening is not yet supported by every
client, so protected export creation is disabled. Plaintext exports contain
all active client secrets and are development-only; do not use them for
production distribution. The command requires `--confirm`, writes atomically
with restrictive file permissions where supported, and never prints secret
values. Protect the destination directory and securely transfer and remove
the export when it is no longer needed.

`export validate` checks the manifest and reports non-secret metadata. It
accepts files up to 64 MiB.

### Challenge cleanup and audit

```text
mkap challenge cleanup [--dry-run] [--confirm]
mkap audit list [--limit=50]
```

Cleanup removes expired challenges that do not have retained audit records.
Use `--dry-run` to count eligible rows first; a real cleanup requires
`--confirm`. The audit-list limit must be from 1 to 1,000. Database commands
write an audit event to standard output; set `MKAP_CLI_AUDIT_LOG` to also
append JSON-lines records to a file.

## Safety behavior

- Database access is required for client, pool, key, challenge, and audit
  operations. `help` and `export validate` do not require a database.
- Mutating operations require `--confirm`; use `--dry-run` to preview supported
  mutations. `--dry-run` does not replace review of the target identifiers.
- Pool creation and key rotation/revocation change authentication state. Check
  the pool/client identifiers and coordinate export rotation before execution.
- The CLI never prints key secret values. Avoid placing secrets in shell
  history, process arguments, audit metadata, or diagnostic output.
- Use a separate privileged administrative account and monitor the audit log.

The CLI is an administrative utility, not a substitute for backups, change
approval, secret-management controls, or production-readiness review.

## Testing and support

Run package tests with:

```shell
composer install
composer test
```

The test script uses an in-memory SQLite database. For protocol and deployment
guidance, consult the
[monorepo documentation](https://github.com/proit365/multikey-auth/tree/main/docs)
and the [project issue tracker](https://github.com/proit365/multikey-auth/issues).
