# MKAP Protocol v1

This document is the canonical protocol specification for MKAP version 1.
It is independent of any language or framework implementation.

## 1. Scope

MKAP is a short-lived, one-time challenge-response protocol for authenticating
HTTP requests. The server selects active public key identifiers. The client uses
corresponding private secret key material locally to generate an HMAC-SHA256
proof bound to the exact intended request.

No secret key material is transmitted. This is a custom protocol and has not
been independently security reviewed.

## 2. Transport and Encoding

- Transport: HTTPS for production use.
- Challenge endpoint: `POST /v1/auth/challenges`.
- All binary values in JSON and HTTP headers use base64url without padding.
- Text fields use UTF-8.
- Text is not Unicode-normalized.
- Integer encodings in canonical binary messages use network byte order.
- A 32-bit unsigned integer is encoded as four bytes, big-endian.
- A 64-bit unsigned integer is encoded as eight bytes, big-endian.
- A length-delimited field is encoded as a 32-bit unsigned byte length followed
  by exactly that many bytes.
- Empty text fields are represented by a length of zero.
- JSON integer values must be within their documented ranges.

All length and integer values are binary, not textual decimal values.

## 3. Fixed Values

- Protocol name: ASCII `MKAP`
- Protocol version: `1`
- Secret key length: 32 bytes
- Nonce length: 32 bytes
- Request identifier length: 16 bytes
- HMAC-SHA256 output length: 32 bytes
- HKDF hash: SHA-256
- HKDF output length: 32 bytes
- Default challenge lifetime: 30 seconds
- Default pool size: 1000
- Default selection count: 3
- Default identifier length: 6 characters
- Default identifier alphabet: `abcdefghijklmnopqrstuvwxyz0123456789`

A pool defines one fixed identifier alphabet and identifier length. The server
must reject configuration containing duplicate identifiers or characters outside
the pool alphabet.

## 4. Identifier Encoding

The challenge transports selected identifiers in one fixed-length concatenated
string named `key_ids`.

For `key_count = n` and `id_length = k`:

1. `key_ids` length must equal `n * k`.
2. Split `key_ids` into exactly `n` segments of exactly `k` bytes.
3. Every character must belong to the pool alphabet.
4. Every identifier must be distinct.

Identifier concatenation is only compact transport encoding. It does not
provide cryptographic protection.

## 5. Challenge Request

The challenge request JSON object has these fields:

```json
{
  "version": 1,
  "client_id": "CLIENT_001",
  "method": "POST",
  "target": "/api/orders?status=pending",
  "body_sha256": "BASE64URL_SHA256"
}
```

Rules:

- `version` must equal `1`.
- `client_id` must contain 1 through 128 UTF-8 bytes and must match a registered
  active client.
- `method` must be an uppercase HTTP token, 1 through 32 bytes.
- `target` must be an origin-form target, 1 through 4096 UTF-8/ASCII bytes.
- `target` must begin with `/`, contain no fragment or absolute URI, and contain
  no ASCII control character or space.
- `body_sha256` must decode to exactly 32 bytes.
- The server must hash the exact body it receives for the challenge request,
  but the challenge itself binds the separately supplied intended API body hash.
- The challenge request body size must be subject to a configured limit.

## 6. Challenge Response

The challenge response JSON object is:

```json
{
  "version": 1,
  "request_id": "BASE64URL_REQUEST_ID",
  "nonce": "BASE64URL_NONCE",
  "key_ids": "a8f31xz71q5bk92p4m",
  "key_count": 3,
  "id_length": 6,
  "expires_in": 30,
  "expires_at": 1893456030
}
```

- `request_id` is 16 random bytes.
- `nonce` is 32 random bytes.
- `key_count` must be 1 through the server-configured maximum and must not exceed
  the active pool size.
- `id_length` must equal the pool's fixed identifier length.
- `expires_in` is the remaining lifetime as a positive integer.
- `expires_at` is the UTC Unix time in seconds when the challenge expires.
- `expires_at` is server-authoritative and must be the value used in proof
  canonicalization.
- The server must persist the challenge before returning it.

The response must never include secret key material.

## 7. Request Headers

An authenticated request includes:

```text
X-MKAP-Version: 1
X-MKAP-Client: CLIENT_001
X-MKAP-Request: BASE64URL_REQUEST_ID
X-MKAP-Proof: BASE64URL_HMAC_SHA256
```

- Header values must contain no surrounding whitespace.
- `X-MKAP-Version` must decode to integer `1`.
- `X-MKAP-Request` must decode to exactly 16 bytes.
- `X-MKAP-Proof` must decode to exactly 32 bytes.
- `X-MKAP-Client` must exactly match the challenge's client identifier.

## 8. HKDF-SHA256 Derivation

The selected secret values are concatenated in server-provided key identifier
order. Every secret is exactly 32 bytes:

```text
IKM = Secret_1 || Secret_2 || ... || Secret_N
```

HKDF-SHA256 follows RFC 5869:

