# Contributing

## Principles

- Preserve MKAP v1 canonical encodings.
- Do not implement custom cryptographic primitives.
- Add deterministic known-answer tests for cryptographic changes.
- Never commit plaintext exports or master keys.
- Record security-sensitive deviations outside the repository if project
  planning files are intentionally kept separate.

## Verification

PHP smoke and syntax:

```bash
php tests/phase13-smoke.php
php tests/benchmark_phase13.php
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
