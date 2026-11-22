<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Key;

use ProIT365\MultiKeyAuth\Core\Random\RandomGeneratorInterface;

final class KeyLifecycle
{
    public function __construct(
        private readonly RandomGeneratorInterface $random,
        private readonly IdentifierGenerator $identifierGenerator
    ) {
    }

    public function revoke(ManagedKey $key, int $timestamp): void
    {
        $key->revoke($timestamp);
    }

    public function replace(array $keys, ManagedKey $oldKey, int $timestamp): ManagedKey
    {
        if (!$oldKey->isActive()) {
            throw new \LogicException('Only active keys can be replaced.');
        }

        $identifiers = array_map(
            static fn (ManagedKey $key): string => $key->identifier,
            $keys
        );
        $newIdentifier = $this->identifierGenerator->generate($identifiers);
        $oldKey->revoke($timestamp);

        return new ManagedKey(
            $newIdentifier,
            new KeyMaterial($this->random->bytes(32))
        );
    }
}
