# Threat Model

## Assets

- 32-byte client key secrets
- 32-byte server master key
- Challenge nonce and request identifier
- Pool/client metadata
- Challenge and audit records
- Export files

## Trust Boundaries

- Client/export storage
- Server process and external master-key provider
- Database and challenge repository
- HTTP transport and application body serialization
- Administrative CLI and operator environment

## Threats and Controls

| Threat | Existing control | Residual risk |
|---|---|---|
| Stolen export | Export validation, rotation/revocation, restrictive file intent | Export compromise permits client impersonation |
| Master-key compromise | AES-GCM encrypted server secrets, external provider interface | Provider implementation and key rotation require deployment validation |
| Replay | Short-lived challenge, pending status, conditional consume | Distributed concurrency requires live database testing |
| Request modification | Proof binds method, target, body hash, identifiers, nonce, and expiry | Transport and middleware must preserve exact request bytes |
| Challenge flooding | Configurable challenge rate limit | Shared atomic limiter required across instances |
| Database compromise | Server secrets remain encrypted; protocol exposes no plaintext secrets | Operational metadata and encrypted records remain sensitive |
| Malformed input | Strict field, size, JSON, and identifier validation | Fuzzing and cross-language malformed-input testing remain pending |
| Administrative misuse | Confirm/dry-run controls, audit log, no secret terminal output | Deployment-specific secret-manager and process-safety review remains |
| Browser/device compromise | Browser and mobile storage limitations are documented | Complete pool secrets cannot provide hardware-backed guarantees |

## Security Requirements

- Independent security review is mandatory before production use.
- Live MySQL, Laravel, JavaScript HTTP, Flutter HTTP, and concurrent consume
  tests are required.
- HTTPS, external master-key storage, shared atomic rate limiting, restrictive
  export permissions, and audit review are deployment requirements.
