# JavaScript SDK

Package: `@proit365/multikey-auth`

```js
const { MkapClient } = require('@proit365/multikey-auth');
const client = new MkapClient({
  baseUri: 'https://api.example.test',
  clientId: 'CLIENT_001',
  poolVersion: 1,
  identifierLength: 6,
  identifierAlphabet: 'abcdefghijklmnopqrstuvwxyz0123456789',
  exportPath: '/secure/export.json'
});
const response = await client.request('POST', '/api/orders', Buffer.from('{}'));
```

The published MKAP vector passes. Live Node-to-PHP HTTP authentication remains
pending. Do not run a complete secret pool in browser JavaScript.
