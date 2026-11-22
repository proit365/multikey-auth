export interface HttpResponse {
  statusCode: number;
  headers: { [name: string]: string | string[] | undefined };
  body: Buffer;
}

export interface HttpRequest {
  method: string;
  url: string;
  headers: { [name: string]: string };
  body: Buffer;
}

export interface HttpTransport {
  send(request: HttpRequest): Promise<HttpResponse>;
}

export interface MkapClientConfig {
  baseUri: string;
  clientId: string;
  poolVersion: number;
  identifierLength: number;
  identifierAlphabet: string;
  exportJson?: string;
  exportPath?: string;
  maximumChallengeResponseBytes?: number;
  requireHttps?: boolean;
}

export class MkapClient {
  constructor(config: MkapClientConfig, transport?: HttpTransport);
  request(method: string, target: string, body?: Buffer | string): Promise<HttpResponse>;
  parseChallenge(body: Buffer): {
    version: number;
    requestId: Buffer;
    nonce: Buffer;
    keyIds: string[];
    expiresAt: number;
  };
}

export interface ExportPool {
  clientId: string;
  poolId: string;
  poolVersion: number;
  identifierLength: number;
  identifierAlphabet: string;
  secrets: Map<string, Buffer>;
}

export function loadExportJson(json: string): ExportPool;
export function loadExportFile(path: string): ExportPool;
export function validateExport(manifest: unknown): ExportPool;
export function encodeBase64Url(buffer: Buffer): string;
export function decodeBase64Url(value: string, exactLength?: number): Buffer;
export function hkdfSha256(
  ikm: Buffer,
  salt: Buffer,
  info: Buffer,
  length: number
): Buffer;
export function hkdfContext(
  protocolVersion: number,
  clientId: string,
  requestId: Buffer,
  poolVersion: number,
  keyIds: string[]
): Buffer;
export function proofMessage(
  protocolVersion: number,
  clientId: string,
  requestId: Buffer,
  nonce: Buffer,
  expiresAt: number,
  method: string,
  target: string,
  bodySha256: Buffer,
  poolVersion: number,
  keyIds: string[]
): Buffer;
export function hmacSha256(key: Buffer, message: Buffer): Buffer;
