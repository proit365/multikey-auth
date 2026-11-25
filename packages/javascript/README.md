# JavaScript SDK

`@proit365/multikey-auth` is a CommonJS client SDK for MKAP v1. It obtains a
server-issued challenge and creates an HMAC-SHA256 proof bound to the exact
HTTP method, request target, and request-body bytes. The SDK uses Node.js built-in
modules only and has no runtime dependencies.

## Requirements and installation

- Node.js 22 or newer, as declared by the package.
- A server-side Node.js environment. This package is not browser-compatible.
- A client export generated for the corresponding MKAP client and pool.

Published package: [`@proit365/multikey-auth` on npm](https://www.npmjs.com/package/@proit365/multikey-auth).

```sh
npm install @proit365/multikey-auth
```

The package is CommonJS. Use `require()`:

```js
const { MkapClient } = require('@proit365/multikey-auth');
```

TypeScript consumers also need Node.js type declarations (for example,
`@types/node`) because the public API accepts and returns `Buffer` values.

## Configuration and request workflow

`baseUri` must be an origin (scheme and host only). HTTPS is required by default.
`poolVersion`, `identifierLength`, and `identifierAlphabet` must match the
provided client export. Supply either `exportPath` or `exportJson`; the export
contains the client's secret key material.

```js
const { MkapClient } = require('@proit365/multikey-auth');

const client = new MkapClient({
  baseUri: 'https://api.example.test',
  clientId: 'CLIENT_001',
  poolVersion: 1,
  identifierLength: 6,
  identifierAlphabet: 'abcdefghijklmnopqrstuvwxyz0123456789',
  exportPath: '/secure/client-export.json'
});

async function main() {
  const response = await client.request(
    'POST',
    '/api/orders?status=pending',
    Buffer.from('{"status":"pending"}', 'utf8')
  );
  console.log(response.statusCode);
}

main().catch((error) => {
  console.error('Authenticated request failed:', error.message);
  process.exitCode = 1;
});
```

For each call, `MkapClient.request()`:

1. Validates the method and origin-form request target and hashes the exact
   request-body bytes. Targets that URL parsing would normalize are rejected;
   percent-encode non-ASCII path or query data.
2. Requests a challenge from `POST /v1/auth/challenges`.
3. Validates the challenge fields and selected key identifiers against the
   local export.
4. Derives the proof key with HKDF-SHA256 and creates an HMAC-SHA256 proof.
5. Sends the original request with the MKAP authentication headers.

The SDK does not retry a `401` or replay requests. A final API response,
including non-2xx statuses, is returned to the caller as an `HttpResponse`.

## API

- `new MkapClient(config, transport?)`: create a client. Required configuration
  includes `baseUri`, `clientId`, `poolVersion`, `identifierLength`,
  `identifierAlphabet`, and one of `exportPath` or `exportJson`. HTTPS is
  required unless `requireHttps: false` is explicitly supplied for non-production
  use. `maximumChallengeResponseBytes` defaults to 65,536.
- `MkapClient.request(method, target, body?)`: send a signed request. `body` may
  be a `Buffer`, string, or omitted; strings are encoded as UTF-8.
- `MkapClient.parseChallenge(body)`: validate and parse a challenge response;
  normally called internally by `request()`.
- `NodeHttpTransport(options?)`: built-in Node HTTP(S) transport. HTTPS is
  required unless its `requireHttps` option is false. It does not follow
  redirects and returns 3xx responses unchanged.
- `HttpTransport`: interface for a custom transport. Its `send()` method must
  preserve the supplied method, URL, authentication headers, and body bytes.
  A custom transport may add application headers such as `Content-Type`.
- Export and protocol helpers are also exported: `loadExportJson`,
  `loadExportFile`, `validateExport`, `encodeBase64Url`, `decodeBase64Url`,
  `uint32`, `uint64`, `lengthField`, `hmacSha256`, `hkdfSha256`, `hkdfContext`,
  `proofMessage`, and `requestUrl`.

## Errors and transport behavior

Invalid configuration, methods, targets, exports, or challenge responses throw
errors. A non-2xx challenge response throws an error with its `statusCode`.
Transport failures reject the request promise. The final API response status is
not converted into an exception; handle it according to your API's contract.

The default `NodeHttpTransport` enforces HTTPS, does not follow redirects, and
rejects URLs with credentials. A custom transport must enforce equivalent
transport security and must send the exact signed target and body bytes.

## Security

Treat the client export and its secret keys as credentials: restrict access,
keep them out of source control and logs, and do not include them in browser
bundles. Use HTTPS in production; do not set `requireHttps: false` for deployed
services. Do not send secret exports to untrusted systems.

MKAP is a custom protocol. The repository includes cross-language test vectors,
but the protocol has not been independently security reviewed. Review the
[MKAP monorepo security guidance](https://github.com/proit365/multikey-auth)
before production use.

## Browser limitation

This package requires Node.js built-ins including `crypto`, `http`, `https`,
`fs`, and `Buffer`; it does not provide a browser build. Do not bundle this SDK
or a plaintext key export into browser JavaScript. Browser use requires a
separate backend-for-frontend or device-bound credential design.

## Project

Visit [www.proit365.com](https://www.proit365.com/). The authoritative source,
protocol specification, and other SDKs are in the
[ProIT365 MultiKey Auth monorepo](https://github.com/proit365/multikey-auth).