- IKM: concatenated selected secrets
- Salt: raw 32-byte nonce
- Info: canonical HKDF context bytes
- Output: exactly 32 bytes

Canonical HKDF context is:

```text
length_field("MKAP")
|| uint32(protocol_version)
|| length_field(client_id_utf8)
|| length_field(request_id_raw)
|| uint32(pool_version)
|| uint32(key_count)
|| length_field(key_id_1_utf8)
|| ...
|| length_field(key_id_n_utf8)
```

`length_field(value)` means four-byte big-endian byte length plus the exact bytes.
It must not contain JSON or language-dependent serialization.

The implementation must use the raw nonce bytes, not its base64url text.

## 9. Proof Message

The proof HMAC key is the 32-byte HKDF output. The HMAC-SHA256 input is:

```text
uint32(protocol_version)
|| length_field(client_id_utf8)
|| length_field(request_id_raw)
|| length_field(nonce_raw)
|| uint64(expires_at)
|| length_field(http_method_utf8)
|| length_field(request_target_utf8)
|| length_field(body_sha256_raw)
|| uint32(pool_version)
|| uint32(key_count)
|| length_field(key_id_1_utf8)
|| ...
|| length_field(key_id_n_utf8)
```

The proof message contains no protocol name or separators beyond the
length-delimited fields shown above.

The body hash is SHA-256 over the exact request body bytes actually transmitted.
An empty body is represented by the 32-byte SHA-256 digest of zero bytes.

The server reconstructs this message from its stored challenge and the actual
incoming HTTP request. It must not trust client-supplied canonical text.

## 10. Request Binding

A challenge authorizes exactly one request tuple:

- client identifier
- request identifier
- nonce
- protocol version
- HTTP method
- origin-form request target
- exact body SHA-256
- pool identifier and pool version
- ordered key identifiers
- server-issued expiration time

The server must reject mismatches in any field.

## 11. Challenge Lifecycle

1. The server validates the client, method, target, body hash, and protocol
   version.
2. The server applies rate limits.
3. The server generates a unique 16-byte request identifier and unique 32-byte
   nonce.
4. The server securely selects distinct active key identifiers.
5. The server atomically persists the challenge and its intended request metadata.
6. The server returns the challenge response only after persistence succeeds.
7. The client derives and sends the proof for the exact original request.
8. The server loads the challenge and selected key records.
9. The server validates expiry, client, method, target, body, pool, and identifiers.
10. The server verifies HMAC-SHA256 using constant-time comparison.
11. The server atomically consumes the challenge.
12. The request is authorized only after consumption succeeds.

The challenge is valid while `expires_at > server_current_unix_time`. It is
invalid at or after `expires_at`. Client clocks are not trusted.

## 12. Atomic Consumption

Challenge consumption must use a conditional atomic operation equivalent to:

```sql
UPDATE authentication_challenges
SET status = 'consumed', consumed_at = :now
WHERE request_identifier = :request_id
  AND status = 'pending'
  AND expires_at > :now
```

A successful update must affect exactly one row. A concurrent caller that loses
the update must receive replay/conflict behavior and must not authorize the
request. Challenge verification and consume must be transactionally coordinated
when the persistence implementation requires it.

Expired challenges may be deleted or excluded by cleanup jobs. Cleanup must not
change the atomic replay guarantee.

## 13. Status and Error Handling

The following mapping records the behavior of the shipped PHP/Laravel server
adapter. HTTP status codes do not change the proof format. Clients must treat
any non-2xx response as a failed operation.

- `400`: malformed JSON, malformed request fields, unsupported protocol
  versions, or invalid/unsupported challenge metadata.
- `401`: missing or invalid proof, expired challenge, request-binding failure,
  or selected key unavailable for verification.
- `403`: unknown client during challenge issuance.
- `404`: unknown challenge.
- `409`: challenge already consumed or replay detected.
- `413`: request exceeds a configured size limit.
- `429`: challenge or authentication rate limit exceeded.
- `500`: unexpected internal server error.

The current adapter maps invalid metadata to `400`; it does not emit `422`.
Using `422` for valid JSON with invalid or unsupported metadata would require a
separate implementation and compatibility decision, so this documentation
update does not introduce that behavior. Revoked or unavailable selected keys
currently fail proof verification with `401`, rather than revealing key state
with `403`.

Unless a public API explicitly requires more detail, authentication failures
must use a consistent response shape and must not reveal whether a particular
key was correct.

## 14. Security Requirements

Implementations must:

- Use operating-system-backed secure randomness.
- Use constant-time HMAC comparison.
- Reject duplicate identifiers.
- Reject revoked or inactive keys.
- Reject malformed or oversized fields.
- Enforce pool and selection limits.
- Never transmit or log secret key material.
- Apply challenge flooding and authentication rate limits.
- Require HTTPS in production.
- Preserve exact request body bytes.
- Keep protocol implementation independent of individual SDK and framework code.

## 15. Versioning

This specification is MKAP v1. Future versions must not silently reinterpret
v1 fields or encodings. Unsupported versions must be rejected.
