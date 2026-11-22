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

## Multi-repository distribution readiness

- [ ] Resolve the PHP-client distribution repository collision with the
      authoritative `proit365/multikey-auth` monorepo.
- [ ] Create the five non-conflicting distribution repositories as empty
      repositories; do not seed them with unrelated commits.
- [ ] Commit source changes, use a clean `main` worktree, and validate the
      `composer.dev.json` workspace with TLS verification enabled.
- [ ] Run `php tests/phase13-smoke.php`, the package-specific PHPUnit suites,
      `npm --prefix packages/javascript test`, and Flutter analysis/tests.
- [ ] Verify clean consumer installation and dependency tags after registries
      are available.
- [ ] Preview all six `git subtree split` outputs and inspect package contents
      for secrets, generated files, test databases, local configuration, and
      private planning documents.
- [ ] Release in dependency order: core; server and PHP client; Laravel server
      and CLI; Laravel client.
- [ ] Supply an owner-approved version for each package. Confirm that each
      distribution tag points to the reviewed split commit.
- [ ] Register the Composer repositories on Packagist and verify update hooks
      only after the repositories and first tags exist.
- [ ] Keep JavaScript/npm and Flutter/pub.dev releases separate from Composer
      subtree synchronization.

## Release metadata

- Repository: https://github.com/proit365/multikey-auth
- Product: ProIT365 MultiKey Auth
- Protocol: MKAP v1
- License: MIT
- Development status: not production-ready

See the [local distribution workflow](tools/release/README.md). Its script
does not create repositories or register/publish packages.
