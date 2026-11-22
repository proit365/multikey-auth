# Composer Release Audit

**Status:** Local preparation and validation are complete; stable publication is
blocked by outstanding project release gates and the required owner approval.  
**Audit date:** 2026-11-22  
**Repository:** `proit365/multikey-auth`, branch `main`  
**Local HEAD:** `b4fd54311f2c24d4ac651336c16c82c4e3289358`  
**Origin `main`:** `3d19ebfaaca04c7143aafa20a5a30f71ac718941`; two local commits are
ahead and have not been pushed.

No protocol, cryptographic behavior, or public API was changed. The CLI
Composer metadata/docs and release tooling were updated. No remote repository
was modified; no push, stable tag, GitHub Release, or package publication was
performed.

## Current execution summary

- The only confirmed cause of the reported failure remains the absence of
  stable tags: all six Packagist packages expose only `dev-main`, which cannot
  satisfy `^1.0`. Laravel Server also needs stable Server and Core dependencies.
- Local commits `8859a6d` and `b4fd543` prepare the CLI test-extension metadata,
  annotated-tag publishing, and Windows shell line endings.
- All six package manifests pass strict Composer validation. Core, CLI, PHP
  smoke, Laravel Server, and Laravel Client test suites passed.
- All six package subtree previews passed in an isolated clean clone. Each
  distribution `main` is an ancestor of its proposed split commit, so the
  prepared branch updates are fast-forwardable. The PHP client target remains
  `proit365/multikey-auth-php-client`.
- Stable tags were not created. The project release checklist still requires
  integration/concurrency checks, independent security review, protected
  export interoperability, production rate-limiter verification, and
  master-key/secure-export workflow verification.
- No stable Packagist installation was attempted or claimed: the necessary
  versions are not yet published.

## Confirmed cause of the installation failure

The reported `composer require 'proit365/laravel-multikey-auth-server:^1.0'`
failure is explained by registry version metadata, not by an established source
code defect:

- All six names are registered on Packagist.
- The Packagist package detail API currently returns only `dev-main` for each.
- No Git tags are present in any of the six distribution repositories.
- A stable `^1.0` constraint does not match `dev-main`.
- The Laravel server also requires `proit365/multikey-auth-server:^1.0`, which
  requires `proit365/multikey-auth-core:^1.0`; those stable dependency versions
  are also unavailable.

Therefore, adding a Laravel-server tag alone would not satisfy the dependency
graph. Stable versions must be released in dependency order and then indexed by
Packagist. The initial version `1.0.0` remains conditional on package readiness;
no version is assumed or assigned here.

## Repository and registry snapshot

GitHub search confirmed that the six distribution repositories are public and
use `main`. Their current remote refs and Packagist metadata are:

| Local directory | Composer package | Distribution repository | GitHub `main` | Prepared split | Packagist version / source ref | Tags |
|---|---|---|---|---|---|---|
| `packages/core` | `proit365/multikey-auth-core` | `proit365/multikey-auth-core` | `1229a2138889` | `1229a2138889` | `dev-main` / `1229a2138889` | None |
| `packages/server` | `proit365/multikey-auth-server` | `proit365/multikey-auth-server` | `383cd2cf7d27` | `383cd2cf7d27` | `dev-main` / `383cd2cf7d27` | None |
| `packages/client-php` | `proit365/multikey-auth` | `proit365/multikey-auth-php-client` | `4883dc28c28d` | `4883dc28c28d` | `dev-main` / `4883dc28c28d` | None |
| `packages/laravel-server` | `proit365/laravel-multikey-auth-server` | `proit365/laravel-multikey-auth-server` | `1ed3bf13f757` | `1ed3bf13f757` | `dev-main` / `1ed3bf13f757` | None |
| `packages/laravel-client` | `proit365/laravel-multikey-auth` | `proit365/laravel-multikey-auth` | `737c71b39c79` | `737c71b39c79` | `dev-main` / `737c71b39c79` | None |
| `packages/cli` | `proit365/multikey-auth-cli` | `proit365/multikey-auth-cli` | `2af738985b29` | `8bce7df77cad` | `dev-main` / `d6e8792d2bc3` | None |

Packagist’s repository URL matches the intended distribution repository for each
package, including the PHP-client distinction. However, each Packagist
`dev-main` reference currently matches GitHub `main` for five packages. The CLI
Packagist ref (`d6e8792d2bc3`) is stale versus its distribution `main`
(`2af738985b29`); the prepared CLI split is `8bce7df77cad`. This indicates an
update/indexing issue to verify after the approved push. Hook configuration
cannot be verified publicly and was not changed.

Packagist's documented manual GitHub hook, if needed, is configured in each
distribution repository under **Settings → Webhooks → Add webhook**:

- Payload URL: `https://packagist.org/api/github?username=<PACKAGIST_USERNAME>`
- Content type: `application/json`
- Secret: the Packagist API token, entered directly in GitHub and never stored
  in this repository
- Events: **Just the push event**

Prefer the Packagist GitHub application/account sync when available. Packagist
documents this setup at [packagist.org/about#how-to-update-packages](https://packagist.org/about#how-to-update-packages).

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
| Git status, branch, local tags, and `origin/main` | PASS — clean before audit files; `main`; no local tags; local HEAD equals `origin/main`. |
| `composer validate --strict --no-check-publish` for each of the six package manifests | PASS — all six valid. |
| `bash -n tools/release/split-packages.sh` | PASS. |
| `bash tools/release/split-packages.sh --help` | PASS. |
| GitHub distribution refs and tags | PASS — all six `main` refs found; no tags returned. |
| GitHub distribution Composer manifests | PASS — intended names, internal requirements, and autoload mappings observed. |
| Packagist package detail metadata | PASS — all six names registered; only `dev-main` observed; source URLs point to intended repositories. |
| Clean consumer install from stable Packagist versions | NOT RUN — no stable versions are available; this is reserved for Batch 6 after release approval and indexing. |

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
| Annotated release tag | **PASS** — object and push-to-tag-ref/peeling tested against an isolated local bare repository only |
| Split publisher preflight | **PASS** — all six splits from clean clone; safe-path/content checks passed |
| Distribution ancestry | **PASS** — all six remote `main` branches are ancestors of their prepared split commits |
| Stable Packagist versions and clean `^1.0` consumer installs | **BLOCKED** — no stable tags/versions exist; not claimed |
| GitHub push, tag, GitHub Release, Packagist configuration changes | **NOT PERFORMED** — requires remaining release gates and explicit authorization |

No TLS verification was disabled.

## Remaining work and authorization boundary

- **Batch 2:** Completed — CLI test extension metadata and annotated release
  tagging/Windows line-ending safeguards were corrected; previous package
  content fixes were confirmed.
- **Batch 3:** Completed — manifests/tests passed and all six distribution
  split candidates were previewed; no push occurred.
- **Batches 4–6:** Blocked. Complete the outstanding release checklist before
  stable tags, obtain explicit owner approval before any push/tag, wait for
  Packagist indexing, then run all six clean consumer installs.
