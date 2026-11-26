# Flutter SDK

`proit365_multikey_auth` provides an MKAP v1 client for Flutter and server-side
Dart applications. It uses maintained `crypto` and `http` packages and has
replaceable secure-storage and HTTP transport interfaces.

```dart
import 'package:proit365_multikey_auth/proit365_multikey_auth.dart';

final export = MkapExport.fromJson(await secureStorage.readExport());
final config = MkapClientConfig.validate(
  baseUri: 'https://api.example.test',
  clientId: 'CLIENT_001',
  poolVersion: 1,
  identifierLength: 6,
  identifierAlphabet: 'abcdefghijklmnopqrstuvwxyz0123456789',
  requireHttps: true,
);
final client = MkapClient(
  config: config,
  export: export,
  transport: HttpPackageMkapTransport(requireHttps: true),
);

final response = await client.request(
  'POST',
  '/api/orders',
  Uint8List.fromList(utf8.encode('{"status":"pending"}')),
);
```

The package never serializes a payload for the application. Pass the exact
`Uint8List` bytes that the transport sends. Challenge failures throw
`MkapChallengeException`; malformed or oversized challenge responses throw
`MkapChallengeFormatException`. API responses, including 401 responses, are
returned as `MkapHttpResponse` and are not replayed.

## Secure storage

`MkapSecureStorage` is a replaceable adapter. `InMemoryMkapStorage` is suitable
only for tests and development. Production Flutter applications should provide
a platform plugin such as `flutter_secure_storage`:

```dart
class FlutterSecureStorageAdapter implements MkapSecureStorage {
  // Implement write/read/delete using the platform plugin.
}
```

The adapter itself does not imply hardware-backed protection. Android Keystore
and iOS Keychain behavior depends on platform, device, OS, and configuration.
Device compromise or application compromise can expose the complete pool.
Do not place browser JavaScript or desktop logs in the secure-store trust
boundary.

## Tests

The historically published v1.0.3 package passed `dart test` with two tests, including a
live challenge-response request that received HTTP 200 from the loopback
Laravel test server; `dart analyze` also passed. Hardware-backed storage has
not been tested on Android or iOS devices.

## Project

Published package: [proit365_multikey_auth on pub.dev](https://pub.dev/packages/proit365_multikey_auth).

Visit [www.proit365.com](https://www.proit365.com/). The authoritative source,
protocol specification, and other SDKs are in the
[ProIT365 MultiKey Auth monorepo](https://github.com/proit365/multikey-auth).
