# Known Limitations

- MKAP has not received an independent security review.
- Distributed multi-worker replay protection, shared rate limiting, and
  concurrent challenge consumption have not been verified.
- The fixed-window limiter is process-local and not suitable for
  multi-instance production deployments.
- The CLI environment/stdin master-key workflow requires deployment-specific
  secret-management review.
- Production HTTPS and request-size limits, and clean consumer-install
  workflows, have not been verified.
- Protected exports are unsupported by the PHP, JavaScript, and Flutter
  loaders, so protected export creation is disabled.
- Flutter secure storage is an adapter contract; hardware-backed storage has
  not been verified on Android or iOS.
- Browser JavaScript must not hold a complete secret pool.
