# Cryptography

MKAP v1 uses standard algorithms:

- SHA-256 for exact request body hashes and HKDF/HMAC internals.
- HKDF-SHA256 for temporary proof-key derivation.
- HMAC-SHA256 for request proofs.
- AES-256-GCM for server-side secret and protected-export envelopes.

The implementation does not define custom cryptographic primitives.
Constant-time proof verification is used where the core HMAC implementation
supports it.

The protocol custom design has not received an independent security review.
