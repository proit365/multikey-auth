# Release Notes - 1.0.0-development

## Included

- MKAP v1 specification and test vectors
- Framework-independent PHP core
- Key pool generation and lifecycle
- AES-256-GCM server-side secret storage
- PDO and mysqli repository implementations
- SQLite and MySQL migrations
- Challenge issuance, verification, replay prevention, and request binding
- PHP client, Laravel server/client, JavaScript/Node, and Flutter SDKs
- Administrative CLI
- Documentation, threat model, interoperability matrix, and benchmark

## Status

This is not a production release. Independent security review and live
database/framework/cross-language/concurrency validation remain mandatory.

Protected exports are disabled until client loaders implement authenticated
envelope opening.
