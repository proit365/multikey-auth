<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Server;

use ProIT365\MultiKeyAuth\Core\Protocol\RequestMetadataValidator;

final class ChallengeRequest
{
    public function __construct(
        public readonly int $version,
        public readonly string $clientIdentifier,
        public readonly string $method,
        public readonly string $target,
        public readonly string $bodySha256
    ) {
        if ($version !== 1) {
            throw new \InvalidArgumentException('Unsupported protocol version.');
        }
        RequestMetadataValidator::clientIdentifier($clientIdentifier);
        RequestMetadataValidator::method($method);
        RequestMetadataValidator::target($target);
        RequestMetadataValidator::bodySha256($bodySha256);
    }
}
