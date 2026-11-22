<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Server;

interface ClientPoolProviderInterface
{
    public function findActivePool(string $clientIdentifier): ?ClientPool;
}
