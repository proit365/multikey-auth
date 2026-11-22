import 'dart:convert';
import 'dart:typed_data';

import 'package:crypto/crypto.dart' as crypto;

import 'cryptography.dart';
import 'encoding.dart';
import 'export.dart';
import 'transport.dart';

class MkapClientConfig {
  MkapClientConfig({
    required this.baseUri,
    required this.clientId,
    required this.poolVersion,
    required this.identifierLength,
    required this.identifierAlphabet,
    this.maximumChallengeResponseBytes = 65536,
    this.requireHttps = true,
  });

  final Uri baseUri;
  final String clientId;
  final int poolVersion;
  final int identifierLength;
  final String identifierAlphabet;
  final int maximumChallengeResponseBytes;
  final bool requireHttps;

  factory MkapClientConfig.validate({
    required String baseUri,
    required String clientId,
    required int poolVersion,
    required int identifierLength,
    required String identifierAlphabet,
    int maximumChallengeResponseBytes = 65536,
    bool requireHttps = true,
  }) {
    late Uri uri;
    try {
      uri = Uri.parse(baseUri);
    } on FormatException {
      throw ArgumentError('Base URI is invalid.');
    }
    if (!uri.isAbsolute ||
        !{'http', 'https'}.contains(uri.scheme) ||
        uri.userInfo.isNotEmpty ||
        uri.hasQuery ||
        uri.fragment.isNotEmpty ||
        (uri.path.isNotEmpty && uri.path != '/')) {
      throw ArgumentError(
        'Base URI must be an origin with no credentials, path, query, or fragment.',
      );
    }
    if (requireHttps && uri.scheme != 'https') {
      throw ArgumentError('HTTPS is required.');
    }
    final clientBytes = utf8.encode(clientId);
    if (clientBytes.isEmpty || clientBytes.length > 128) {
      throw ArgumentError('Invalid client identifier.');
    }
    if (poolVersion < 1) {
      throw ArgumentError('Pool version must be positive.');
    }
    if (identifierLength < 1 || identifierLength > 256) {
      throw ArgumentError('Identifier length is outside the supported range.');
    }
    if (identifierAlphabet.isEmpty ||
        identifierAlphabet.runes.toSet().length !=
            identifierAlphabet.runes.length) {
      throw ArgumentError('Identifier alphabet is invalid.');
    }
    if (maximumChallengeResponseBytes < 1 ||
        maximumChallengeResponseBytes > 16777216) {
      throw ArgumentError('Maximum challenge response size is invalid.');
    }

    return MkapClientConfig(
      baseUri: uri,
      clientId: clientId,
      poolVersion: poolVersion,
      identifierLength: identifierLength,
      identifierAlphabet: identifierAlphabet,
      maximumChallengeResponseBytes: maximumChallengeResponseBytes,
      requireHttps: requireHttps,
    );
  }
}

class MkapChallenge {
  MkapChallenge({
    required this.requestId,
    required this.nonce,
    required this.keyIds,
    required this.expiresAt,
  });

  final Uint8List requestId;
  final Uint8List nonce;
  final List<String> keyIds;
  final int expiresAt;
}

class MkapClient {
  MkapClient({
    required this.config,
    required MkapExport export,
    required MkapHttpTransport transport,
  })  : _export = export,
        _transport = transport {
    if (_export.clientId != config.clientId ||
        _export.poolVersion != config.poolVersion ||
        _export.identifierLength != config.identifierLength ||
        _export.identifierAlphabet != config.identifierAlphabet) {
      throw ArgumentError(
        'Client configuration does not match the key export.',
      );
    }
  }

  final MkapClientConfig config;
  final MkapExport _export;
  final MkapHttpTransport _transport;

