# Laravel Client

Package: `realmatrix/laravel-multikey-auth`

```bash
php artisan vendor:publish --tag=mkap-client-config
```

Configure `MKAP_BASE_URI`, `MKAP_CLIENT_ID`, `MKAP_POOL_VERSION`,
`MKAP_IDENTIFIER_LENGTH`, `MKAP_IDENTIFIER_ALPHABET`, and
`MKAP_EXPORT_PATH`.

Laravel dependency resolution and live Testbench integration remain blocked by
the environment TLS issue.
