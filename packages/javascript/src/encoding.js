'use strict';

function assertBuffer(value, name) {
  if (!Buffer.isBuffer(value)) {
    throw new TypeError(name + ' must be a Buffer');
  }
}

function encodeBase64Url(buffer) {
  assertBuffer(buffer, 'Base64URL value');
  return buffer.toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
}

function decodeBase64Url(value, exactLength) {
  if (typeof value !== 'string' || value.length === 0 && exactLength !== 0) {
    throw new Error('Invalid base64url value');
  }
  if (value.length > 8388608 || !/^[A-Za-z0-9_-]*$/.test(value)) {
    throw new Error('Invalid base64url value');
  }
  if (value.includes('=')) {
    throw new Error('Invalid base64url padding');
  }
  const padding = (4 - (value.length % 4)) % 4;
  let buffer;
  try {
    buffer = Buffer.from(value.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat(padding), 'base64');
  } catch (error) {
    throw new Error('Invalid base64url value');
  }
  if (encodeBase64Url(buffer) !== value) {
    throw new Error('Invalid canonical base64url value');
  }
  if (typeof exactLength === 'number' && buffer.length !== exactLength) {
    throw new Error('Decoded value has an unexpected length');
  }
  return buffer;
}

function uint32(value) {
  if (!Number.isInteger(value) || value < 0 || value > 0xffffffff) {
    throw new RangeError('uint32 value is outside the supported range');
  }
  const buffer = Buffer.alloc(4);
  buffer.writeUInt32BE(value, 0);
  return buffer;
}

function uint64(value) {
  if (!Number.isSafeInteger(value) || value < 0 || value > 0xffffffff * 0x100000000) {
    throw new RangeError('uint64 value is outside the supported range');
  }
  const buffer = Buffer.alloc(8);
  const high = Math.floor(value / 0x100000000);
  const low = value >>> 0;
  buffer.writeUInt32BE(high, 0);
  buffer.writeUInt32BE(low, 4);
  return buffer;
}

function lengthField(value) {
  let bytes;
  if (typeof value === 'string') {
    bytes = Buffer.from(value, 'utf8');
  } else if (Buffer.isBuffer(value)) {
    bytes = value;
  } else {
    throw new TypeError('Length-delimited value must be a string or Buffer');
  }
  if (bytes.length > 0xffffffff) {
    throw new RangeError('Length-delimited text is too long');
  }
  return Buffer.concat([uint32(bytes.length), bytes]);
}

module.exports = {
  encodeBase64Url,
  decodeBase64Url,
  uint32,
  uint64,
  lengthField
};
