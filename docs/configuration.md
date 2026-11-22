# Configuration

## PHP and server

- Challenge lifetime: 1–3600 seconds.
- Challenge maximum body bytes: framework/transport-configured.
- Authenticated maximum body bytes: default 1 MiB.
- Identifier length: 1–256 characters.
- Selection count: 1 through pool key count.
- Master key: external versioned 32-byte value.

## Laravel server

- `database_connection`
- `master_key_provider`
- `client_pool_provider`
- `challenge_lifetime_seconds`
- `maximum_challenge_body_bytes`
- `maximum_authenticated_body_bytes`
- `rate_limits.challenge`
- `rate_limits.authenticate`
- `rate_limits.decay_seconds`
- `routes.enabled`
- `routes.challenge_path`
- `routes.middleware`

## Laravel client

- `base_uri`
- `client_id`
- `pool_version`
- `identifier_length`
- `identifier_alphabet`
- `export_path`
- `require_https`
- `maximum_challenge_response_bytes`
