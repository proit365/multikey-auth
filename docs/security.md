# Security

Read [SECURITY.md](../SECURITY.md),
[SECURITY_REVIEW.md](../SECURITY_REVIEW.md),
[THREAT_MODEL.md](../THREAT_MODEL.md), and
[KNOWN_LIMITATIONS.md](../KNOWN_LIMITATIONS.md).

Deployment requirements:

- Use HTTPS.
- Use an external master-key provider.
- Do not place plaintext exports in web roots or logs.
- Use shared atomic rate limiting across instances.
- Preserve exact request method, target, and body bytes.
- Use restrictive export file permissions.
- Apply explicit confirmation to destructive administration.
- Obtain an independent security review before production use.
