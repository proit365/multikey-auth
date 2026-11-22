# MySQL Storage Schema

`001_mkap_tables.sql` contains the default MySQL/MariaDB schema for MKAP.
The schema is framework-independent SQL and may be adapted by framework
migrations.

The master encryption key is never stored in these tables. Applications must
provide it from an external secret store and track key versions.

The core package provides `PdoKeyRepository` and `MysqliKeyRepository` adapters
for the same `KeyRepositoryInterface`. Applications may choose either adapter;
both support the same MySQL pool lookup and revocation operations.

The migration uses foreign keys and unique constraints for client, pool, key,
export, batch, challenge, and rotation records. Large-pool key retrieval must
use indexed pool and identifier predicates rather than table scans.
