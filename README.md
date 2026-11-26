# ProIT365 MultiKey Auth

**Dynamic Multi-Key Challenge-Response Authentication for APIs**

*One request. Multiple keys. One cryptographic proof.*

[![Publisher](https://img.shields.io/badge/Publisher-ProIT365-blue)](https://www.proit365.com/)
[![Protocol](https://img.shields.io/badge/Protocol-MKAP%20v1-informational)](#protocol-overview)
[![Development](https://img.shields.io/badge/Status-Under%20Development-orange)](#project-status)

**Project website:** [https://mkap.proit365.com/](https://mkap.proit365.com/)

**ProIT365 MultiKey Auth** is a multi-key challenge-response authentication system designed to protect API requests through dynamically selected cryptographic keys and HMAC-based request verification.

Rather than depending on a single static API secret for every request, MultiKey Auth uses configurable pools of independent secret keys. For each authentication challenge, the server selects multiple keys, and the client uses the corresponding locally stored secrets to generate a cryptographic proof.

The underlying secret keys are never transmitted during authentication.

The project is designed to provide a framework-independent authentication core with integrations for PHP, Laravel, JavaScript, and Flutter.

> **Development status:** MultiKey Auth is under development and is not yet recommended for production use. Published-package and live HTTP tests passed on 2026-11-26. MySQL 8.0.46 checks were reported as passing on 2026-11-25 but were not rerun during this cleanup. Multi-worker/shared-service validation, production deployment checks, protected-export interoperability, and independent security review remain incomplete.

---

## Table of Contents

- [Key Features](#key-features)
- [How It Works](#how-it-works)
- [Protocol Overview](#protocol-overview)
- [Architecture](#architecture)
- [Supported Platforms](#supported-platforms)
- [Usage](#usage)
- [Security](#security)
- [Project Structure](#project-structure)
- [Requirements](#requirements)
- [Installation](#installation)
- [Documentation](#documentation)
- [Project Status](#project-status)
- [Roadmap](#roadmap)
- [Contributing](#contributing)
- [Reporting Security Vulnerabilities](#reporting-security-vulnerabilities)
- [License](#license)
- [About ProIT365](#about-proit365)

---

## Key Features

The project is being designed around the following capabilities:

- **Dynamic multi-key authentication:** Select multiple distinct keys from a configurable client key pool for each challenge.
- **Challenge-response authentication:** Generate a fresh authentication challenge for each protected API request.
- **HMAC-SHA256:** Authenticate requests using cryptographic message authentication codes.
- **HKDF-SHA256:** Derive temporary authentication keys from selected secret material.
- **No secret transmission:** Authenticate without transmitting individual secret keys.
- **Replay protection:** Use unique challenges, expiration, and atomic challenge consumption.
- **Request binding:** Bind authentication proofs to the HTTP method, request target, and body hash.
- **Configurable key pools:** Support configurable credential pool sizes and key selection counts.
- **Key lifecycle management:** Support key generation, revocation, rotation, and export management.
- **Framework-independent core:** Keep the authentication protocol independent of application frameworks.
- **Multiple platform integrations:** Provide consistent authentication functionality across supported languages.

These are project objectives, not a claim that all features are currently implemented.

---

## How It Works

MultiKey Auth is designed around a two-stage authentication process.

### 1. Request an authentication challenge

Before sending a protected API request, the client requests a challenge from the authentication server.

The server:

1. Validates the client identity.
2. Generates a cryptographically random request identifier and nonce.
3. Selects a configurable number of distinct keys from the client's active key pool.
4. Stores the challenge and its associated request context.
5. Returns the challenge information to the client.

### 2. Generate a cryptographic proof

The client:

1. Receives the selected key identifiers.
2. Locates the corresponding secrets in its local credential export.
3. Derives a temporary authentication key using HKDF-SHA256.
4. Generates an HMAC-SHA256 proof over the defined request context.
5. Sends the original API request with its authentication proof.

### 3. Verify the request

The server:

1. Retrieves the stored challenge.
2. Loads the corresponding active secret keys.
3. Independently derives the temporary authentication key.
4. Reconstructs the expected authentication message.
5. Verifies the HMAC proof using a constant-time comparison.
6. Checks expiration and request binding.
7. Atomically consumes the challenge.
8. Authorizes the request only if all verification checks succeed.

The authentication process does not require transmitting the selected secret keys over the network.

---

## Protocol Overview

**Protocol:** Multi-Key Authentication Protocol (MKAP)

**Initial version:** MKAP v1

The proposed cryptographic components are:

| Component | Algorithm / Method |
|---|---|
| Challenge nonce | Cryptographically secure random generation |
| Request identifier | Cryptographically secure random generation |
| Key derivation | HKDF-SHA256 |
| Authentication proof | HMAC-SHA256 |
| Request body fingerprint | SHA-256 |
| Secret key size | 32 bytes (256 bits) |
| Proof size | 32 bytes (256 bits) |
| Transport | HTTPS |

### Example challenge response

The following illustrates the intended challenge response structure:

```json
{
  "version": 1,
  "request_id": "BASE64URL_REQUEST_ID",
  "nonce": "BASE64URL_RANDOM_NONCE",
  "key_ids": "a8f31xz71q5bk92p4m",
  "key_count": 3,
  "id_length": 6,
  "expires_in": 30
}
```

The `key_ids` field contains concatenated fixed-length identifiers. The client uses the configured identifier length and selection count to split the string into individual identifiers.

This compact representation is an encoding choice, not a security mechanism.

The canonical wire format, message encoding, derivation parameters, and
verification rules are defined in the [MKAP v1 specification](specification/mkap-v1.md).

---

## Architecture

MultiKey Auth is designed as a modular system.

| Component | Responsibility |
|---|---|
| Cryptographic Engine | HKDF, HMAC, hashing, and secure random generation |
| Key Generator | Generate independent secret keys and identifiers |
| Key Pool Manager | Create and manage client key pools |
| Key Storage | Securely store and retrieve encrypted secrets |
| Export Manager | Generate and validate client credential exports |
| Challenge Manager | Create, track, expire, and consume challenges |
| Authentication Verifier | Verify proofs and authorize requests |
| Key Rotation Manager | Rotate and revoke credentials |
| Client SDK | Generate proofs and authenticate outgoing requests |
| Server SDK | Verify authenticated requests |
| CLI | Manage clients, pools, exports, and credentials |

The core protocol is intended to remain independent of Laravel and other application frameworks.

---

## Supported Platforms

Live package repositories:

| Platform | Package |
|---|---|
| PHP Core | [`proit365/multikey-auth-core`](https://packagist.org/packages/proit365/multikey-auth-core) |
| PHP Server | [`proit365/multikey-auth-server`](https://packagist.org/packages/proit365/multikey-auth-server) |
| PHP Client | [`proit365/multikey-auth`](https://packagist.org/packages/proit365/multikey-auth) |
| Laravel Server | [`proit365/laravel-multikey-auth-server`](https://packagist.org/packages/proit365/laravel-multikey-auth-server) |
| Laravel Client | [`proit365/laravel-multikey-auth`](https://packagist.org/packages/proit365/laravel-multikey-auth) |
| JavaScript / Node.js | [`@proit365/multikey-auth`](https://www.npmjs.com/package/@proit365/multikey-auth) |
| Flutter / Dart | [`proit365_multikey_auth`](https://pub.dev/packages/proit365_multikey_auth) |
| Command Line | [`proit365/multikey-auth-cli`](https://packagist.org/packages/proit365/multikey-auth-cli) |

These packages are published in their linked registries. See each package's
registry page for the current release and package-specific installation details.

### Browser security

Long-term authentication secrets should not be embedded in browser JavaScript applications. Browser environments cannot reliably protect such secrets from users, extensions, or injected scripts.

For browser-based applications, a server-side Backend-for-Frontend architecture is preferred.

---

## Usage

The PHP client API and its exact request/transport requirements are documented
in the [PHP Client SDK guide](https://github.com/proit365/multikey-auth/tree/main/packages/client-php#usage).
The end-to-end example demonstrates the current client/server request flow.

### Working examples

The project aims to provide complete, executable examples, including:

- PHP client and protected API server.
- Laravel server with authentication middleware.
- Laravel client making authenticated HTTP requests.
- JavaScript / Node.js client and server.
- Flutter client using a local credential export.
- Complete client-server integration demonstration.
- Docker Compose demonstration environment.

Examples will include installation instructions, configuration, test credentials, execution commands, and authentication success and failure scenarios.

---

## Security

Security is a fundamental design requirement.

The implementation is intended to address:

- Secret key confidentiality.
- Secure random generation.
- Challenge expiration.
- Replay protection.
- HTTP method and request target binding.
- Request body integrity.
- Constant-time proof comparison.
- Atomic challenge consumption.
- Key revocation and rotation.
- Secure credential exports.
- Challenge endpoint rate limiting.
- Authentication failure logging without exposing secrets.

### Important security considerations

**HTTPS is mandatory.**

Authentication challenges and protected requests must use secure transport.

**Credential exports are sensitive.**

An attacker who obtains a complete client credential export may be able to impersonate that client. Credential exports require appropriate encryption, access controls, and secure storage.

**Server-side secrets require protection.**

Secret material must be encrypted at rest using authenticated encryption. Encryption keys should be managed separately from the database.

**Key count does not equal cryptographic strength.**

Increasing the number of selected keys does not increase the HMAC-SHA256 output length or automatically improve its security. Security depends on secret entropy, key protection, correct implementation, and the overall threat model.

**Independent security review is essential.**

MultiKey Auth is a custom authentication protocol. It must not be considered production-secure or independently audited until qualified cryptographic reviewers have examined the specification and implementation.

---

## Project Structure

The repository is organized around the public source code, specifications, tests, documentation, and examples.

```text
proit365-multikey-auth/
│
├── .github/
│   └── workflows/
│       ├── publish-flutter.yml
│       └── publish-javascript.yml
├── .gitattributes
├── .gitignore
├── README.md
├── CHANGELOG.md
├── COMPOSER-RELEASE-REPORT.md
├── CONTRIBUTING.md
├── LICENSE
├── SECURITY.md
├── THREAT_MODEL.md
├── KNOWN_LIMITATIONS.md
├── INTEROPERABILITY.md
├── RELEASE_CHECKLIST.md
├── RELEASE_NOTES.md
├── DEPLOYMENT_GUIDANCE.md
├── SEO_METADATA.md
├── llms.txt
├── composer.dev.json
│
├── specification/
│   ├── README.md
│   ├── mkap-v1.md
│   └── test-vectors-v1.json
│
├── packages/
│   ├── cli/
│   ├── client-php/
│   ├── core/
│   ├── server/
│   ├── laravel-server/
│   ├── laravel-client/
│   ├── javascript/
│   └── flutter/
│
├── database/
│   ├── mysql/
│   └── sqlite/
├── docs/
├── examples/
│   └── end-to-end/
├── tests/
└── tools/
    └── release/
```

This tree reflects the tracked repository. The website source and published
package test harness are maintained outside this repository.

---

## Requirements

Requirements depend on the integration being used.

Initial development targets include:

- PHP with strict typing and PSR-4 autoloading.
- Composer for PHP dependency management.
- Laravel for framework-specific integrations.
- Node.js for the JavaScript package.
- Dart and Flutter for mobile integrations.
- A database supporting transactional challenge management.
- Docker and Docker Compose for reproducible demonstrations.

Exact runtime constraints are defined in each package manifest and its
package-specific documentation.

---

## Installation

For installation instructions, requirements, and current releases, use the
registry link for your platform in [Supported Platforms](#supported-platforms).

To obtain the source code:

```bash
git clone https://github.com/proit365/multikey-auth

cd proit365-multikey-auth
```

The repository is currently under development. Production installation and usage are not yet recommended.

---

## Documentation

The repository includes:

- The [canonical MKAP v1 specification](specification/mkap-v1.md) and shared
  [protocol test vectors](specification/test-vectors-v1.json).
- Architecture, cryptography, security, database, installation, and API guides
  under [`docs/`](docs/).
- Package-specific READMEs and guides under [`packages/`](packages/).
- The [interoperability and test matrix](INTEROPERABILITY.md), examples, and
  release documentation.

Package requirements and commands are documented with each package.

---

## Project Status

<badge color="warning">Under Development</badge>

MultiKey Auth is being developed incrementally. The test results below establish
tested behavior, not production readiness.

| Area | Status |
|---|---|
| Architecture | Under development |
| Protocol specification | Under development |
| Cryptographic implementation | Published in packages; under development |
| Key pool management | Published in packages; under development |
| PHP SDK | Published packages; under development |
| Laravel integration | Published packages; under development |
| JavaScript SDK | Published package; under development |
| Flutter SDK | Published package; under development |
| CLI tools | Published package; under development |
| Working examples | Development example available; not production configured |
| Automated package and framework tests | Published-package and local suites passed; see [test status](INTEROPERABILITY.md) |
| MySQL integration tests | Reported passed on 2026-11-25; not rerun in this cleanup |
| Independent security review | Pending |
| Production release | Not available |

### Current validation

On 2026-11-26, the published PHP, Laravel, Node.js, and Dart client flows
passed against the local Laravel test server; the protected endpoint returned
HTTP 200. The tested versions and automated suite counts are in the
[interoperability matrix](INTEROPERABILITY.md). Local Core PHPUnit (9 tests,
20 assertions), Laravel server Testbench (3 tests, 16 assertions), Laravel
client Testbench (4 tests, 6 assertions), JavaScript tests, Flutter analysis
and tests, PHP smoke/lint checks, and CLI administration tests also passed.

A separate recorded test run reports MySQL 8.0.46 migration and PDO/mysqli
repository checks passing on 2026-11-25. They were not rerun in this cleanup
because no MySQL listener or test credentials were available.

These results do not replace multi-worker replay and shared-rate-limiter tests,
production HTTPS and request-limit validation, device-backed Flutter storage
tests, protected-export interoperability, or independent security review.
Do not treat the current validation as production certification.

---

## Roadmap

The roadmap below records project workstreams; several listed components have
already been implemented and published. See the status table above and the
test matrix for current evidence.

1. Protocol specification and security design.
2. Core cryptographic implementation.
3. Key generation and key pool management.
4. Secure storage and credential exports.
5. Challenge management.
6. Authentication verification.
7. PHP client and server SDKs.
8. Laravel integrations.
9. JavaScript / Node.js SDK.
10. Flutter SDK.
11. CLI tools.
12. Working examples and Docker demonstration.
13. Ongoing automated testing, interoperability, and release validation.
14. Security review and performance optimization.
15. Documentation and release preparation.

The roadmap may evolve as development and security reviews identify additional requirements.

---

## Contributing

Contributions are welcome.

Before contributing:

1. Read the protocol specification and architecture documentation.
2. Review the existing implementation.
3. Follow the coding standards of the relevant package.
4. Include automated tests for new functionality.
5. Avoid undocumented protocol changes.
6. Never commit production credentials, private keys, or real credential exports.

Changes involving cryptographic operations or protocol behavior require particular care and documented review.

---

## Reporting Security Vulnerabilities

Please do not disclose security vulnerabilities through public GitHub issues.

Use the private reporting process documented in [SECURITY.md](SECURITY.md).

Do not include real client secrets, credential exports, or sensitive production data in reports.

---

## License

This project is distributed under the MIT License. See [LICENSE](LICENSE).

---

## About ProIT365

**ProIT365**

Professional AI Automation · Software Development · Server Solutions

Project website: [https://mkap.proit365.com/](https://mkap.proit365.com/)

Main website: [https://www.proit365.com/](https://www.proit365.com/)

GitHub: [https://github.com/proit365/multikey-auth](https://github.com/proit365/multikey-auth)

**ProIT365 MultiKey Auth**

Dynamic Multi-Key Challenge-Response Authentication for APIs.

*One request. Multiple keys. One cryptographic proof.*