# Key Generation

Use the PHP or Laravel pool APIs to create pools with secure random identifiers
and 32-byte secrets. The core pool generator prevents duplicate identifiers
and supports progress/cancellation callbacks.

The standalone CLI supports:

```bash
php packages/cli/bin/mkap pool create CLIENT_001 POOL_001 --count=1000 --selection=3 --dry-run
MKAP_CLI_MASTER_KEY_HEX=<64-hex-chars> php packages/cli/bin/mkap pool generate POOL_001 --confirm
```

The CLI never prints secret values. Master keys must come from an external
secret-management process or `--master-key-stdin`.
