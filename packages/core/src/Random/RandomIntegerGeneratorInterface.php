<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Random;

interface RandomIntegerGeneratorInterface
{
    public function integer(int $minimum, int $maximum): int;
}
