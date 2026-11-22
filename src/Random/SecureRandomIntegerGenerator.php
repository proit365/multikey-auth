<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Random;

final class SecureRandomIntegerGenerator implements RandomIntegerGeneratorInterface
{
    public function integer(int $minimum, int $maximum): int
    {
        if ($minimum > $maximum) {
            throw new \InvalidArgumentException('Random integer range is invalid.');
        }

        return random_int($minimum, $maximum);
    }
}
