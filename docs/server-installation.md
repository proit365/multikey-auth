# Server Installation

The framework-independent server package is under
`packages/server`. Use its PDO or mysqli key repository and challenge
repository implementations.

Apply the SQLite or MySQL migration in `database/`:

- SQLite: `database/sqlite/001_mkap_tables.sql`
- MySQL: `database/mysql/001_mkap_tables.sql`

Live MySQL migration execution and credentials were not validated in this
environment.

Configure an external `MasterKeyProviderInterface`. The fixed-window
`FixedWindowRateLimiter` is only suitable for one process; production deployments
need a shared atomic implementation.
