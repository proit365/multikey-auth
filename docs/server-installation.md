# Server Installation

The framework-independent server package is under
`packages/server`. Use its PDO or mysqli key repository and challenge
repository implementations.

Apply the SQLite or MySQL migration in `database/`:

- SQLite: `database/sqlite/001_mkap_tables.sql`
- MySQL: `database/mysql/001_mkap_tables.sql`

A recorded MySQL 8.0.46 validation run on 2026-11-25 passed the migration,
Laravel integration suite, PDO/mysqli key-repository checks, and PDO challenge
repository checks. This cleanup did not rerun those tests because no MySQL
listener or test credentials were available. Multi-worker concurrency remains
unverified.

Configure an external `MasterKeyProviderInterface`. The fixed-window
`FixedWindowRateLimiter` is only suitable for one process; production deployments
need a shared atomic implementation.
