<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Server;

interface RateLimiterInterface
{
    public function allow(string $clientIdentifier, string $operation): bool;
}
