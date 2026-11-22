# FAQ

## Is MKAP production-ready?

No. It is an initial development implementation without an independent
security review or complete live interoperability validation.

## Does the protocol transmit secret keys?

No. The challenge and request transport only contain public identifiers,
binary random values, and the HMAC proof.

## Why are protected exports disabled?

The core can create authenticated export envelopes, but all client loaders
do not yet open those envelopes. Protected export creation is disabled to avoid
an incompatible or unusable export format.

## Is Flutter secure storage hardware-backed?

Not by default. The package provides an adapter contract; actual platform
storage and hardware guarantees depend on plugin and device configuration.
