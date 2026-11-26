# Laravel Client

Package: `proit365/laravel-multikey-auth`

```bash
php artisan vendor:publish --tag=mkap-client-config
```

Configure `MKAP_BASE_URI`, `MKAP_CLIENT_ID`, `MKAP_POOL_VERSION`,
`MKAP_IDENTIFIER_LENGTH`, `MKAP_IDENTIFIER_ALPHABET`, and
`MKAP_EXPORT_PATH`.

The published Laravel client package v1.0.1 passed its Composer test suite on
2026-11-26 (5 tests, 9 assertions), including a live request to the Laravel
test server that received HTTP 200 from the protected endpoint. This verifies
the tested package flow, not production TLS or deployment configuration.
