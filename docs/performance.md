# Performance

The local Phase 13 benchmark on PHP 8.2.17 measured:

- 10,000 HMAC-SHA256 operations in 0.019399 seconds.
- 515,485 operations per second.
- A 96,000-byte simulated export allocation in 0.000030 seconds.

These values are environment-specific smoke measurements, not production
capacity guarantees. Pool generation and database benchmarks for 10,000,
100,000, and 1,000,000 keys are not complete live measurements.
