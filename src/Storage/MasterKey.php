<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Storage;

final class MasterKey
{
    public function __construct(
        private readonly string $bytes,
        public readonly int $version = 1
    ) {
        if (strlen($bytes) !== 32) {
            throw new \InvalidArgumentException('The MKAP master key must be exactly 32 bytes.');
        }
        if ($version < 1) {
            throw new \InvalidArgumentException('The master key version must be positive.');
        }
    }

    public function bytes(): string
    {
        return $this->bytes;
    }
}
