<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Storage;

interface KeyRepositoryInterface
{
    public function storeKey(string $poolId, KeyRecord $record): void;

    public function findActiveByIdentifiers(string $poolId, array $identifiers): array;

    public function findActiveIdentifierAt(string $poolId, int $offset): ?string;

    public function revokeKey(string $poolId, string $identifier, int $timestamp): void;

    public function countActive(string $poolId): int;
}
