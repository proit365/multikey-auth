# API Reference

## PHP client

- `ExportLoader::fromJson(string): ClientKeyPool`
- `PhpMultiKeyClient::request(string $method, string $target, string $body): HttpResponse`
- `HttpTransportInterface::send(HttpRequest): HttpResponse`

## Laravel client

- `LaravelMultiKeyClient::request()`
- `LaravelMultiKeyClient::get()`
- `LaravelMultiKeyClient::post()`
- `LaravelMultiKeyClient::put()`
- `LaravelMultiKeyClient::patch()`
- `LaravelMultiKeyClient::delete()`
- Facade: `MKAP`

## JavaScript

- `MkapClient.request(method, target, body)`
- `loadExportJson`, `loadExportFile`, `validateExport`
- `hkdfSha256`, `hkdfContext`, `proofMessage`, `hmacSha256`

## Flutter

- `MkapClient.request()`
- `MkapExport.fromJson()`
- `MkapClientConfig.validate()`
- `MkapSecureStorage`
- `MkapHttpTransport`
- `hkdfSha256`, `hkdfContext`, `proofMessage`, `hmacSha256`

## CLI

See `packages/cli/README.md` for commands and confirmation rules.
