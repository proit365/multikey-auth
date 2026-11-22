<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Server;

use ProIT365\MultiKeyAuth\Core\Key\PoolMetadata;

final class ClientPool
{
    public function __construct(
        public readonly int $clientRecordId,
        public readonly int $poolRecordId,
        public readonly string $clientIdentifier,
        public readonly PoolMetadata $metadata
    ) {
        if ($clientRecordId < 1 || $poolRecordId < 1) {
            throw new \InvalidArgumentException('Client and pool record identifiers must be positive.');
        }
        if ($clientIdentifier === '') {
            throw new \InvalidArgumentException('Client identifier must not be empty.');
        }
    }
}
