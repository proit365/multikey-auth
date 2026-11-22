<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Server;

final class AuthenticatedRequest
{
    public function __construct(
        public readonly string $clientIdentifier,
        public readonly string $requestIdentifier,
        public readonly string $proof,
        public readonly string $method,
        public readonly string $target,
        public readonly string $body,
        public readonly int $protocolVersion = 1
    ) {
        if (strlen($requestIdentifier) !== 16 || strlen($proof) !== 32) {
            throw new \InvalidArgumentException('Authentication header binary values are malformed.');
        }
    }
}
