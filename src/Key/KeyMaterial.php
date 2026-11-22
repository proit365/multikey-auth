<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Key;

final class KeyMaterial
{
    public function __construct(private readonly string $secret)
    {
        if (strlen($secret) !== 32) {
            throw new \InvalidArgumentException('A MKAP secret key must be exactly 32 bytes.');
        }
    }

    public function bytes(): string
    {
        return $this->secret;
    }

    public function length(): int
    {
        return strlen($this->secret);
    }
}
