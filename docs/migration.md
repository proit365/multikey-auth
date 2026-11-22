# Migration

## Protocol upgrades

Protocol and export format versions must be accepted explicitly. No field or
encoding should be silently changed. Cross-language known-answer vectors must
pass before a version change.

## Key rotation

1. Add or replace a key.
2. Issue a new export.
3. Deploy the export to the client.
4. Revoke the old key.
5. Retain audit and rotation records.

Revoking before the client has received a replacement export can interrupt
authentication.
