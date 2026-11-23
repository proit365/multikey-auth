'use strict';

const assert = require('assert');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const {
  MkapClient,
  hkdfSha256,
  hkdfContext,
  proofMessage,
  hmacSha256,
  decodeBase64Url,
  encodeBase64Url,
  requestUrl
} = require('../src');

const vectorPath = path.resolve(__dirname, '../../../specification/test-vectors-v1.json');
const vector = JSON.parse(fs.readFileSync(vectorPath, 'utf8'));

assert.throws(
  () => requestUrl('https://api.example.test', '//attacker.example/path'),
  /must remain on the base origin/
);
assert.throws(
  () => requestUrl('https://api.example.test', '/api/../admin'),
  /not canonical for the transport/
);

const ikm = Buffer.from(vector.hkdf.ikm_hex, 'hex');
const salt = Buffer.from(vector.hkdf.salt_hex, 'hex');
const info = Buffer.from(vector.hkdf.info_hex, 'hex');
const derived = hkdfSha256(ikm, salt, info, 32);
assert.strictEqual(derived.toString('hex'), vector.hkdf.output_hex);

const context = hkdfContext(
  1,
  'CLIENT_001',
  Buffer.from(vector.challenge_fixture.request_id_hex, 'hex'),
  1,
  vector.proof.key_ids
);
assert.strictEqual(context.toString('hex'), vector.hkdf.info_hex);

const nonce = Buffer.from(vector.challenge_fixture.nonce_hex, 'hex');
const bodyHash = Buffer.from(vector.proof.body_sha256_hex, 'hex');
const requestId = Buffer.from(vector.challenge_fixture.request_id_hex, 'hex');
const message = proofMessage(
  1,
  'CLIENT_001',
  requestId,
  nonce,
  vector.proof.expires_at,
  vector.proof.http_method,
  vector.proof.request_target,
  bodyHash,
  vector.proof.pool_version,
  vector.proof.key_ids
);
assert.strictEqual(message.toString('hex'), vector.proof.message_hex);
const proof = hmacSha256(derived, message);
assert.strictEqual(proof.toString('hex'), vector.proof.hmac_hex);
assert.strictEqual(encodeBase64Url(proof), vector.proof.hmac_base64url);

const secretHexParts = [
  vector.hkdf.ikm_hex.slice(0, 64),
  vector.hkdf.ikm_hex.slice(64, 128),
  vector.hkdf.ikm_hex.slice(128, 192)
];
const manifest = {
  format_version: 1,
  protocol_version: 1,
  client_id: 'CLIENT_001',
  pool_id: 'POOL_001',
  pool_version: 1,
  export_version: 1,
  created_at: '2026-11-22T00:00:00Z',
  identifier_length: 6,
  identifier_alphabet: 'abcdefghijklmnopqrstuvwxyz0123456789',
  secret_length_bytes: 32,
  key_count: 3,
  keys: vector.proof.key_ids.map((id, index) => ({
    id,
    secret: encodeBase64Url(Buffer.from(secretHexParts[index], 'hex'))
  }))
};

const requests = [];
const mockTransport = {
  async send(request) {
    requests.push(request);
    if (requests.length === 1) {
      return {
        statusCode: 200,
        headers: {},
        body: Buffer.from(JSON.stringify({
          version: 1,
          request_id: vector.challenge_fixture.request_id_base64url,
          nonce: vector.challenge_fixture.nonce_base64url,
          key_ids: vector.challenge_fixture.key_ids,
          key_count: vector.challenge_fixture.key_count,
          id_length: vector.challenge_fixture.id_length,
          expires_in: 30,
          expires_at: vector.proof.expires_at
        }))
      };
    }
    return {
      statusCode: 202,
      headers: { 'content-type': 'application/json' },
      body: Buffer.from('{"accepted":true}')
    };
  }
};

