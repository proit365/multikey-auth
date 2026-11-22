# Package distribution and release workflow

## Source-of-truth policy

The `main` branch of [proit365/multikey-auth](https://github.com/proit365/multikey-auth)
is the only development source of truth. The Composer distribution repositories
are one-way subtree projections of `packages/`; do not develop directly in
them. Make changes in this monorepo, validate them here, then synchronize the
resulting package tree. Contributions found in a distribution repository must
be reviewed and reapplied to this monorepo before the next split.

The split script preserves the history of each package prefix. It does not
rewrite source history or merge work from a distribution repository.

## Repository and package map

| Monorepo prefix | GitHub distribution repository | Composer package |
|---|---|---|
| `packages/core` | `proit365/multikey-auth-core` | `proit365/multikey-auth-core` |
| `packages/server` | `proit365/multikey-auth-server` | `proit365/multikey-auth-server` |
| `packages/client-php` | `proit365/multikey-auth-php-client` | `proit365/multikey-auth` |
| `packages/laravel-server` | `proit365/laravel-multikey-auth-server` | `proit365/laravel-multikey-auth-server` |
| `packages/laravel-client` | `proit365/laravel-multikey-auth` | `proit365/laravel-multikey-auth` |
| `packages/cli` | `proit365/multikey-auth-cli` | `proit365/multikey-auth-cli` |

The PHP-client Composer package name remains `proit365/multikey-auth`; its
distribution repository is `proit365/multikey-auth-php-client`. The latter is
distinct from the authoritative monorepo repository. The distribution
repositories remain one-way projections and must not become development
sources.

The script handles only the six Composer packages. JavaScript remains an npm
package (`@proit365/multikey-auth`) and Flutter remains a pub.dev package
(`proit365_multikey_auth`); neither is subtree-published.

## Local development and tests

The six package manifests contain no local `path` repositories. Local internal
dependencies are configured only in the non-published `composer.dev.json`
workspace manifest.

Bash / Git Bash:

```bash
COMPOSER=composer.dev.json composer install
COMPOSER=composer.dev.json composer test:smoke
```

PowerShell:

```powershell
$env:COMPOSER = 'composer.dev.json'
composer install
composer test:smoke
Remove-Item Env:COMPOSER
```

Once workspace dependencies are installed, package tests can also be run from
the monorepo root:

```bash
vendor/bin/phpunit --bootstrap vendor/autoload.php packages/core/tests
vendor/bin/phpunit --bootstrap vendor/autoload.php packages/laravel-server/tests/Feature
vendor/bin/phpunit --bootstrap vendor/autoload.php packages/laravel-client/tests/Feature
npm --prefix packages/javascript test
(cd packages/flutter && flutter analyze && flutter test)
```

For a distribution-repository checkout, install its declared dependencies and
run `composer test` where the package defines that script. The core known-answer
vectors and CLI SQLite schema are packaged as local test fixtures; the
monorepo smoke suite also checks the CLI schema fixture against the canonical
database migration. Cross-package manual smoke checks are run by the root
`tests/phase13-smoke.php` command, not from an individual split repository.

The root PHP smoke command runs the framework-independent manual checks,
JavaScript interoperability test, and PHP syntax checks:

```bash
php tests/phase13-smoke.php
```

`composer.dev.json` has no lockfile yet. After resolving workspace dependencies,
generate and commit `composer.dev.lock` to pin the development/test environment.
Do not disable Composer TLS verification to work around certificate errors.
Live database, Laravel, HTTP, concurrency, and independent security checks remain
separate release gates listed in [RELEASE_CHECKLIST.md](../../RELEASE_CHECKLIST.md).

## Release order

Release dependencies before their consumers:

1. `core`
2. `server` and `client-php`
3. `laravel-server` and `cli`
4. `laravel-client`

The server and PHP client both depend on core; Laravel server and CLI depend on
server/core; Laravel client depends on the PHP client. Existing constraints are
`^1.0`. No package version or independent-versus-coordinated version policy is
selected here; the release owner must provide the version for each invocation.

## Preview and synchronize

Run from a clean checkout of `main`, after all intended source changes and the
split script itself have been committed. The script validates the prefix tree,
rejects common generated/local/private paths, and scans for common credential
markers. These checks are guardrails, not a complete secret scanner; review
the split contents before publishing.

Preview all package splits without contacting distribution remotes:

```bash
bash tools/release/split-packages.sh --package all
```

Preview one package and its proposed version tag. Replace the placeholder with
the release owner's approved version:

```bash
VERSION='<owner-approved-semver>'
bash tools/release/split-packages.sh --package core --version "$VERSION"
```

After tests pass and the destination repository is confirmed, explicitly push
one package and its `vX.Y.Z` tag:

```bash
VERSION='<owner-approved-semver>'
bash tools/release/split-packages.sh --package core --version "$VERSION" --push
```

The script updates `main` with a normal non-force push. With `--version`, it
checks that an existing remote tag points to the exact split commit; otherwise
it creates the matching tag. It accepts one pushed package per invocation.
Never initialize a distribution repository with a competing README/license
commit: the first subtree push expects an empty repository. A normal push will
reject unrelated or divergent history rather than overwrite it.

After verifying the pushed branch and `vX.Y.Z` tag, create a GitHub Release
from that tag in the package's distribution repository if a release page is
desired. The local script creates tags only; it does not create GitHub Releases.

Before each push:

1. Run the required tests and complete the release checklist.
2. Confirm the package's dependencies have been released and are installable.
3. Inspect the previewed package contents and version.
4. Confirm the GitHub target is the intended distribution repository.
5. Push that package, verify its branch/tag, then proceed to dependents.

## Packagist registration

After a distribution repository exists and its Composer manifest is validated,
register that repository with Packagist and configure its GitHub update hook
using the registry's supported workflow. Packagist derives the package name
from the root `composer.json` and discovers versions from Git tags. Do not put
registry credentials in this repository. This workflow does not register or
publish packages automatically.

## Recovery and retries

- **Tests or preflight fail:** no distribution update occurs. Fix the source in
  the monorepo, commit it, and rerun the preview.
- **Non-fast-forward branch push:** stop. Inspect the destination history and
  recover without force-pushing. Distribution repositories must not become
  independent development sources.
- **Branch push succeeds but tag push fails:** rerun the exact same package and
  version command. The branch push is idempotent; the script checks the remote
  tag and creates it only if absent.
- **Tag exists at another commit:** the script stops before updating the branch.
  Do not move or delete the tag. Investigate the release state and choose a new
  version only after owner review.
- **A multi-package release stops partway:** do not roll back successful
  dependency releases. Record which package/tag completed, retry that package
  if necessary, then continue in dependency order. Each invocation is limited
  to one package to keep recovery explicit.

The six-repository sequence is not a distributed transaction. The release
owner must track per-package completion and avoid releasing dependents until
their required dependency tags are available.

## Automation and external actions

The local script is the publishing path; no GitHub Actions publishing workflow
is required. CI can later run the same non-pushing checks. Creating repositories,
resolving the PHP-client slug conflict, Packagist registration, credentials,
remote tags/releases, and any actual publication require separate owner action.
None was performed during preparation.
