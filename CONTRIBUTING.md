# Contributing

## Principles

- Preserve MKAP v1 canonical encodings.
- Do not implement custom cryptographic primitives.
- Add deterministic known-answer tests for cryptographic changes.
- Never commit plaintext exports or master keys.
- Record security-sensitive deviations outside the repository if project
  planning files are intentionally kept separate.

## Source of truth and package distributions

Develop and review all changes in this monorepo's `main` branch. The Composer
distribution repositories are generated subtree mirrors, not independent
development repositories; do not make direct development changes there. Apply
fixes here and synchronize the package split afterward. See the
[distribution workflow](tools/release/README.md) for mappings, release order,
tagging, and recovery.

Use the root `composer.dev.json` workspace manifest for local internal package
dependencies. Do not add monorepo path repositories to publishable package
manifests.

## Verification

PHP smoke and syntax:

```bash
php tests/phase13-smoke.php
php tests/benchmark_phase13.php
```

For the local Composer workspace:

```bash
COMPOSER=composer.dev.json composer install
COMPOSER=composer.dev.json composer test:smoke
```

JavaScript:

```bash
node packages/javascript/test/interoperability.test.js
```

Flutter:

```bash
cd packages/flutter
flutter analyze
flutter test test/mkap_interoperability_test.dart
```

Live Laravel, MySQL, JavaScript HTTP, Flutter HTTP, and concurrent replay tests
remain environment-blocked until credentials and dependencies are available.
