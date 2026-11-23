'use strict';

const crypto = require('crypto');
const { encodeBase64Url, decodeBase64Url } = require('./encoding');
const { hkdfSha256, hkdfContext, proofMessage, hmacSha256 } = require('./cryptography');
const { loadExportJson, loadExportFile } = require('./export');
const { requestUrl, NodeHttpTransport } = require('./transport');
const { URL } = require('url');

function validateUtf8(value, name, maximumBytes) {
  if (typeof value !== 'string') {
    throw new TypeError(name + ' must be a string');
  }
  const bytes = Buffer.byteLength(value, 'utf8');
  if (bytes < 1 || bytes > maximumBytes) {
    throw new Error(name + ' length is outside the supported range');
  }
  if (Buffer.from(value, 'utf8').toString('utf8') !== value) {
    throw new Error(name + ' must be valid UTF-8');
  }
  return bytes;
}

function validateMethod(method) {
  if (!/^[!#$%&'*+.^_`|~0-9A-Z-]{1,32}$/.test(method)) {
    throw new Error('HTTP method must be an uppercase token');
  }
}

function validateTarget(target) {
  validateUtf8(target, 'Request target', 4096);
  if (!target.startsWith('/')
      || target.includes('#')
      || /[\u0000-\u0020\u007f]/.test(target)) {
    throw new Error('Invalid origin-form request target');
  }
}

class MkapClient {
  constructor(config, transport) {
    if (!config || typeof config !== 'object') {
      throw new TypeError('Client config is required');
    }
    if (!config.baseUri || !config.clientId || !config.exportJson && !config.exportPath) {
      throw new Error('Client configuration is incomplete');
    }
    validateUtf8(config.clientId, 'Client identifier', 128);
    let baseUrl;
    try {
      baseUrl = new URL(config.baseUri);
    } catch (error) {
      throw new Error('Base URI is invalid');
    }
    if (!['https:', 'http:'].includes(baseUrl.protocol)
        || baseUrl.username
        || baseUrl.password
        || baseUrl.search
        || baseUrl.hash
        || (baseUrl.pathname !== '/' && baseUrl.pathname !== '')) {
      throw new Error('Base URI must be an origin with no credentials, path, query, or fragment');
    }
    if (config.requireHttps !== false && baseUrl.protocol !== 'https:') {
      throw new Error('HTTPS is required');
    }
    if (!Number.isInteger(config.poolVersion) || config.poolVersion < 1) {
      throw new Error('Pool version must be positive');
    }
    if (!Number.isInteger(config.identifierLength)
        || config.identifierLength < 1 || config.identifierLength > 256) {
      throw new Error('Identifier length is outside the supported range');
    }
    if (typeof config.identifierAlphabet !== 'string'
        || config.identifierAlphabet.length === 0
        || new Set(Array.from(config.identifierAlphabet)).size !== Array.from(config.identifierAlphabet).length) {
      throw new Error('Identifier alphabet is invalid');
    }

    let pool;
    if (config.exportJson) {
      pool = loadExportJson(config.exportJson);
    } else {
      pool = loadExportFile(config.exportPath);
    }
    if (pool.clientId !== config.clientId
        || pool.poolVersion !== config.poolVersion
        || pool.identifierLength !== config.identifierLength
        || pool.identifierAlphabet !== config.identifierAlphabet) {
      throw new Error('Client configuration does not match the export');
    }

    this.config = Object.assign({ maximumChallengeResponseBytes: 65536 }, config);
    this.pool = pool;
    this.transport = transport || new NodeHttpTransport({
      requireHttps: config.requireHttps !== false
    });
  }

  async request(method, target, body) {
    validateMethod(method);
    validateTarget(target);
    const bodyBuffer = Buffer.isBuffer(body)
      ? Buffer.from(body)
      : Buffer.from(body || '', 'utf8');
    if (bodyBuffer.length > 16777216) {
      throw new Error('Request body is too large');
    }
    const bodyHash = crypto.createHash('sha256').update(bodyBuffer).digest();
    const apiUrl = requestUrl(this.config.baseUri, target);

    const challengeRequest = {
      version: 1,
      client_id: this.config.clientId,
      method,
      target,
      body_sha256: encodeBase64Url(bodyHash)
    };
    const challengeUrl = requestUrl(this.config.baseUri, '/v1/auth/challenges');
    const challengeResponse = await this.transport.send({
      method: 'POST',
      url: challengeUrl.toString(),
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: Buffer.from(JSON.stringify(challengeRequest))
    });
    if (challengeResponse.statusCode < 200 || challengeResponse.statusCode >= 300) {
      const error = new Error('Challenge endpoint returned HTTP ' + challengeResponse.statusCode);
      error.statusCode = challengeResponse.statusCode;
      throw error;
    }
    if (challengeResponse.body.length > this.config.maximumChallengeResponseBytes) {
      throw new Error('Challenge response exceeds the configured size limit');
    }

    const challenge = this.parseChallenge(challengeResponse.body);
    const selectedSecrets = [];
    for (const keyId of challenge.keyIds) {
      const secret = this.pool.secrets.get(keyId);
      if (!secret) {
        throw new Error('Challenge selected a key absent from the local export');
      }
      selectedSecrets.push(secret);
    }

    const ikm = Buffer.concat(selectedSecrets);
    const proofKey = hkdfSha256(
      ikm,
      challenge.nonce,
      hkdfContext(
        challenge.version,
        this.config.clientId,
        challenge.requestId,
        this.config.poolVersion,
        challenge.keyIds
      ),
      32
    );
    const message = proofMessage(
      challenge.version,
      this.config.clientId,
      challenge.requestId,
      challenge.nonce,
      challenge.expiresAt,
      method,
      target,
      bodyHash,
      this.config.poolVersion,
      challenge.keyIds
    );
    const proof = hmacSha256(proofKey, message);

    return this.transport.send({
      method,
      url: apiUrl.toString(),
      headers: {
        'X-MKAP-Version': '1',
        'X-MKAP-Client': this.config.clientId,
        'X-MKAP-Request': encodeBase64Url(challenge.requestId),
        'X-MKAP-Proof': encodeBase64Url(proof)
      },
      body: bodyBuffer
    });
  }

  parseChallenge(body) {
    let data;
    try {
      data = JSON.parse(body.toString('utf8'));
    } catch (error) {
      throw new Error('Challenge response is not valid JSON');
    }
    if (!data || typeof data !== 'object' || Array.isArray(data)) {
      throw new Error('Challenge response must be a JSON object');
    }
    const fields = ['version', 'request_id', 'nonce', 'key_ids', 'key_count', 'id_length', 'expires_in', 'expires_at'];
    for (const field of fields) {
      if (!Object.prototype.hasOwnProperty.call(data, field)) {
        throw new Error('Challenge response is missing field: ' + field);
      }
    }
    if (data.version !== 1
        || typeof data.request_id !== 'string'
        || typeof data.nonce !== 'string'
        || typeof data.key_ids !== 'string'
        || !Number.isInteger(data.key_count)
        || !Number.isInteger(data.id_length)
        || !Number.isInteger(data.expires_in)
        || !Number.isInteger(data.expires_at)
        || data.key_count < 1
        || data.key_count > this.pool.secrets.size
        || data.id_length !== this.pool.identifierLength
        || data.expires_in < 1
        || data.expires_at < 1
        || data.key_ids.length !== data.key_count * data.id_length) {
      throw new Error('Challenge response fields are invalid');
    }

    const requestId = decodeBase64Url(data.request_id, 16);
    const nonce = decodeBase64Url(data.nonce, 32);
    if (encodeBase64Url(requestId) !== data.request_id
        || encodeBase64Url(nonce) !== data.nonce) {
      throw new Error('Challenge binary fields must use canonical base64url');
    }
    const keyIds = [];
    for (let offset = 0; offset < data.key_ids.length; offset += data.id_length) {
      keyIds.push(data.key_ids.slice(offset, offset + data.id_length));
    }
    if (new Set(keyIds).size !== keyIds.length) {
      throw new Error('Challenge key identifiers are duplicated');
    }
    for (const keyId of keyIds) {
      for (const character of keyId) {
        if (!this.pool.identifierAlphabet.includes(character)) {
          throw new Error('Challenge key identifier contains an invalid character');
        }
      }
    }
    return {
      version: data.version,
      requestId,
      nonce,
      keyIds,
      expiresAt: data.expires_at
    };
  }
}

module.exports = {
  MkapClient
};
