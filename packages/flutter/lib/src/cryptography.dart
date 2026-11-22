import 'dart:typed_data';
import 'package:crypto/crypto.dart' as crypto;

import 'encoding.dart';

List<int> hmacSha256(List<int> key, List<int> message) {
  final mac = crypto.Hmac(crypto.sha256, key);
  return mac.convert(message).bytes;
}

List<int> hkdfSha256(
  List<int> ikm,
  Uint8List salt,
  Uint8List info,
  int length,
) {
  if (salt.length != 32) {
    throw ArgumentError('HKDF salt must be exactly 32 bytes.');
  }
  if (length < 1 || length > 8160) {
    throw RangeError('HKDF length is outside the supported range.');
  }

  final prk = hmacSha256(salt, ikm);
  final blocks = (length / 32).ceil();
  final output = BytesBuilder(copy: false);
  var previous = Uint8List(0);
  for (var counter = 1; counter <= blocks; counter++) {
    final blockInput = BytesBuilder(copy: false);
    blockInput.add(previous);
    blockInput.add(info);
    blockInput.addByte(counter);
    previous = Uint8List.fromList(hmacSha256(prk, blockInput.toBytes()));
    output.add(previous);
  }
  final bytes = output.toBytes();
  return Uint8List.fromList(bytes.take(length).toList());
}

Uint8List hkdfContext(
  int protocolVersion,
  String clientId,
  Uint8List requestId,
  int poolVersion,
  List<String> keyIds,
) {
  validateKeyIds(keyIds);
  final output = BytesBuilder(copy: false);
  output.add(lengthField('MKAP'));
  output.add(uint32(protocolVersion));
  output.add(lengthField(clientId));
  output.add(lengthField(requestId));
  output.add(uint32(poolVersion));
  output.add(uint32(keyIds.length));
  for (final keyId in keyIds) {
    output.add(lengthField(keyId));
  }
  return output.toBytes();
}

Uint8List proofMessage({
  required int protocolVersion,
  required String clientId,
  required Uint8List requestId,
  required Uint8List nonce,
  required int expiresAt,
  required String method,
  required String target,
  required Uint8List bodySha256,
  required int poolVersion,
  required List<String> keyIds,
}) {
  if (nonce.length != 32) {
    throw ArgumentError('Nonce must be exactly 32 bytes.');
  }
  if (bodySha256.length != 32) {
    throw ArgumentError('Body SHA-256 must be exactly 32 bytes.');
  }
  validateKeyIds(keyIds);

  final output = BytesBuilder(copy: false);
  output.add(uint32(protocolVersion));
  output.add(lengthField(clientId));
  output.add(lengthField(requestId));
  output.add(lengthField(nonce));
  output.add(uint64(expiresAt));
  output.add(lengthField(method));
  output.add(lengthField(target));
  output.add(lengthField(bodySha256));
  output.add(uint32(poolVersion));
  output.add(uint32(keyIds.length));
  for (final keyId in keyIds) {
    output.add(lengthField(keyId));
  }
  return output.toBytes();
}

void validateKeyIds(List<String> keyIds) {
  if (keyIds.isEmpty || keyIds.length > 0xffffffff) {
    throw ArgumentError('Invalid key identifier count.');
  }
  final seen = <String>{};
  for (final keyId in keyIds) {
    if (keyId.isEmpty || keyId.codeUnits.length > 256) {
      throw ArgumentError('Invalid key identifier.');
    }
    if (!seen.add(keyId)) {
      throw ArgumentError('Duplicate key identifier.');
    }
  }
}
