# Known Limitations

- MKAP has not received an independent security review.
- PHPUnit and Laravel/Testbench dependencies remain blocked by environment TLS
  certificate verification.
- Live MySQL, mysqli, PDO/MySQL challenge, Laravel HTTP, and distributed
  concurrent challenge-consumption tests were not executed.
- JavaScript and Flutter live HTTP authentication against PHP is pending.
- Protected exports cannot be opened by the PHP, JavaScript, or Flutter loaders,
  so protected export creation is disabled.
- The fixed-window limiter is process-local and not suitable for
  multi-instance production deployments.
- The CLI environment/stdin master-key workflow requires deployment-specific
  secret-management review.
- Flutter secure storage is an adapter contract, not a verified
  hardware-backed implementation.
- Browser JavaScript must not hold a complete secret pool.
