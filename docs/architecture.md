# Architecture

The implementation is layered:

1. `packages/core`: protocol, cryptography, key lifecycle, exports, and storage
   interfaces.
2. `packages/server`: challenge issuance, verification, replay prevention,
   challenge repositories, and rate-limit interfaces.
3. `packages/client-php`: framework-independent PHP client.
4. `packages/laravel-server` and `packages/laravel-client`: Laravel adapters.
5. `packages/javascript`: server-side Node.js client.
6. `packages/flutter`: Dart/Flutter client.
7. `packages/cli`: framework-independent administrative operations.

Database implementations use `PDO` or `mysqli` key repositories and
replaceable interfaces. HTTP transports are replaceable. Framework adapters
must not implement protocol cryptography separately.
