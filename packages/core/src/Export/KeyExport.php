<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Export;

final class KeyExport
{
    public function __construct(
        public readonly int $formatVersion,
        public readonly int $protocolVersion,
        public readonly string $clientId,
        public readonly string $poolId,
        public readonly int $poolVersion,
        public readonly int $exportVersion,
        public readonly string $createdAt,
        public readonly int $identifierLength,
        public readonly string $identifierAlphabet,
        public readonly int $secretLengthBytes,
        public readonly array $keys
    ) {
        if ($formatVersion !== 1 || $protocolVersion !== 1) {
            throw new \InvalidArgumentException('Unsupported export or protocol version.');
        }
        if ($clientId === '' || $poolId === '' || $createdAt === '') {
            throw new \InvalidArgumentException('Required export metadata is missing.');
        }
        if ($poolVersion < 1 || $exportVersion < 1) {
            throw new \InvalidArgumentException('Pool and export versions must be positive.');
        }
        if ($identifierLength < 1 || $identifierLength > 256) {
            throw new \InvalidArgumentException('Identifier length is outside the supported range.');
        }
        if ($identifierAlphabet === '' || strlen($identifierAlphabet) !== count(array_unique(str_split($identifierAlphabet)))) {
            throw new \InvalidArgumentException('Invalid identifier alphabet.');
        }
        if ($secretLengthBytes !== 32) {
            throw new \InvalidArgumentException('Secret length must be 32 bytes.');
        }
        if ($keys === []) {
            throw new \InvalidArgumentException('Key export must contain at least one key.');
        }
    }

    public function manifest(): array
    {
        return [
            'format_version' => $this->formatVersion,
            'protocol_version' => $this->protocolVersion,
            'client_id' => $this->clientId,
            'pool_id' => $this->poolId,
            'pool_version' => $this->poolVersion,
            'export_version' => $this->exportVersion,
            'created_at' => $this->createdAt,
            'identifier_length' => $this->identifierLength,
            'identifier_alphabet' => $this->identifierAlphabet,
            'secret_length_bytes' => $this->secretLengthBytes,
            'key_count' => count($this->keys),
            'keys' => $this->keys,
        ];
    }
}
