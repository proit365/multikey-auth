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

Challenge consume uses a conditional status-and-expiry update. Live MySQL and
concurrent-consume behavior was not executed in this environment.
