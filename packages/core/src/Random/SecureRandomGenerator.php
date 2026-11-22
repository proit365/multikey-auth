<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Random;

final class SecureRandomGenerator implements RandomGeneratorInterface
{
    public function bytes(int $length): string
    {
        if ($length < 1 || $length > 1_048_576) {
            throw new \InvalidArgumentException('Random byte length is outside the supported range.');
        }

        return random_bytes($length);
    }
}
