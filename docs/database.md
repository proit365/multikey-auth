# Database

SQLite and MySQL migrations are provided under `database/`.

Key tables:

- `mkap_clients`
- `mkap_key_pools`
- `mkap_authentication_keys`
- `mkap_key_exports`
- `mkap_key_generation_batches`
- `mkap_authentication_challenges`
- `mkap_authentication_logs`
- `mkap_key_rotations`

Challenge consume uses a conditional status-and-expiry update. A recorded
MySQL 8.0.46 run on 2026-11-25 passed the Laravel migration, Laravel tests, PDO
and mysqli key-repository checks, and PDO challenge-repository checks. These
checks were not rerun during this cleanup because no MySQL listener or test
credentials were available. Multi-worker concurrent consumption remains
unverified; SQLite tests do not satisfy that deployment gate.

See the [test-status matrix](../INTEROPERABILITY.md) for the distinction
between recorded MySQL results and checks rerun in the current validation.
