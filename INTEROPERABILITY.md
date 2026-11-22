# MKAP Interoperability Matrix

| Implementation | Protocol encoding | HKDF-SHA256 | HMAC-SHA256 | Challenge parsing | Export validation | Live HTTP |
|---|---|---|---|---|---|---|
| PHP core/client | Published vector | Published vector | Published vector | Dependency-free smoke | Dependency-free smoke | Pending |
| JavaScript/Node | Published vector | Published vector | Published vector | Mock transport smoke | Smoke | Pending PHP network |
| Flutter/Dart | Published vector | Published vector | Published vector | Mock transport smoke | Unit/export test | Pending PHP network |
| Laravel 12 server/client | Adapter source | Shared PHP core | Shared PHP core | Not live-run | Not live-run | Dependencies blocked |

The common fixture is `specification/test-vectors-v1.json`. It covers:

- HKDF-SHA256 context and output
- Canonical proof message bytes
- HMAC-SHA256 output
- Base64URL request identifier, nonce, and proof

No implementation is declared interoperable over live HTTP until the matrix's
live HTTP entries are executed. Browser JavaScript and platform secure storage
remain outside the protocol interoperability claims.
