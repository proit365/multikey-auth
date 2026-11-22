# Key Management

Each pool has a public identifier, version, identifier length, identifier
alphabet, secret length, key count, and selection count. Public identifiers
can be transmitted; 32-byte secret values must not.

The implementation supports active/revoked status, individual key revocation,
key replacement, pool replacement/rotation history, and key export.

An export compromise permits a client to generate proofs for that client.
Rotation and revocation do not retroactively protect a stolen export.
