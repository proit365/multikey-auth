# Flutter SDK

Package: `proit365_multikey_auth`

```dart
final export = MkapExport.fromJson(await readExport());
final config = MkapClientConfig.validate(
  baseUri: 'https://api.example.test',
  clientId: 'CLIENT_001',
  poolVersion: 1,
  identifierLength: 6,
  identifierAlphabet: 'abcdefghijklmnopqrstuvwxyz0123456789',
);
final client = MkapClient(
  config: config,
  export: export,
  transport: HttpPackageMkapTransport(),
);
```

The Dart tests and `flutter analyze` pass. Platform secure storage remains an
adapter contract; no hardware-backed protection is claimed.
