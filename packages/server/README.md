# Framework-independent MKAP Server

This package provides challenge issuance, request-bound proof verification,
one-time challenge consumption, client/pool and repository interfaces, and
rate-limiter boundaries. Framework routing and middleware belong in the
framework adapters.

The package requires the `realmatrix/multikey-auth-core` package. A local path
repository is configured for this monorepo. The PDO challenge repository uses
the database schema in `database/mysql` or `database/sqlite`.

The included fixed-window rate limiter stores state in process memory and is
for tests or single-process development only. Production deployments must
provide a shared, atomic rate limiter. Configure HTTP request body limits at
the transport boundary as well as the verifier's maximum body size.

Server key selection uses secure random ranks and indexed database lookups; it
does not load the complete key pool into application memory per challenge.
Master encryption keys must be provided through an external secret store.
