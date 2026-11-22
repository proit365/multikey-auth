'use strict';

const path = require('path');
const { MkapClient } = require('../../packages/javascript/src');

async function main() {
  const client = new MkapClient({
    baseUri: 'http://127.0.0.1:8080',
    clientId: 'DEMO_CLIENT',
    poolVersion: 1,
    identifierLength: 6,
    identifierAlphabet: 'abcdefghijklmnopqrstuvwxyz0123456789',
    exportPath: path.join(__dirname, 'data', 'client-export.json'),
    requireHttps: false
  });

  const body = Buffer.from('{"sku":"MKAP-DEMO","quantity":1}', 'utf8');
  const response = await client.request('POST', '/api/orders', body);
  const responseBody = response.body.toString('utf8');

  process.stdout.write('HTTP ' + response.statusCode + '\n' + responseBody + '\n');
  if (response.statusCode !== 200) {
    process.exitCode = 1;
  }
}

main().catch((error) => {
  console.error(error && error.stack ? error.stack : error);
  process.exitCode = 1;
});
