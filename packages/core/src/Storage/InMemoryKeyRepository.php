<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Storage;

use ProIT365\MultiKeyAuth\Core\Key\KeyStatus;

final class InMemoryKeyRepository implements KeyRepositoryInterface
{
    private array $keys = [];

    public function storeKey(string $poolId, KeyRecord $record): void
    {
        $this->keys[$poolId][$record->identifier] = $record;
    }

    public function findActiveByIdentifiers(string $poolId, array $identifiers): array
    {
        $matches = [];
        foreach ($identifiers as $identifier) {
            $record = $this->keys[$poolId][$identifier] ?? null;
            if ($record instanceof KeyRecord && $record->status === KeyStatus::Active) {
                $matches[$identifier] = $record;
            }
        }
        return $matches;
    }

    public function findActiveIdentifierAt(string $poolId, int $offset): ?string
    {
        if ($offset < 0) {
            throw new \InvalidArgumentException('Key offset must not be negative.');
        }

        $identifiers = [];
        foreach ($this->keys[$poolId] ?? [] as $identifier => $record) {
            if ($record->status === KeyStatus::Active) {
                $identifiers[] = $identifier;
            }
        }
        sort($identifiers, SORT_STRING);
        return $identifiers[$offset] ?? null;
    }

    public function revokeKey(string $poolId, string $identifier, int $timestamp): void
    {
        $record = $this->keys[$poolId][$identifier] ?? null;
        if (!$record instanceof KeyRecord) {
            throw new \RuntimeException('Unknown key identifier.');
        }
        $this->keys[$poolId][$identifier] = new KeyRecord(
            $record->identifier,
            $record->encryptedSecret,
            KeyStatus::Revoked,
            $timestamp
        );
    }

    public function countActive(string $poolId): int
    {
        $count = 0;
        foreach ($this->keys[$poolId] ?? [] as $record) {
            if ($record->status === KeyStatus::Active) {
                $count++;
            }
        }
        return $count;
    }
}
