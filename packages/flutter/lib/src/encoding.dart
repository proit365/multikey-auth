import 'dart:convert';
import 'dart:typed_data';

String encodeBase64Url(List<int> bytes) {
  return base64Url.encode(bytes).replaceAll('=', '');
}

Uint8List decodeBase64Url(String value, [int? exactLength]) {
  if (value.isEmpty) {
    final empty = Uint8List(0);
    if (exactLength != null && empty.length != exactLength) {
      throw FormatException('Decoded value has an unexpected length.');
    }
    return empty;
  }
  if (value.length > 8388608 || !RegExp(r'^[A-Za-z0-9_-]+$').hasMatch(value)) {
    throw FormatException('Invalid base64url value.');
  }
  Uint8List bytes;
  try {
    bytes = Uint8List.fromList(base64Url.normalize(value).isEmpty
        ? const <int>[]
        : base64Url.decode(base64Url.normalize(value)));
  } on FormatException {
    throw FormatException('Invalid base64url value.');
  }
  if (encodeBase64Url(bytes) != value) {
    throw FormatException('Non-canonical base64url value.');
  }
  if (exactLength != null && bytes.length != exactLength) {
    throw FormatException('Decoded value has an unexpected length.');
  }
  return bytes;
}

Uint8List uint32(int value) {
  if (value < 0 || value > 0xffffffff) {
    throw RangeError('uint32 value is outside the supported range.');
  }
  final bytes = Uint8List(4);
  final view = ByteData.sublistView(bytes);
  view.setUint32(0, value, Endian.big);
  return bytes;
}

Uint8List uint64(int value) {
  if (value < 0) {
    throw RangeError('uint64 value must be non-negative.');
  }
  final bytes = Uint8List(8);
  final view = ByteData.sublistView(bytes);
  view.setUint64(0, value, Endian.big);
  return bytes;
}

Uint8List lengthField(dynamic value) {
  Uint8List bytes;
  if (value is String) {
    bytes = Uint8List.fromList(utf8.encode(value));
  } else if (value is Uint8List) {
    bytes = value;
  } else if (value is List<int>) {
    bytes = Uint8List.fromList(value);
  } else {
    throw ArgumentError('Length-delimited value must be text or bytes.');
  }
  if (bytes.length > 0xffffffff) {
    throw RangeError('Length-delimited value is too long.');
  }
  final output = BytesBuilder(copy: false);
  output.add(uint32(bytes.length));
  output.add(bytes);
  return output.toBytes();
}
