<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Server;

final class SystemClock implements ClockInterface
{
    public function now(): int
    {
        return time();
    }
}
