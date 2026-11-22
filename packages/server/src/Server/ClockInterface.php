<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Server;

interface ClockInterface
{
    public function now(): int;
}
