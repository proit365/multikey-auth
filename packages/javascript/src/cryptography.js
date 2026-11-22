'use strict';

const crypto = require('crypto');
const { lengthField, uint32, uint64 } = require('./encoding');

function hmacSha256(key, message) {
  if (!Buffer.isBuffer(key) || !Buffer.isBuffer(message)) {
    throw new TypeError('HMAC key and message must be Buffers');
  }
  return crypto.createHmac('sha256', key).update(message).digest();
}

function hkdfSha256(ikm, salt, info, length) {
  if (!Buffer.isBuffer(ikm) || !Buffer.isBuffer(salt) || !Buffer.isBuffer(info)) {
    throw new TypeError('HKDF inputs must be Buffers');
  }
  if (salt.length !== 32) {
    throw new Error('HKDF salt must be exactly 32 bytes');
  }
  if (!Number.isInteger(length) || length < 1 || length > 8160) {
    throw new RangeError('HKDF length is outside the supported range');
  }

  const prk = hmacSha256(salt, ikm);
  const blocks = Math.ceil(length / 32);
  const output = [];
  let previous = Buffer.alloc(0);
  for (let counter = 1; counter <= blocks; counter++) {
    previous = hmacSha256(prk, Buffer.concat([previous, info, Buffer.from([counter])]));
    output.push(previous);
  }
  return Buffer.concat(output).subarray(0, length);
}

function hkdfContext(protocolVersion, clientId, requestId, poolVersion, keyIds) {
  validateKeyIds(keyIds);
  const parts = [
    lengthField('MKAP'),
    uint32(protocolVersion),
    lengthField(clientId),
    lengthField(requestId),
    uint32(poolVersion),
    uint32(keyIds.length)
  ];
  keyIds.forEach((keyId) => parts.push(lengthField(keyId)));
  return Buffer.concat(parts);
}

function proofMessage(
  protocolVersion,
  clientId,
  requestId,
  nonce,
  expiresAt,
  method,
  target,
  bodySha256,
  poolVersion,
  keyIds
) {
  if (!Buffer.isBuffer(nonce) || nonce.length !== 32) {
    throw new Error('Nonce must be exactly 32 bytes');
  }
  if (!Buffer.isBuffer(bodySha256) || bodySha256.length !== 32) {
    throw new Error('Body SHA-256 must be exactly 32 bytes');
  }
  validateKeyIds(keyIds);

  const parts = [
    uint32(protocolVersion),
    lengthField(clientId),
    lengthField(requestId),
    lengthField(nonce),
    uint64(expiresAt),
    lengthField(method),
    lengthField(target),
    lengthField(bodySha256),
    uint32(poolVersion),
    uint32(keyIds.length)
  ];
  keyIds.forEach((keyId) => parts.push(lengthField(keyId)));
  return Buffer.concat(parts);
}

function validateKeyIds(keyIds) {
  if (!Array.isArray(keyIds) || keyIds.length === 0 || keyIds.length > 0xffffffff) {
    throw new Error('Invalid key identifier count');
  }
  const seen = new Set();
  keyIds.forEach((keyId) => {
    if (typeof keyId !== 'string' || keyId.length === 0 || Buffer.byteLength(keyId, 'utf8') > 256) {
      throw new Error('Invalid key identifier');
    }
    if (seen.has(keyId)) {
      throw new Error('Duplicate key identifier');
    }
    seen.add(keyId);
  });
}

module.exports = {
  hmacSha256,
  hkdfSha256,
  hkdfContext,
  proofMessage
};
