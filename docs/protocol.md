# MKAP v1 Protocol

The canonical specification is [mkap-v1.md](../specification/mkap-v1.md).
Known-answer fixtures are in [test-vectors-v1.json](../specification/test-vectors-v1.json).

Flow:

1. The client hashes the exact intended request body.
2. The client requests a challenge.
3. The server persists a nonce, request identifier, selected key identifiers,
   intended request metadata, and expiry.
4. The client concatenates selected 32-byte secrets in server-provided order.
5. HKDF-SHA256 derives a 32-byte proof key.
6. HMAC-SHA256 signs the canonical proof message.
7. The server verifies the request and atomically consumes the challenge.

Text is UTF-8 and not Unicode-normalized. Canonical integers are big-endian.
No secret key material is returned by the challenge response.
