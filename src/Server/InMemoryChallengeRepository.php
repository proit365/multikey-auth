<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Server;

final class InMemoryChallengeRepository implements ChallengeRepositoryInterface
{
    private array $challenges = [];

    public function save(ChallengeRecord $challenge): void
    {
        $key = bin2hex($challenge->requestIdentifier);
        if (isset($this->challenges[$key])) {
            throw new \RuntimeException('Challenge identifier already exists.');
        }
        $this->challenges[$key] = $challenge;
    }

    public function findByRequestIdentifier(string $requestIdentifier): ?ChallengeRecord
    {
        return $this->challenges[bin2hex($requestIdentifier)] ?? null;
    }

    public function consumeIfPending(string $requestIdentifier, int $now): bool
    {
        $key = bin2hex($requestIdentifier);
        $challenge = $this->challenges[$key] ?? null;
        if (!$challenge instanceof ChallengeRecord
            || $challenge->status !== 'pending'
            || $challenge->expiresAt <= $now) {
            return false;
        }

        $this->challenges[$key] = new ChallengeRecord(
            $challenge->requestIdentifier,
            $challenge->protocolVersion,
            $challenge->clientRecordId,
            $challenge->clientIdentifier,
            $challenge->poolRecordId,
            $challenge->poolIdentifier,
            $challenge->poolVersion,
            $challenge->nonce,
            $challenge->keyIdentifiers,
            $challenge->identifierLength,
            $challenge->method,
            $challenge->target,
            $challenge->bodySha256,
            $challenge->createdAt,
            $challenge->expiresAt,
            'consumed',
            $now
        );
        return true;
    }
}
