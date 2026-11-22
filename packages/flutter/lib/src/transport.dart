import 'dart:typed_data';

import 'package:http/http.dart' as http;

class MkapHttpRequest {
  MkapHttpRequest({
    required this.method,
    required this.url,
    required this.headers,
    required this.body,
  });

  final String method;
  final Uri url;
  final Map<String, String> headers;
  final Uint8List body;
}

class MkapHttpResponse {
  MkapHttpResponse({
    required this.statusCode,
    required this.headers,
    required this.body,
  });

  final int statusCode;
  final Map<String, String> headers;
  final Uint8List body;
}

abstract interface class MkapHttpTransport {
  Future<MkapHttpResponse> send(MkapHttpRequest request);
}

class HttpPackageMkapTransport implements MkapHttpTransport {
  HttpPackageMkapTransport({this.requireHttps = true});

  final bool requireHttps;

  @override
  Future<MkapHttpResponse> send(MkapHttpRequest request) async {
    if (requireHttps && request.url.scheme != 'https') {
      throw ArgumentError('HTTPS is required.');
    }
    if (request.url.userInfo.isNotEmpty) {
      throw ArgumentError('Transport URLs must not contain credentials.');
    }
    final outgoing = http.Request(request.method, request.url)
      ..headers.addAll(request.headers)
      ..followRedirects = false
      ..bodyBytes = request.body;
    final response = await outgoing.send();
    final streamed = await response.stream.toBytes();
    return MkapHttpResponse(
      statusCode: response.statusCode,
      headers: response.headers,
      body: Uint8List.fromList(streamed),
    );
  }
}
