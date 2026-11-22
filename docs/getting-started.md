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
JavaScript HTTP, Flutter HTTP, and concurrent replay tests require
dependencies and credentials not available in this environment.
