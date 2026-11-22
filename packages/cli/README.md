# MKAP Administrative CLI

The framework-independent CLI wraps the core and server packages. It uses
`MKAP_CLI_DSN`, `MKAP_CLI_DB_USERNAME`, `MKAP_CLI_DB_PASSWORD`, and
`MKAP_CLI_AUDIT_LOG`.

Examples:

```shell
php bin/mkap pool list
mkap client create CLIENT_001 --confirm
php bin/mkap pool create CLIENT_001 POOL_001 --count=1000 --selection=3 --dry-run
MKAP_CLI_MASTER_KEY_HEX=<64-hex-chars> php bin/mkap pool generate POOL_001 --confirm
php bin/mkap pool inspect POOL_001
php bin/mkap key revoke POOL_001 a8f31x --confirm
MKAP_CLI_MASTER_KEY_HEX=<64-hex-chars> php bin/mkap key rotate POOL_001 a8f31x --confirm
MKAP_CLI_MASTER_KEY_HEX=<64-hex-chars> php bin/mkap export create POOL_001 --output=exports/client.json --development-json --confirm
php bin/mkap export validate exports/client.json
php bin/mkap challenge cleanup --dry-run
php bin/mkap audit list --limit=50
```

Destructive operations require `--confirm` and do not run from `--dry-run`.
Secret values are never printed. Master-key values must not be supplied with
`--master-key-hex`; use `MKAP_CLI_MASTER_KEY_HEX` or `--master-key-stdin`.
Development exports are plaintext and must
remain development-only. Production exports should use the protected export
Protected export creation is intentionally disabled until every supported
client SDK implements authenticated-envelope opening. Production exports should use the protected export
mode and a securely provisioned external wrapping/master key.
