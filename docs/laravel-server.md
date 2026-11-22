# Laravel Server

Package: `proit365/laravel-multikey-auth-server`

```bash
php artisan vendor:publish --tag=mkap-config
php artisan migrate
```

Challenge routes are opt-in. Attach `multikey.auth` only to routes that require
authentication. Configure an external master-key provider and a shared Laravel
cache for rate limiting in multi-instance deployments.
