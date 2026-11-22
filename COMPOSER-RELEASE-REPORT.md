# Composer Release Audit

**Status:** All six stable `v1.0.0` tags are published and indexed by Packagist;
all 12 clean Composer installation checks passed.
**Audit date:** 2026-11-22  
**Repository:** `proit365/multikey-auth`, branch `main`  
**Local HEAD / origin `main`:** `ef90f6b681dbcd8ae7316cf5f2c5f767c6f9dc36`.
The local release-preparation commits are included in the synchronized
monorepo branch. All six distribution repositories now carry annotated
`v1.0.0` tags.

No protocol, cryptographic behavior, or public API was changed. The CLI
Composer metadata/docs and release tooling were updated. The monorepo HEAD is
synchronized with `origin/main`. Packagist exposes `v1.0.0` for all six
packages.

## Current execution summary

- The original failure was caused by the absence of stable tags. All six
  `v1.0.0` releases are now on their intended distribution repositories and
  visible in Packagist metadata.
- Local commits `8859a6d` and `b4fd543` prepare the CLI test-extension metadata,
  annotated-tag publishing, and Windows shell line endings.
- All six package manifests pass strict Composer validation. Core, CLI, PHP
  smoke, Laravel Server, and Laravel Client test suites passed.
- All six package subtree previews passed in an isolated clean clone. Each
  distribution `main` is an ancestor of its proposed split commit, so the
  prepared branch updates are fast-forwardable. The PHP client target remains
  `proit365/multikey-auth-php-client`.
- All six distribution tags and their peeled split commits were verified
  remotely. The Packagist Composer v2 metadata endpoint exposes `v1.0.0` for
  each package with the expected internal dependency constraints.
- All 12 clean consumer installs succeeded using default stable rules:
  unversioned and `^1.0` requirements for each package. A representative
  PSR-4 class from every package loaded through Composer autoload.
- The broader project release checklist still has integration/concurrency,
  independent security review, protected-export interoperability,
  production rate-limiter, and master-key/secure-export workflow items open.

## Confirmed cause of the installation failure

The reported `composer require 'proit365/laravel-multikey-auth-server:^1.0'`
failure was caused by registry version metadata, not by an established source
code defect:

- All six names are registered on Packagist.
- At the time of the failure, no Git tags were present in the six distribution
  repositories, so `^1.0` could not match the only available `dev-main`.
- Laravel Server transitively requires stable Server and Core packages. These
  dependencies were released first and now resolve from Packagist.

All six packages were released in dependency order, and Packagist now indexes
the complete stable dependency graph.

## Repository and registry snapshot

GitHub search confirmed that the six distribution repositories are public and
use `main`. Their current remote refs and Packagist metadata are:

| Local directory | Composer package | Distribution repository | `v1.0.0` peeled commit | Annotated tag object | Packagist |
|---|---|---|---|---|---|
| `packages/core` | `proit365/multikey-auth-core` | `proit365/multikey-auth-core` | `1229a2138889` | `805d8ca1e6d8` | `v1.0.0` |
| `packages/server` | `proit365/multikey-auth-server` | `proit365/multikey-auth-server` | `383cd2cf7d27` | `a5077a9c5137` | `v1.0.0` |
| `packages/client-php` | `proit365/multikey-auth` | `proit365/multikey-auth-php-client` | `4883dc28c28d` | `51bf19cd5fcb` | `v1.0.0` |
| `packages/laravel-server` | `proit365/laravel-multikey-auth-server` | `proit365/laravel-multikey-auth-server` | `1ed3bf13f757` | `6539d393f561` | `v1.0.0` |
| `packages/laravel-client` | `proit365/laravel-multikey-auth` | `proit365/laravel-multikey-auth` | `737c71b39c79` | `13277e50fb84` | `v1.0.0` |
| `packages/cli` | `proit365/multikey-auth-cli` | `proit365/multikey-auth-cli` | `8bce7df77cad` | `ca0cd4050e97` | `v1.0.0` |

The Packagist Composer v2 metadata endpoint exposes stable `v1.0.0` for all six
packages and reports source URLs and references matching the intended
distribution repositories and split commits. This verifies the PHP-client
mapping to `proit365/multikey-auth-php-client`.

All six releases appeared in Packagist metadata after the GitHub tag pushes,
and clean Composer installs resolved them. This demonstrates that Packagist
received the release updates. The private GitHub/Packagist webhook or app
configuration itself is not publicly inspectable, so its exact mechanism was
not independently verified. If future tags stop indexing, the documented
manual hook can be configured in each distribution repository under
**Settings → Webhooks → Add webhook**:

