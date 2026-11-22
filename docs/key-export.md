# Key Export

Plaintext JSON development exports contain the complete client secret pool.
Protect the file with OS permissions and remove it after use.

The CLI and Laravel export commands create development exports atomically with
`0600` intent. Protected AES-GCM export creation is currently disabled because
the PHP, JavaScript, and Flutter loaders do not yet implement interoperable
authenticated-envelope opening.

Never use a plaintext export in browser JavaScript.
