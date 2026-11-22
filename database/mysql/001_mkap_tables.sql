START TRANSACTION;

CREATE TABLE IF NOT EXISTS mkap_clients (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_identifier VARCHAR(128) NOT NULL,
  name VARCHAR(255) NOT NULL,
  status VARCHAR(32) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mkap_clients_identifier (client_identifier)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mkap_key_pools (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id BIGINT UNSIGNED NOT NULL,
  pool_identifier VARCHAR(128) NOT NULL,
  pool_version INT UNSIGNED NOT NULL,
  identifier_length SMALLINT UNSIGNED NOT NULL,
  identifier_alphabet VARCHAR(256) NOT NULL,
  secret_length_bytes SMALLINT UNSIGNED NOT NULL,
  key_count INT UNSIGNED NOT NULL,
  selection_count INT UNSIGNED NOT NULL,
  status VARCHAR(32) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mkap_pools_client_version (client_id, pool_identifier, pool_version),
  CONSTRAINT fk_mkap_pools_client FOREIGN KEY (client_id)
    REFERENCES mkap_clients(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mkap_authentication_keys (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pool_id BIGINT UNSIGNED NOT NULL,
  public_identifier VARCHAR(256) NOT NULL,
  encrypted_secret VARBINARY(4096) NOT NULL,
  encryption_key_version INT UNSIGNED NOT NULL,
  status VARCHAR(32) NOT NULL,
  generation_batch_id VARCHAR(128) NULL,
  created_at DATETIME(6) NOT NULL,
  revoked_at DATETIME(6) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mkap_keys_pool_identifier (pool_id, public_identifier),
  KEY idx_mkap_keys_pool_status_identifier (pool_id, status, public_identifier),
  CONSTRAINT fk_mkap_keys_pool FOREIGN KEY (pool_id)
    REFERENCES mkap_key_pools(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mkap_key_exports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pool_id BIGINT UNSIGNED NOT NULL,
  export_version INT UNSIGNED NOT NULL,
  format_version INT UNSIGNED NOT NULL,
  key_count INT UNSIGNED NOT NULL,
  checksum VARBINARY(64) NOT NULL,
  status VARCHAR(32) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  revoked_at DATETIME(6) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mkap_exports_pool_version (pool_id, export_version),
  CONSTRAINT fk_mkap_exports_pool FOREIGN KEY (pool_id)
    REFERENCES mkap_key_pools(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mkap_key_generation_batches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pool_id BIGINT UNSIGNED NOT NULL,
  batch_identifier VARCHAR(128) NOT NULL,
  requested_count INT UNSIGNED NOT NULL,
  generated_count INT UNSIGNED NOT NULL,
  failed_count INT UNSIGNED NOT NULL,
  status VARCHAR(32) NOT NULL,
  started_at DATETIME(6) NOT NULL,
  completed_at DATETIME(6) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mkap_batches_identifier (batch_identifier),
  KEY idx_mkap_batches_pool_status (pool_id, status),
  CONSTRAINT fk_mkap_batches_pool FOREIGN KEY (pool_id)
    REFERENCES mkap_key_pools(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mkap_authentication_challenges (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_identifier VARBINARY(16) NOT NULL,
  client_id BIGINT UNSIGNED NOT NULL,
  pool_id BIGINT UNSIGNED NOT NULL,
  pool_version INT UNSIGNED NOT NULL,
  nonce VARBINARY(32) NOT NULL,
  selected_key_identifiers TEXT NOT NULL,
  http_method VARCHAR(32) NOT NULL,
  request_target VARCHAR(4096) NOT NULL,
  body_sha256 VARBINARY(32) NOT NULL,
  expires_at DATETIME(6) NOT NULL,
  consumed_at DATETIME(6) NULL,
  status VARCHAR(32) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mkap_challenges_request (request_identifier),
  KEY idx_mkap_challenges_expiry_status (expires_at, status),
  CONSTRAINT fk_mkap_challenges_client FOREIGN KEY (client_id)
    REFERENCES mkap_clients(id),
  CONSTRAINT fk_mkap_challenges_pool FOREIGN KEY (pool_id)
    REFERENCES mkap_key_pools(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mkap_authentication_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id BIGINT UNSIGNED NULL,
  challenge_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(64) NOT NULL,
  result VARCHAR(32) NOT NULL,
  failure_reason VARCHAR(255) NULL,
  ip_address VARBINARY(16) NULL,
  created_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_mkap_logs_client_created (client_id, created_at),
  CONSTRAINT fk_mkap_logs_client FOREIGN KEY (client_id)
    REFERENCES mkap_clients(id),
  CONSTRAINT fk_mkap_logs_challenge FOREIGN KEY (challenge_id)
    REFERENCES mkap_authentication_challenges(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mkap_key_rotations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pool_id BIGINT UNSIGNED NOT NULL,
  old_key_id BIGINT UNSIGNED NOT NULL,
  new_key_id BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(255) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_mkap_rotations_pool_created (pool_id, created_at),
  CONSTRAINT fk_mkap_rotations_pool FOREIGN KEY (pool_id)
    REFERENCES mkap_key_pools(id),
  CONSTRAINT fk_mkap_rotations_old FOREIGN KEY (old_key_id)
    REFERENCES mkap_authentication_keys(id),
  CONSTRAINT fk_mkap_rotations_new FOREIGN KEY (new_key_id)
    REFERENCES mkap_authentication_keys(id)
) ENGINE=InnoDB;

COMMIT;
