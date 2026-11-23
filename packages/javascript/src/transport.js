'use strict';

const http = require('http');
const https = require('https');
const { URL } = require('url');

function requestUrl(baseUri, target) {
  if (typeof baseUri !== 'string' || typeof target !== 'string') {
    throw new TypeError('Base URI and target must be strings');
  }
  const baseUrl = new URL(baseUri);
  const url = new URL(target, baseUrl);
  if (!/^https?:$/.test(url.protocol)) {
    throw new Error('Unsupported transport protocol');
  }
  if (url.origin !== baseUrl.origin) {
    throw new Error('Request target must remain on the base origin');
  }
  if (url.username || url.password || baseUrl.username || baseUrl.password) {
    throw new Error('Transport URLs must not contain credentials');
  }
  if (url.pathname + url.search !== target) {
    throw new Error('Request target is not canonical for the transport');
  }
  return url;
}

class NodeHttpTransport {
  constructor(options) {
    this.options = options && typeof options === 'object' ? options : {};
  }

  send(request) {
    return new Promise((resolve, reject) => {
      let url;
      try {
        url = new URL(request.url);
      } catch (error) {
        reject(error);
        return;
      }
      if (url.protocol !== 'https:' && url.protocol !== 'http:') {
        reject(new Error('Unsupported transport protocol'));
        return;
      }
      if (url.username || url.password) {
        reject(new Error('Transport URLs must not contain credentials'));
        return;
      }
      if (this.options.requireHttps !== false && url.protocol !== 'https:') {
        reject(new Error('HTTPS is required'));
        return;
      }

      const body = Buffer.isBuffer(request.body)
        ? request.body
        : Buffer.from(request.body || '', 'utf8');
      const transport = url.protocol === 'https:' ? https : http;
      const outgoing = transport.request({
        protocol: url.protocol,
        hostname: url.hostname,
        port: url.port || undefined,
        method: request.method,
        path: url.pathname + url.search,
        headers: Object.assign({
          'Content-Length': body.length
        }, request.headers || {})
      }, (incoming) => {
        const chunks = [];
        incoming.on('data', (chunk) => chunks.push(Buffer.from(chunk)));
        incoming.on('end', () => resolve({
          statusCode: incoming.statusCode,
          headers: incoming.headers,
          body: Buffer.concat(chunks)
        }));
      });
      outgoing.on('error', reject);
      outgoing.end(body);
    });
  }
}

module.exports = {
  requestUrl,
  NodeHttpTransport
};
