<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Key;

final class KeyRotation
{
    public function __construct(
        public readonly string $poolId,
        public readonly string $oldKeyId,
        public readonly string $newKeyId,
        public readonly string $reason,
        public readonly int $createdAt
    ) {
        if ($oldKeyId === '' || $newKeyId === '' || $poolId === '') {
            throw new \InvalidArgumentException('Key rotation identifiers must not be empty.');
        }
        if ($reason === '') {
            throw new \InvalidArgumentException('Rotation reason must not be empty.');
        }
        if ($createdAt < 0) {
            throw new \InvalidArgumentException('Rotation timestamp must not be negative.');
        }
    }
}
