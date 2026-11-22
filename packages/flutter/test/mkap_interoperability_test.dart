import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:proit365_multikey_auth/proit365_multikey_auth.dart';

void main() {
  test('published MKAP v1 vector matches Dart implementation', () {
    final vectorPath = _vectorPath();
    final vector = jsonDecode(
      File(vectorPath).readAsStringSync(),
    ) as Map<String, dynamic>;
    final hkdf = vector['hkdf'] as Map<String, dynamic>;
    final proof = vector['proof'] as Map<String, dynamic>;
    final challenge = vector['challenge_fixture'] as Map<String, dynamic>;

    final ikm = Uint8List.fromList(
      _hex(hkdf['ikm_hex'] as String),
    );
    final nonce = Uint8List.fromList(
      _hex(hkdf['salt_hex'] as String),
    );
    final info = Uint8List.fromList(
      _hex(hkdf['info_hex'] as String),
    );
    final derived = Uint8List.fromList(hkdfSha256(ikm, nonce, info, 32));
    expect(_hexString(derived), hkdf['output_hex']);

    final context = hkdfContext(
      1,
      'CLIENT_001',
      Uint8List.fromList(
        _hex(challenge['request_id_hex'] as String),
      ),
      1,
      (proof['key_ids'] as List).cast<String>(),
    );
    expect(_hexString(context), hkdf['info_hex']);

    final bodyHash = Uint8List.fromList(
      _hex(proof['body_sha256_hex'] as String),
    );
    final requestId = Uint8List.fromList(
      _hex(challenge['request_id_hex'] as String),
    );
    final message = proofMessage(
      protocolVersion: 1,
      clientId: 'CLIENT_001',
      requestId: requestId,
      nonce: nonce,
      expiresAt: proof['expires_at'] as int,
      method: proof['http_method'] as String,
      target: proof['request_target'] as String,
      bodySha256: bodyHash,
      poolVersion: proof['pool_version'] as int,
      keyIds: (proof['key_ids'] as List).cast<String>(),
    );
    expect(_hexString(message), proof['message_hex']);
    final hmac = Uint8List.fromList(hmacSha256(derived, message));
    expect(_hexString(hmac), proof['hmac_hex']);
    expect(encodeBase64Url(hmac), proof['hmac_base64url']);
  });

  test('Flutter client uses exact body and returns normal response', () async {
    final vector = jsonDecode(
      File(_vectorPath()).readAsStringSync(),
    ) as Map<String, dynamic>;
    final hkdf = vector['hkdf'] as Map<String, dynamic>;
    final proof = vector['proof'] as Map<String, dynamic>;
    final challenge = vector['challenge_fixture'] as Map<String, dynamic>;
    final ikmHex = hkdf['ikm_hex'] as String;
    final secretHexes = <String>[
      ikmHex.substring(0, 64),
      ikmHex.substring(64, 128),
      ikmHex.substring(128, 192),
    ];
    final secrets = <String, Uint8List>{};
    for (var index = 0; index < (proof['key_ids'] as List).length; index++) {
      secrets[(proof['key_ids'] as List)[index] as String] =
          Uint8List.fromList(_hex(secretHexes[index]));
    }

    final export = MkapExport(
      clientId: 'CLIENT_001',
      poolId: 'POOL_001',
      poolVersion: 1,
      identifierLength: 6,
      identifierAlphabet: 'abcdefghijklmnopqrstuvwxyz0123456789',
      secrets: secrets,
    );
    final config = MkapClientConfig.validate(
      baseUri: 'https://api.example.test',
      clientId: 'CLIENT_001',
      poolVersion: 1,
      identifierLength: 6,
      identifierAlphabet: 'abcdefghijklmnopqrstuvwxyz0123456789',
    );
    final transport = _MockTransport(
      challenge: challenge,
      expiresAt: proof['expires_at'] as int,
    );
    final client = MkapClient(
      config: config,
      export: export,
      transport: transport,
    );

    final response = await client.request(
      proof['http_method'] as String,
      proof['request_target'] as String,
      Uint8List(0),
    );
    expect(response.statusCode, 202);
    expect(transport.requests.length, 2);
    expect(transport.requests[1].body, isEmpty);
    expect(
      transport.requests[1].headers['X-MKAP-Proof'],
      proof['hmac_base64url'],
    );
  });
}

class _MockTransport implements MkapHttpTransport {
  _MockTransport({
    required this.challenge,
    required this.expiresAt,
  });

  final Map<String, dynamic> challenge;
  final int expiresAt;
  final requests = <MkapHttpRequest>[];

  @override
  Future<MkapHttpResponse> send(MkapHttpRequest request) async {
    requests.add(request);
    if (requests.length == 1) {
      return MkapHttpResponse(
        statusCode: 200,
        headers: const <String, String>{'content-type': 'application/json'},
        body: Uint8List.fromList(
          utf8.encode(
            jsonEncode(<String, dynamic>{
              'version': 1,
              'request_id': challenge['request_id_base64url'],
              'nonce': challenge['nonce_base64url'],
              'key_ids': challenge['key_ids'],
              'key_count': challenge['key_count'],
              'id_length': challenge['id_length'],
              'expires_in': 30,
              'expires_at': expiresAt,
            }),
          ),
        ),
      );
    }
    return MkapHttpResponse(
      statusCode: 202,
      headers: const <String, String>{'content-type': 'application/json'},
      body: Uint8List.fromList(utf8.encode('{"accepted":true}')),
    );
  }
}

List<int> _hex(String value) {
  final bytes = <int>[];
  for (var index = 0; index < value.length; index += 2) {
    bytes.add(int.parse(value.substring(index, index + 2), radix: 16));
  }
  return bytes;
}

String _hexString(List<int> bytes) {
  return bytes.map((byte) => byte.toRadixString(16).padLeft(2, '0')).join();
}

String _vectorPath() {
  final candidates = <String>[
    '${Directory.current.path}/specification/test-vectors-v1.json',
    '${Directory.current.path}/../../specification/test-vectors-v1.json',
  ];
  for (final candidate in candidates) {
    if (File(candidate).existsSync()) {
      return candidate;
    }
  }
  throw StateError('Published MKAP test-vector file was not found.');
}
