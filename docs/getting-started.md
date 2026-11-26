# Getting Started

## Repository

```bash
git clone https://github.com/proit365/multikey-auth.git
cd multikey-auth
```

## Available development checks

```bash
php tests/phase13-smoke.php
php tests/benchmark_phase13.php
node packages/javascript/test/interoperability.test.js
cd packages/flutter
flutter pub get
flutter analyze
flutter test test/mkap_interoperability_test.dart
```

The PHP package manifests validate with Composer. Live Laravel, MySQL,
JavaScript HTTP, Flutter HTTP, and concurrent replay checks have different
statuses; do not treat them as one test category.

The published PHP, JavaScript, and Dart client flows were run against the
Laravel test server on 2026-11-26 and each asserted HTTP 200 from the protected
endpoint. Published package smoke and Laravel integration suites also passed.
See the [interoperability and test matrix](../INTEROPERABILITY.md) for package
versions and per-suite results.

MySQL-backed checks were reported as passing against MySQL 8.0.46 on
2026-11-25, but were not rerun during this cleanup. Multi-worker replay,
production HTTPS/request-limit checks, protected-export interoperability,
device-backed Flutter storage, and independent security review remain
unverified or blocked; successful automated tests do not establish production
readiness.
