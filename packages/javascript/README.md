# JavaScript SDK

`@realmatrix/multikey-auth` is a server-side JavaScript implementation of MKAP
v1 for Node.js. It uses Node's built-in `crypto` APIs, supports Node.js 12 and
later, and has no runtime dependencies.

```js
const { MkapClient } = require('@realmatrix/multikey-auth');

const client = new MkapClient({
  baseUri: 'https://api.example.test',
  clientId: 'CLIENT_001',
  poolVersion: 1,
  identifierLength: 6,
  identifierAlphabet: 'abcdefghijklmnopqrstuvwxyz0123456789',
  exportPath: '/secure/client-export.json',
  requireHttps: true
});

const response = await client.request(
  'POST',
  '/api/orders',
  Buffer.from('{"status":"pending"}')
);
```

`MkapClient.request()` preserves a supplied `Buffer` exactly. It hashes and
transmits the exact bytes and returns the ordinary transport response object.
It does not replay a `401` response automatically. A `challenge` HTTP failure
throws with `statusCode`; malformed or oversized challenge responses throw
before signing.

`NodeHttpTransport` does not follow redirects. Redirecting a signed request
would move the request to another origin or target outside the proof binding.
Custom transports can implement `send({ method, url, headers, body })` and must
preserve the supplied method, URL, headers, and exact body bytes.

## Browser limitation

Do not use this package or a complete plaintext export in browser JavaScript.
Browser storage and JavaScript execution are not a safe environment for
secret key pools. Browser support requires a separate backend-for-frontend or
device-bound credential architecture, not simply bundling this SDK.
