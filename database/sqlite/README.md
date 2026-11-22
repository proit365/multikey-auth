# SQLite Storage Schema

`001_mkap_tables.sql` defines the SQLite schema. Apply it using PDO with the
`pdo_sqlite` extension enabled. Foreign key enforcement is enabled by the
migration; applications should also execute `PRAGMA foreign_keys = ON` for
each new connection.

Use the core package's `PdoKeyRepository` with a SQLite PDO connection. SQLite
support is intended for local development, tests, and appropriately sized
single-instance deployments. For concurrent multi-instance production use,
evaluate MySQL/MariaDB or another database designed for that workload.

Master encryption keys remain external to the database.
