PRAGMA foreign_keys = ON;

BEGIN;

CREATE TABLE IF NOT EXISTS mkap_clients (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  client_identifier TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  status TEXT NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS mkap_key_pools (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  client_id INTEGER NOT NULL,
  pool_identifier TEXT NOT NULL,
  pool_version INTEGER NOT NULL CHECK (pool_version > 0),
  identifier_length INTEGER NOT NULL CHECK (identifier_length > 0),
  identifier_alphabet TEXT NOT NULL,
  secret_length_bytes INTEGER NOT NULL CHECK (secret_length_bytes = 32),
  key_count INTEGER NOT NULL CHECK (key_count > 0),
  selection_count INTEGER NOT NULL CHECK (selection_count > 0 AND selection_count <= key_count),
  status TEXT NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  UNIQUE (client_id, pool_identifier, pool_version),
  FOREIGN KEY (client_id) REFERENCES mkap_clients(id)
);

CREATE TABLE IF NOT EXISTS mkap_authentication_keys (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  pool_id INTEGER NOT NULL,
  public_identifier TEXT NOT NULL,
  encrypted_secret BLOB NOT NULL,
  encryption_key_version INTEGER NOT NULL CHECK (encryption_key_version > 0),
  status TEXT NOT NULL,
  generation_batch_id TEXT NULL,
  created_at TEXT NOT NULL,
  revoked_at TEXT NULL,
  UNIQUE (pool_id, public_identifier),
  FOREIGN KEY (pool_id) REFERENCES mkap_key_pools(id)
);
CREATE INDEX IF NOT EXISTS idx_mkap_keys_pool_status
  ON mkap_authentication_keys(pool_id, status, public_identifier);

CREATE TABLE IF NOT EXISTS mkap_key_exports (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  pool_id INTEGER NOT NULL,
  export_version INTEGER NOT NULL,
  format_version INTEGER NOT NULL,
  key_count INTEGER NOT NULL,
  checksum BLOB NOT NULL,
  status TEXT NOT NULL,
  created_at TEXT NOT NULL,
  revoked_at TEXT NULL,
  UNIQUE (pool_id, export_version),
  FOREIGN KEY (pool_id) REFERENCES mkap_key_pools(id)
);

CREATE TABLE IF NOT EXISTS mkap_key_generation_batches (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  pool_id INTEGER NOT NULL,
  batch_identifier TEXT NOT NULL UNIQUE,
  requested_count INTEGER NOT NULL,
  generated_count INTEGER NOT NULL,
  failed_count INTEGER NOT NULL,
  status TEXT NOT NULL,
  started_at TEXT NOT NULL,
  completed_at TEXT NULL,
  FOREIGN KEY (pool_id) REFERENCES mkap_key_pools(id)
);
CREATE INDEX IF NOT EXISTS idx_mkap_batches_pool_status
  ON mkap_key_generation_batches(pool_id, status);

CREATE TABLE IF NOT EXISTS mkap_authentication_challenges (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  request_identifier BLOB NOT NULL UNIQUE CHECK (length(request_identifier) = 16),
  client_id INTEGER NOT NULL,
  pool_id INTEGER NOT NULL,
  pool_version INTEGER NOT NULL,
  nonce BLOB NOT NULL CHECK (length(nonce) = 32),
  selected_key_identifiers TEXT NOT NULL,
  http_method TEXT NOT NULL,
  request_target TEXT NOT NULL,
  body_sha256 BLOB NOT NULL CHECK (length(body_sha256) = 32),
  expires_at TEXT NOT NULL,
  consumed_at TEXT NULL,
  status TEXT NOT NULL,
  created_at TEXT NOT NULL,
  FOREIGN KEY (client_id) REFERENCES mkap_clients(id),
  FOREIGN KEY (pool_id) REFERENCES mkap_key_pools(id)
);
CREATE INDEX IF NOT EXISTS idx_mkap_challenges_expiry_status
  ON mkap_authentication_challenges(expires_at, status);

CREATE TABLE IF NOT EXISTS mkap_authentication_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  client_id INTEGER NULL,
  challenge_id INTEGER NULL,
  event_type TEXT NOT NULL,
  result TEXT NOT NULL,
  failure_reason TEXT NULL,
  ip_address BLOB NULL,
  created_at TEXT NOT NULL,
  FOREIGN KEY (client_id) REFERENCES mkap_clients(id),
  FOREIGN KEY (challenge_id) REFERENCES mkap_authentication_challenges(id)
);
CREATE INDEX IF NOT EXISTS idx_mkap_logs_client_created
  ON mkap_authentication_logs(client_id, created_at);

CREATE TABLE IF NOT EXISTS mkap_key_rotations (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  pool_id INTEGER NOT NULL,
  old_key_id INTEGER NOT NULL,
  new_key_id INTEGER NOT NULL,
  reason TEXT NOT NULL,
  created_at TEXT NOT NULL,
  FOREIGN KEY (pool_id) REFERENCES mkap_key_pools(id),
  FOREIGN KEY (old_key_id) REFERENCES mkap_authentication_keys(id),
  FOREIGN KEY (new_key_id) REFERENCES mkap_authentication_keys(id)
);
CREATE INDEX IF NOT EXISTS idx_mkap_rotations_pool_created
  ON mkap_key_rotations(pool_id, created_at);

COMMIT;
