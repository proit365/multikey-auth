'use strict';

const fs = require('fs');
const { decodeBase64Url } = require('./encoding');

function validateExport(manifest) {
  if (!manifest || typeof manifest !== 'object' || Array.isArray(manifest)) {
    throw new Error('Export manifest must be an object');
  }
  const required = [
    'format_version', 'protocol_version', 'client_id', 'pool_id',
    'pool_version', 'export_version', 'created_at', 'identifier_length',
    'identifier_alphabet', 'secret_length_bytes', 'key_count', 'keys'
  ];
  for (const field of required) {
    if (!Object.prototype.hasOwnProperty.call(manifest, field)) {
      throw new Error('Export manifest is missing field: ' + field);
    }
  }
  if (manifest.format_version !== 1 || manifest.protocol_version !== 1) {
    throw new Error('Unsupported export or protocol version');
  }
  if (!Number.isInteger(manifest.identifier_length)
      || manifest.identifier_length < 1 || manifest.identifier_length > 256) {
    throw new Error('Invalid identifier length');
  }
  if (typeof manifest.identifier_alphabet !== 'string'
      || manifest.identifier_alphabet.length === 0
      || new Set(Array.from(manifest.identifier_alphabet)).size !== Array.from(manifest.identifier_alphabet).length) {
    throw new Error('Invalid identifier alphabet');
  }
  if (manifest.secret_length_bytes !== 32) {
    throw new Error('Export secret length must be 32 bytes');
  }
  if (!Number.isInteger(manifest.key_count) || manifest.key_count < 1
      || !Array.isArray(manifest.keys) || manifest.keys.length !== manifest.key_count) {
    throw new Error('Export key count is inconsistent');
  }

  const secrets = new Map();
  for (const key of manifest.keys) {
    if (!key || typeof key !== 'object'
        || typeof key.id !== 'string'
        || typeof key.secret !== 'string'
        || key.id.length !== manifest.identifier_length) {
      throw new Error('Malformed export key record');
    }
    if (secrets.has(key.id)) {
      throw new Error('Duplicate export key identifier');
    }
    for (const character of key.id) {
      if (!manifest.identifier_alphabet.includes(character)) {
        throw new Error('Export key identifier contains an invalid character');
      }
    }
    const secret = decodeBase64Url(key.secret, 32);
    secrets.set(key.id, secret);
  }

  return {
    clientId: manifest.client_id,
    poolId: manifest.pool_id,
    poolVersion: manifest.pool_version,
    identifierLength: manifest.identifier_length,
    identifierAlphabet: manifest.identifier_alphabet,
    secrets
  };
}

function loadExportJson(json) {
  if (typeof json !== 'string') {
    throw new TypeError('Export JSON must be a string');
  }
  if (Buffer.byteLength(json, 'utf8') > 67108864) {
    throw new Error('Export exceeds the configured size limit');
  }
  let manifest;
  try {
    manifest = JSON.parse(json);
  } catch (error) {
    throw new Error('Export is not valid JSON');
  }
  return validateExport(manifest);
}

function loadExportFile(path) {
  if (typeof path !== 'string' || path.length === 0) {
    throw new TypeError('Export path must be a non-empty string');
  }
  return loadExportJson(fs.readFileSync(path, 'utf8'));
}

module.exports = {
  validateExport,
  loadExportJson,
  loadExportFile
};
