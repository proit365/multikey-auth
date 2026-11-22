import 'dart:convert';
import 'dart:typed_data';

import 'encoding.dart';

class MkapExport {
  MkapExport({
    required this.clientId,
    required this.poolId,
    required this.poolVersion,
    required this.identifierLength,
    required this.identifierAlphabet,
    required this.secrets,
  });

  final String clientId;
  final String poolId;
  final int poolVersion;
  final int identifierLength;
  final String identifierAlphabet;
  final Map<String, Uint8List> secrets;

  static MkapExport fromJson(String json) {
    if (utf8.encode(json).length > 67108864) {
      throw ArgumentError('Export exceeds the configured size limit.');
    }
    late Map<String, dynamic> manifest;
    try {
      final decoded = jsonDecode(json);
      if (decoded is! Map<String, dynamic>) {
        throw const FormatException('Export manifest must be a JSON object.');
      }
      manifest = decoded;
    } on FormatException {
      rethrow;
    } catch (_) {
      throw const FormatException('Export is not valid JSON.');
    }

    const required = <String>[
      'format_version',
      'protocol_version',
      'client_id',
      'pool_id',
      'pool_version',
      'export_version',
      'created_at',
      'identifier_length',
      'identifier_alphabet',
      'secret_length_bytes',
      'key_count',
      'keys',
    ];
    for (final field in required) {
      if (!manifest.containsKey(field)) {
        throw FormatException('Export manifest is missing field: $field');
      }
    }
    if (manifest['format_version'] != 1 || manifest['protocol_version'] != 1) {
      throw const FormatException('Unsupported export or protocol version.');
    }
    final identifierLength = manifest['identifier_length'];
    if (identifierLength is! int ||
        identifierLength < 1 ||
        identifierLength > 256) {
      throw const FormatException('Invalid identifier length.');
    }
    final alphabet = manifest['identifier_alphabet'];
    if (alphabet is! String ||
        alphabet.isEmpty ||
        alphabet.runes.toSet().length != alphabet.runes.length) {
      throw const FormatException('Invalid identifier alphabet.');
    }
    if (manifest['secret_length_bytes'] != 32) {
      throw const FormatException('Export secret length must be 32 bytes.');
    }
    final keyCount = manifest['key_count'];
    final keys = manifest['keys'];
    if (keyCount is! int ||
        keyCount < 1 ||
        keys is! List ||
        keys.length != keyCount) {
      throw const FormatException('Export key count is inconsistent.');
    }

    final secrets = <String, Uint8List>{};
    for (final key in keys) {
      if (key is! Map ||
          key['id'] is! String ||
          key['secret'] is! String ||
          (key['id'] as String).length != identifierLength) {
        throw const FormatException('Malformed export key record.');
      }
      final identifier = key['id'] as String;
      final secretValue = key['secret'] as String;
      if (secrets.containsKey(identifier)) {
        throw const FormatException('Duplicate export key identifier.');
      }
      for (final rune in identifier.runes) {
        if (!alphabet.contains(String.fromCharCode(rune))) {
          throw const FormatException(
            'Export key identifier contains an invalid character.',
          );
        }
      }
      secrets[identifier] = decodeBase64Url(secretValue, 32);
    }

    final clientId = manifest['client_id'];
    final poolId = manifest['pool_id'];
    final poolVersion = manifest['pool_version'];
    if (clientId is! String ||
        clientId.isEmpty ||
        poolId is! String ||
        poolId.isEmpty ||
        poolVersion is! int ||
        poolVersion < 1) {
      throw const FormatException('Export client or pool metadata is invalid.');
    }

    return MkapExport(
      clientId: clientId,
      poolId: poolId,
      poolVersion: poolVersion,
      identifierLength: identifierLength,
      identifierAlphabet: alphabet,
      secrets: secrets,
    );
  }
}
