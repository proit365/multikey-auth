<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Cryptography;

final class BodyHasher
{
    public function sha256(string $body): string
    {
        return hash('sha256', $body, true);
    }

    public function sha256Hex(string $body): string
    {
        return hash('sha256', $body);
    }
}