  Future<MkapHttpResponse> request(
    String method,
    String target, [
    Uint8List? body,
  ]) async {
    body ??= Uint8List(0);
    if (!RegExp(r"^[!#$%&'*+.^_`|~0-9A-Z-]{1,32}$").hasMatch(method)) {
      throw ArgumentError('HTTP method must be an uppercase token.');
    }
    final targetBytes = utf8.encode(target);
    if (!target.startsWith('/') ||
        targetBytes.isEmpty ||
        targetBytes.length > 4096 ||
        target.contains('#') ||
        RegExp(r'[\u0000-\u0020\u007f]').hasMatch(target)) {
      throw ArgumentError('Invalid origin-form request target.');
    }
    if (body.length > 16777216) {
      throw ArgumentError('Request body is too large.');
    }
    final bodyHash = Uint8List.fromList(
      crypto.sha256.convert(body).bytes,
    );

    final challengeRequest = jsonEncode(<String, dynamic>{
      'version': 1,
      'client_id': config.clientId,
      'method': method,
      'target': target,
      'body_sha256': encodeBase64Url(bodyHash),
    });
    final challengeResponse = await _transport.send(
      MkapHttpRequest(
        method: 'POST',
        url: config.baseUri.resolve('v1/auth/challenges'),
        headers: const <String, String>{
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: Uint8List.fromList(utf8.encode(challengeRequest)),
      ),
    );
    if (challengeResponse.statusCode < 200 ||
        challengeResponse.statusCode >= 300) {
      throw MkapChallengeException(challengeResponse.statusCode);
    }
    if (challengeResponse.body.length > config.maximumChallengeResponseBytes) {
      throw const MkapChallengeFormatException(
        'Challenge response exceeds the configured size limit.',
      );
    }
    final challenge = _parseChallenge(challengeResponse.body);

    final ikm = BytesBuilder(copy: false);
    for (final keyId in challenge.keyIds) {
      final secret = _export.secrets[keyId];
      if (secret == null) {
        throw const MkapChallengeFormatException(
          'Challenge selected a key absent from the local export.',
        );
      }
      ikm.add(secret);
    }

    final proofKey = hkdfSha256(
      ikm.toBytes(),
      challenge.nonce,
      hkdfContext(
        1,
        config.clientId,
        challenge.requestId,
        config.poolVersion,
        challenge.keyIds,
      ),
      32,
    );
    final message = proofMessage(
      protocolVersion: 1,
      clientId: config.clientId,
      requestId: challenge.requestId,
      nonce: challenge.nonce,
      expiresAt: challenge.expiresAt,
      method: method,
      target: target,
      bodySha256: bodyHash,
      poolVersion: config.poolVersion,
      keyIds: challenge.keyIds,
    );
    final proof = Uint8List.fromList(hmacSha256(proofKey, message));

    return _transport.send(
      MkapHttpRequest(
        method: method,
        url: config.baseUri.resolve(target.substring(1)),
        headers: <String, String>{
          'X-MKAP-Version': '1',
          'X-MKAP-Client': config.clientId,
          'X-MKAP-Request': encodeBase64Url(challenge.requestId),
          'X-MKAP-Proof': encodeBase64Url(proof),
        },
        body: body,
      ),
    );
  }

  MkapChallenge _parseChallenge(Uint8List body) {
    late Map<String, dynamic> data;
    try {
      final decoded = jsonDecode(utf8.decode(body));
      if (decoded is! Map<String, dynamic>) {
        throw const FormatException();
      }
      data = decoded;
    } on FormatException {
      throw const MkapChallengeFormatException(
        'Challenge response is not valid JSON.',
      );
    }
    const fields = <String>[
      'version',
      'request_id',
      'nonce',
      'key_ids',
      'key_count',
      'id_length',
      'expires_in',
      'expires_at',
    ];
    for (final field in fields) {
      if (!data.containsKey(field)) {
        throw MkapChallengeFormatException(
          'Challenge response is missing field: $field',
        );
      }
    }
    if (data['version'] != 1 ||
        data['request_id'] is! String ||
        data['nonce'] is! String ||
        data['key_ids'] is! String ||
        data['key_count'] is! int ||
        data['id_length'] is! int ||
        data['expires_in'] is! int ||
        data['expires_at'] is! int ||
        (data['key_count'] as int) < 1 ||
        (data['key_count'] as int) > _export.secrets.length ||
        (data['id_length'] as int) != _export.identifierLength ||
        (data['expires_in'] as int) < 1 ||
        (data['expires_at'] as int) < 1 ||
        (data['key_ids'] as String).length !=
            (data['key_count'] as int) * (data['id_length'] as int)) {
      throw const MkapChallengeFormatException(
        'Challenge response fields are invalid.',
      );
    }

    late Uint8List requestId;
    late Uint8List nonce;
    try {
      requestId = decodeBase64Url(data['request_id'] as String, 16);
      nonce = decodeBase64Url(data['nonce'] as String, 32);
    } on FormatException {
      throw const MkapChallengeFormatException(
        'Challenge binary fields are malformed.',
      );
    }

    final keyCount = data['key_count'] as int;
    final idLength = data['id_length'] as int;
    final joined = data['key_ids'] as String;
    final keyIds = <String>[];
    for (var offset = 0; offset < joined.length; offset += idLength) {
      keyIds.add(joined.substring(offset, offset + idLength));
    }
    if (keyIds.length != keyCount || keyIds.toSet().length != keyIds.length) {
      throw const MkapChallengeFormatException(
        'Challenge key identifiers are malformed or duplicated.',
      );
    }
    for (final keyId in keyIds) {
      for (final rune in keyId.runes) {
        if (!_export.identifierAlphabet.contains(String.fromCharCode(rune))) {
          throw const MkapChallengeFormatException(
            'Challenge key identifier contains an invalid character.',
          );
        }
      }
    }
    return MkapChallenge(
      requestId: requestId,
      nonce: nonce,
      keyIds: keyIds,
      expiresAt: data['expires_at'] as int,
    );
  }
}

class MkapChallengeException implements Exception {
  const MkapChallengeException(this.statusCode);
  final int statusCode;

  @override
  String toString() => 'Challenge endpoint returned HTTP $statusCode.';
}

class MkapChallengeFormatException implements Exception {
  const MkapChallengeFormatException(this.message);
  final String message;

  @override
  String toString() => message;
}
