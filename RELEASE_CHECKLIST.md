# Release Checklist

## Required before any release

- [ ] Resolve the existing PHPUnit/TLS dependency installation blocker.
- [ ] Run live MySQL, mysqli, PDO/SQLite, Laravel, JavaScript HTTP, and
  Flutter HTTP integration suites.
- [ ] Run concurrent challenge-consumption testing.
- [ ] Obtain an independent security review.
- [ ] Resolve protected-export interoperability across all clients.
- [ ] Verify shared production rate limiting.
- [ ] Verify external master-key and secure-export workflows.
- [ ] Confirm package names and repository availability.
- [ ] Verify every installation command in a clean environment.
- [ ] Remove all plaintext test exports and secret values.

## Release metadata

- Repository: https://github.com/proit365/multikey-auth
- Product: ProIT365 MultiKey Auth
- Protocol: MKAP v1
- License: MIT
- Development status: not production-ready
