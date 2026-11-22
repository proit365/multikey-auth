# Deployment Guidance

## Before deployment

- Obtain independent security review.
- Use HTTPS for challenge and authenticated API traffic.
- Configure an external 32-byte master-key provider.
- Use a shared, atomic rate limiter.
- Apply request body limits at transport and application boundaries.
- Restrict development exports and remove them after use.
- Configure database timezone and backup/rotation procedures.
- Run live MySQL, Laravel, JavaScript, Flutter, and concurrent replay tests.

## Database

Apply the migration for the selected database, then verify binary and timestamp
handling. SQLite behavior does not prove MySQL behavior.

## Challenges

Use server-issued UTC timestamps, short challenge lifetimes, persistent
challenge storage, and atomic conditional consume operations.

## Secrets

Never log complete key material or exports. Never store browser-side complete
secret pools. Use external secret storage for server master keys.
