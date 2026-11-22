<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Server;

final class InMemoryClientPoolProvider implements ClientPoolProviderInterface
{
    private array $pools = [];

    public function add(ClientPool $pool): void
    {
        $this->pools[$pool->clientIdentifier] = $pool;
    }

    public function findActivePool(string $clientIdentifier): ?ClientPool
    {
        return $this->pools[$clientIdentifier] ?? null;
    }
}
