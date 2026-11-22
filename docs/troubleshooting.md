# Troubleshooting

## Composer TLS certificate failure

Do not disable certificate validation. Resolve the local trusted CA/configuration
and retry without `secure-http=false`.

## Challenge response failure

Verify the protocol version, body SHA-256, origin-form target, exact body bytes,
and that the challenge response is served over a size-bounded transport.

## Key missing in challenge

The challenge selected a key absent from the active export or key pool. Reissue
an export after revocation/rotation.

## Replay or 409

Each challenge authorizes one request tuple once. Re-send the challenge request
before generating a new proof.