- Payload URL: `https://packagist.org/api/github?username=<PACKAGIST_USERNAME>`
- Content type: `application/json`
- Secret: the Packagist API token, entered directly in GitHub and never stored
  in this repository
- Events: **Just the push event**

Prefer the Packagist GitHub application/account sync when available. Packagist
documents this setup at [packagist.org/about#how-to-update-packages](https://packagist.org/about#how-to-update-packages).

## End-user Composer acceptance tests

I ran both normal requirement forms for each package in separate clean
temporary projects. Each project started with only a name and empty `require`,
used an isolated Composer home, and had no custom repositories or
`minimum-stability` setting (Composer's default is `stable`). Tests used
`--no-interaction --no-progress` only to make the commands non-interactive.

Unversioned commands (each ran in its own clean project):

```sh
composer require proit365/multikey-auth-core --no-interaction --no-progress
composer require proit365/multikey-auth-server --no-interaction --no-progress
composer require proit365/multikey-auth --no-interaction --no-progress
composer require proit365/laravel-multikey-auth-server --no-interaction --no-progress
composer require proit365/laravel-multikey-auth --no-interaction --no-progress
composer require proit365/multikey-auth-cli --no-interaction --no-progress
```

**Observed:** All six exited 0 and installed `v1.0.0`.

Stable-constraint commands (each ran in its own clean project):

```sh
composer require 'proit365/multikey-auth-core:^1.0' --no-interaction --no-progress
composer require 'proit365/multikey-auth-server:^1.0' --no-interaction --no-progress
composer require 'proit365/multikey-auth:^1.0' --no-interaction --no-progress
composer require 'proit365/laravel-multikey-auth-server:^1.0' --no-interaction --no-progress
composer require 'proit365/laravel-multikey-auth:^1.0' --no-interaction --no-progress
composer require 'proit365/multikey-auth-cli:^1.0' --no-interaction --no-progress
```

**Observed:** All six exited 0 and installed `v1.0.0`. Internal stable
dependencies resolved automatically through Packagist. The Laravel Server
package installed Core and Server without additional require commands.

These were actual Packagist resolution attempts, not local path-repository
tests. A representative Composer-autoloaded class was confirmed for each
package after each install form. The exact requested Laravel Server command
`composer require 'proit365/laravel-multikey-auth-server:^1.0'` was also run in
a fresh project without additional flags; it installed Laravel Server,
Server, and Core at `v1.0.0`, and its service provider autoload check passed.

All six unversioned commands and all six `^1.0` commands therefore meet the
stable Composer acceptance check in this environment.

## Manifest and dependency audit

All six local Composer manifests passed
`composer validate --strict --no-check-publish`. Their remote `main`
`composer.json` files were also read; package names, internal runtime
dependencies, and PSR-4 mappings match the local manifests.

| Package | Runtime dependencies and declared platform | Development/test metadata |
|---|---|---|
| Core | PHP `^8.2`, `ext-openssl`; PDO and mysqli are suggestions for optional adapters. | PHPUnit `^11.5`; `composer test`. |
| Server | PHP `^8.2`, Core `^1.0`; PDO is suggested for its PDO-backed repository. | No package-local dev test dependency or Composer test script. |
| PHP client | PHP `^8.2`, Core `^1.0`. | No package-local dev test dependency or Composer test script. |
| Laravel server | PHP `^8.2`, `ext-pdo`, Illuminate cache/console/database/routing/support `^12.0`, Server `^1.0`. | Testbench `^10.0`, PHPUnit `^11.5`; `composer test`. |
| Laravel client | PHP `^8.2`, Illuminate HTTP/support `^12.0`, PHP client `^1.0`. | Testbench `^10.0`, PHPUnit `^11.5`; `composer test`. |
| CLI | PHP `^8.2`, `ext-pdo`, Core `^1.0`, Server `^1.0`. | Manual PHP test script; `ext-pdo_sqlite` is declared for development/tests. |

Dependency graph and safe publication order:

1. Core.
2. Server.
3. PHP client.
4. Laravel server.
5. Laravel client.
6. CLI.

The six manifests do not define monorepo-relative Composer path repositories.
Those are isolated in `composer.dev.json`. All six READMEs use their intended
Composer package names in installation examples. The package manifests declare
PHP `^8.2`; the Laravel packages declare Laravel 12 dependencies, consistent
with their README claims. This audit did not execute a PHP/Laravel compatibility
matrix.

## Release tooling and readiness findings

- `tools/release/split-packages.sh` contains the correct six mappings, including
  `packages/client-php` → `proit365/multikey-auth-php-client`. It requires
  `main` and a clean worktree, supports local previews, and does not force-push.
- The publisher now creates annotated tags, refuses to overwrite a lightweight
  or mismatched tag, and was verified against an isolated bare repository.
- `.gitattributes` pins the release shell script to LF so a Windows clone can
  run it under Bash.
- No tracked GitHub Actions publishing workflow was found.
- The parent `update.bat` stages broadly, commits, pushes the monorepo, then
  pushes all six subtree branches without running tests or version/tag gates.
  It was inspected but not run; do not use it as the release command.
- The Server and PHP-client manifests have no package-local Composer test
  scripts or development test dependencies. Their monorepo-level tests must be
  part of the release gate unless portable package-local tests are added.
- The project instructions require honest security claims. An independent
  security review remains outstanding; publication must not be presented as
  proof of production security or cryptographic audit.
- Prior packaging items (package-root licenses, Core README, CLI installation
  and export wording, package names, and PHP-client repository mapping) were
  confirmed. This continuation also declared the CLI test's SQLite extension
  and corrected its test-install documentation; no runtime behavior changed.

## Validation record

| Check | Result |
|---|---|
| Monorepo HEAD and `origin/main` | PASS — both `ef90f6b681dbcd8ae7316cf5f2c5f767c6f9dc36`; only release report/progress files are modified locally. |
| `composer validate --strict --no-check-publish` for each of the six package manifests | PASS — all six valid. |
| `bash -n tools/release/split-packages.sh` | PASS. |
| `bash tools/release/split-packages.sh --help` | PASS. |
| GitHub distribution `v1.0.0` tags | PASS — all six annotated tags and peeled commits verified with `git ls-remote --tags`. |
| GitHub distribution Composer manifests | PASS — intended names, internal requirements, and autoload mappings observed. |
| Packagist Composer v2 metadata | PASS — all six `v1.0.0` versions and expected dependency metadata observed; source refs match split commits. |
| Packagist update propagation | PASS — each pushed tag appeared in Packagist metadata; underlying webhook/app configuration is not publicly inspectable. |
| Clean consumer installation with default stability (six unversioned and six `^1.0` requirements) | **PASS** — all 12 installs succeeded at `v1.0.0`; autoload checks passed. |

### Current continuation checks

| Check | Result |
|---|---|
| Strict validation of all six package manifests | **PASS** |
| Core PHPUnit | **PASS** — 9 tests, 20 assertions |
| CLI package test | **PASS** — SQLite extension present and declared for dev/tests |
| PHP phase-13 smoke suite | **PASS** — Core, Server, PHP Client, Laravel Server/Client, CLI, and PHP lint checks |
| Laravel Server Testbench | **PASS** — 2 tests, 9 assertions |
| Laravel Client Testbench | **PASS** — 4 tests, 6 assertions |
| Package LICENSE copies | **PASS** — all six match root MIT license and verified copyright text |
| Annotated release tags | **PASS** — six remote tag objects and peeled split commits verified |
| Split publisher | **PASS** — all six distributions updated/tagged through the existing script; no force-push |
| Packagist stable metadata | **PASS** — all six v1.0.0 entries, source refs, and internal requirements verified |
| 12 clean Composer installs and autoload checks | **PASS** — six unversioned and six `^1.0`; all selected versions were `v1.0.0` |
| Exact Laravel Server command | **PASS** — transitive Server/Core install and provider autoload succeeded |
| GitHub Releases / webhook settings | **NOT CREATED/CHANGED** — not needed for Composer tags; exact integration settings are not publicly inspectable |

No TLS verification was disabled.

## Completion and remaining release gates

- **Batch 2:** Completed — packaging metadata/docs and release tooling fixes.
- **Batch 3:** Completed — package validation and distribution split previews.
- **Batch 4:** Completed — six annotated `v1.0.0` tags published in dependency
  order after the user's explicit approval.
- **Batch 5:** Completed — all remote tags and Packagist stable metadata
  verified; Packagist received the tag updates.
- **Batch 6:** Completed — all 12 clean Composer installs and autoload checks
  passed, including the exact Laravel Server `^1.0` command.

The broader release checklist items listed above remain outside the scope of
Composer publication and were not claimed as completed.
