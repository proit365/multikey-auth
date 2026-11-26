# Release Checklist

## Validation snapshot

Published-package and local automated tests were run on 2026-11-26. These
results are development verification, not production certification.

### Passed in the current validation

- [x] Published Core v1.0.1, Server v1.0.1, CLI v1.0.1, and PHP Client v1.0.2
      smoke tests.
- [x] Published Laravel Server v1.0.2 suite (4 tests, 9 assertions) and
      Laravel Client v1.0.1 suite (5 tests, 9 assertions).
- [x] Published PHP, JavaScript v1.0.2, and Dart/Flutter v1.0.3 client flows
      reached the Laravel test server and asserted HTTP 200 from the protected
      endpoint.
- [x] Published Dart package analysis; no issues found.
- [x] Local Core PHPUnit (9 tests, 20 assertions), Laravel Server Testbench
      (3 tests, 16 assertions), Laravel Client Testbench (4 tests, 6 assertions),
      JavaScript interoperability, Flutter analysis/tests, PHP smoke/lint, and
      CLI administration tests.

See [INTEROPERABILITY.md](INTEROPERABILITY.md) for the exact published package
versions and test categories.

### Prior recorded results, not rerun here

- MySQL 8.0.46 migration, Laravel tests, PDO/mysqli key repositories, and PDO
  challenge repository were reported as passing on 2026-11-25. They were not
  rerun in this cleanup because no MySQL listener or test credentials were
  available.
- Twelve clean Packagist installations of the six Composer packages at v1.0.0
  (unversioned and `^1.0`) were reported as passing on 2026-11-22. This is not
  a clean-install matrix for current versions of all registries.

## Open production-readiness gates

- [ ] Run multi-worker concurrent challenge-consumption tests against a shared
      challenge store.
- [ ] Verify shared atomic rate limiting across workers.
- [ ] Validate production HTTPS, proxy behavior, and request-size limits.
- [ ] Complete protected-export interoperability across supported clients.
- [ ] Test Flutter secure-storage adapters on supported Android and iOS devices.
- [ ] Obtain an independent security review from qualified reviewers outside
      the implementation team.
- [ ] Verify clean installs of current npm/pub.dev versions and a current
      all-registry clean-install matrix.
- [ ] Review secret-handling and export-removal procedures for the target
      deployment.

Passing automated tests do not establish production readiness. Do not treat
loopback HTTP, SQLite, single-process tests, or package publication as evidence
that these open gates have passed.

## Future package release procedure

- [ ] Review the package diff and owner-approved version.
- [ ] Run the relevant source tests and required release checks.
- [ ] Verify the package's dependencies and published registry state.
- [ ] Preview the package subtree or registry archive and inspect it for
      secrets, local configuration, and generated files.
- [ ] Release Composer packages in dependency order: Core; Server and PHP
      Client; Laravel Server and CLI; Laravel Client.
- [ ] Keep JavaScript/npm and Flutter/pub.dev releases separate from Composer
      subtree synchronization.

A version tag or package publication does not close any of the production
readiness gates listed above.

## Release metadata

- Repository: https://github.com/proit365/multikey-auth
- Product: ProIT365 MultiKey Auth
- Protocol: MKAP v1
- License: MIT
- Development status: not production-ready

See the [local distribution workflow](tools/release/README.md).
