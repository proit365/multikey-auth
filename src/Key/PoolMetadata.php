<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Key;

final class PoolMetadata
{
    public function __construct(
        public readonly string $poolId,
        public readonly int $poolVersion,
        public readonly int $identifierLength,
        public readonly string $identifierAlphabet,
        public readonly int $keyCount,
        public readonly int $selectionCount
    ) {
        if ($poolId === '') {
            throw new \InvalidArgumentException('Pool identifier must not be empty.');
        }
        if ($poolVersion < 1) {
            throw new \InvalidArgumentException('Pool version must be positive.');
        }
        if ($identifierLength < 1 || $identifierLength > 256) {
            throw new \InvalidArgumentException('Identifier length is outside the supported range.');
        }
        if ($identifierAlphabet === '' || strlen($identifierAlphabet) !== count(array_unique(str_split($identifierAlphabet)))) {
            throw new \InvalidArgumentException('Identifier alphabet is invalid.');
        }
        if ($keyCount < 1) {
            throw new \InvalidArgumentException('Pool key count must be positive.');
        }
        if ($selectionCount < 1 || $selectionCount > $keyCount) {
            throw new \InvalidArgumentException('Selection count must fit within the pool.');
        }
    }
}