const client = new MkapClient({
  baseUri: 'https://api.example.test',
  clientId: 'CLIENT_001',
  poolVersion: 1,
  identifierLength: 6,
  identifierAlphabet: 'abcdefghijklmnopqrstuvwxyz0123456789',
  exportJson: JSON.stringify(manifest),
  maximumChallengeResponseBytes: 65536,
  requireHttps: true
}, mockTransport);

client.request(
  vector.proof.http_method,
  vector.proof.request_target,
  Buffer.alloc(0)
).then((response) => {
  assert.strictEqual(response.statusCode, 202);
  assert.strictEqual(response.body.toString('utf8'), '{"accepted":true}');
  assert.strictEqual(requests.length, 2);
  assert.strictEqual(requests[0].method, 'POST');
  assert.strictEqual(requests[0].url, 'https://api.example.test/v1/auth/challenges');
  assert.strictEqual(requests[1].method, 'POST');
  assert.strictEqual(requests[1].url, 'https://api.example.test/api/orders?status=pending');
  assert.strictEqual(requests[1].body.length, 0);
  assert.strictEqual(requests[1].headers['X-MKAP-Request'], vector.challenge_fixture.request_id_base64url);
  assert.strictEqual(requests[1].headers['X-MKAP-Proof'], vector.proof.hmac_base64url);

  const originalBody = Buffer.from('{"status":"pending"}');
  const callerBody = Buffer.from(originalBody);
  const mutationRequests = [];
  const mutationTransport = {
    async send(request) {
      mutationRequests.push(request);
      if (mutationRequests.length === 1) {
        const challengeRequest = JSON.parse(request.body.toString('utf8'));
        const expectedHash = crypto.createHash('sha256').update(originalBody).digest();
        assert.strictEqual(challengeRequest.body_sha256, encodeBase64Url(expectedHash));
        callerBody.fill(0);
        return {
          statusCode: 200,
          headers: {},
          body: Buffer.from(JSON.stringify({
            version: 1,
            request_id: vector.challenge_fixture.request_id_base64url,
            nonce: vector.challenge_fixture.nonce_base64url,
            key_ids: vector.challenge_fixture.key_ids,
            key_count: vector.challenge_fixture.key_count,
            id_length: vector.challenge_fixture.id_length,
            expires_in: 30,
            expires_at: vector.proof.expires_at
          }))
        };
      }
      return {
        statusCode: 202,
        headers: {},
        body: Buffer.alloc(0)
      };
    }
  };
  const mutationClient = new MkapClient({
    baseUri: 'https://api.example.test',
    clientId: 'CLIENT_001',
    poolVersion: 1,
    identifierLength: 6,
    identifierAlphabet: 'abcdefghijklmnopqrstuvwxyz0123456789',
    exportJson: JSON.stringify(manifest),
    requireHttps: true
  }, mutationTransport);

  let invalidTargetTransportCalled = false;
  const invalidTargetClient = new MkapClient({
    baseUri: 'https://api.example.test',
    clientId: 'CLIENT_001',
    poolVersion: 1,
    identifierLength: 6,
    identifierAlphabet: 'abcdefghijklmnopqrstuvwxyz0123456789',
    exportJson: JSON.stringify(manifest),
    requireHttps: true
  }, {
    async send() {
      invalidTargetTransportCalled = true;
      throw new Error('Transport must not be called for an invalid target');
    }
  });

  return assert.rejects(
    invalidTargetClient.request('GET', '//attacker.example/path'),
    /must remain on the base origin/
  ).then(() => {
    assert.strictEqual(invalidTargetTransportCalled, false);
    return mutationClient.request(
      vector.proof.http_method,
      vector.proof.request_target,
      callerBody
    );
  }).then(() => {
    assert.deepStrictEqual(mutationRequests[1].body, originalBody);
    console.log('JavaScript interoperability tests passed.');
  });
}).catch((error) => {
  console.error(error && error.stack ? error.stack : error);
  process.exitCode = 1;
});
