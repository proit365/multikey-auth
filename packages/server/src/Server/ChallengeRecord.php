<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Server;

final class ChallengeRecord
{
    public function __construct(
        public readonly string $requestIdentifier,
        public readonly int $protocolVersion,
        public readonly int $clientRecordId,
        public readonly string $clientIdentifier,
        public readonly int $poolRecordId,
        public readonly string $poolIdentifier,
        public readonly int $poolVersion,
        public readonly string $nonce,
        public readonly array $keyIdentifiers,
        public readonly int $identifierLength,
        public readonly string $method,
        public readonly string $target,
        public readonly string $bodySha256,
        public readonly int $createdAt,
        public readonly int $expiresAt,
        public readonly string $status = 'pending',
        public readonly ?int $consumedAt = null
    ) {
        if (strlen($requestIdentifier) !== 16 || strlen($nonce) !== 32 || strlen($bodySha256) !== 32) {
            throw new \InvalidArgumentException('Challenge binary fields have invalid lengths.');
        }
        if ($keyIdentifiers === [] || count($keyIdentifiers) > 0xffffffff) {
            throw new \InvalidArgumentException('Challenge key selection is invalid.');
        }
        if ($identifierLength < 1 || $expiresAt <= $createdAt || $poolVersion < 1) {
            throw new \InvalidArgumentException('Challenge metadata is invalid.');
        }
        $seen = [];
        foreach ($keyIdentifiers as $identifier) {
            if (!is_string($identifier) || strlen($identifier) !== $identifierLength) {
                throw new \InvalidArgumentException('Challenge key identifier length is invalid.');
            }
            if (isset($seen[$identifier])) {
                throw new \InvalidArgumentException('Challenge contains duplicate key identifiers.');
            }
            $seen[$identifier] = true;
        }
    }
}
