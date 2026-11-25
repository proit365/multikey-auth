# Known Limitations

- MKAP has not received an independent security review.
- Source-package Laravel Testbench feature suites were not run; these are
  separate from the live application checks in the framework test workspace.
- Live MySQL, mysqli, PDO/MySQL challenge, and distributed concurrent
  challenge-consumption tests were not executed.
- Protected exports cannot be opened by the PHP, JavaScript, or Flutter loaders,
  so protected export creation is disabled.
- The fixed-window limiter is process-local and not suitable for
  multi-instance production deployments.
- The CLI environment/stdin master-key workflow requires deployment-specific
  secret-management review.
- Flutter secure storage is an adapter contract, not a verified
  hardware-backed implementation.
- Browser JavaScript must not hold a complete secret pool.
