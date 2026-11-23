<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Client;

final class ClientKeyPool
{
    private readonly array $secretsByIdentifier;

    public function __construct(
        public readonly string $clientIdentifier,
        public readonly string $poolIdentifier,
        public readonly int $poolVersion,
        public readonly int $identifierLength,
        public readonly string $identifierAlphabet,
        array $secretsByIdentifier
    ) {
        if ($clientIdentifier === '' || $poolIdentifier === '' || $poolVersion < 1) {
            throw new \InvalidArgumentException('Export client or pool metadata is invalid.');
        }
        if ($identifierLength < 1 || $identifierLength > 256) {
            throw new \InvalidArgumentException('Export identifier length is invalid.');
        }
        if ($identifierAlphabet === ''
            || strlen($identifierAlphabet) !== count(array_unique(str_split($identifierAlphabet)))) {
            throw new \InvalidArgumentException('Export identifier alphabet is invalid.');
        }
        if ($secretsByIdentifier === []) {
            throw new \InvalidArgumentException('Export contains no key material.');
        }
        $normalizedSecretsByIdentifier = [];
        foreach ($secretsByIdentifier as $identifier => $secret) {
            if (!is_string($identifier) && !is_int($identifier)) {
                throw new \InvalidArgumentException('Export key metadata or secret length is invalid.');
            }
            $identifier = (string) $identifier;
            if (strlen($identifier) !== $identifierLength
                || !is_string($secret)
                || strlen($secret) !== 32) {
                throw new \InvalidArgumentException('Export key metadata or secret length is invalid.');
            }
            $normalizedSecretsByIdentifier['id:' . $identifier] = $secret;
        }
        $this->secretsByIdentifier = $normalizedSecretsByIdentifier;
    }

    public function secretFor(string $identifier): ?string
    {
        return $this->secretsByIdentifier['id:' . $identifier] ?? null;
    }

    public function keyCount(): int
    {
        return count($this->secretsByIdentifier);
    }
}
