<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Server;

interface ChallengeRepositoryInterface
{
    public function save(ChallengeRecord $challenge): void;

    public function findByRequestIdentifier(string $requestIdentifier): ?ChallengeRecord;

    public function consumeIfPending(string $requestIdentifier, int $now): bool;
}
