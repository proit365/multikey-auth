<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Random;

interface RandomGeneratorInterface
{
    public function bytes(int $length): string;
}
