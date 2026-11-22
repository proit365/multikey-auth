<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Server;

use ProIT365\MultiKeyAuth\Core\Random\RandomGeneratorInterface;
use ProIT365\MultiKeyAuth\Core\Random\RandomIntegerGeneratorInterface;
use ProIT365\MultiKeyAuth\Core\Storage\KeyRepositoryInterface;

final class ChallengeIssuer
{
    public function __construct(
        private readonly ClientPoolProviderInterface $clients,
        private readonly KeyRepositoryInterface $keys,
        private readonly ChallengeRepositoryInterface $challenges,
        private readonly RateLimiterInterface $rateLimiter,
        private readonly RandomGeneratorInterface $random,
        private readonly RandomIntegerGeneratorInterface $randomIntegers,
        private readonly ClockInterface $clock,
        private readonly int $lifetimeSeconds = 30
    ) {
        if ($lifetimeSeconds < 1 || $lifetimeSeconds > 3600) {
            throw new \InvalidArgumentException('Challenge lifetime must be between 1 and 3600 seconds.');
        }
    }

    public function issue(ChallengeRequest $request): ChallengeResponse
    {
        if (!$this->rateLimiter->allow($request->clientIdentifier, 'challenge')) {
            throw new RateLimitExceededException();
        }
        $clientPool = $this->clients->findActivePool($request->clientIdentifier);
        if ($clientPool === null) {
            throw new UnknownClientException();
        }

        $metadata = $clientPool->metadata;
        $activeCount = $this->keys->countActive((string) $clientPool->poolRecordId);
        if ($metadata->selectionCount > $activeCount) {
            throw new \RuntimeException('Active key count is below the configured selection count.');
        }
        $keyIdentifiers = $this->selectIdentifiers(
            (string) $clientPool->poolRecordId,
            $activeCount,
            $metadata->selectionCount
        );

        $now = $this->clock->now();
        $challenge = new ChallengeRecord(
            $this->random->bytes(16),
            1,
            $clientPool->clientRecordId,
            $clientPool->clientIdentifier,
            $clientPool->poolRecordId,
            $metadata->poolId,
            $metadata->poolVersion,
            $this->random->bytes(32),
            $keyIdentifiers,
            $metadata->identifierLength,
            $request->method,
            $request->target,
            $request->bodySha256,
            $now,
            $now + $this->lifetimeSeconds
        );
        $this->challenges->save($challenge);

        return new ChallengeResponse($challenge);
    }

    private function selectIdentifiers(string $poolId, int $population, int $selectionCount): array
    {
        $ranks = [];
        for ($upper = $population - $selectionCount; $upper < $population; $upper++) {
            $candidate = $this->randomIntegers->integer(0, $upper);
            if (isset($ranks[$candidate])) {
                $candidate = $upper;
            }
            $ranks[$candidate] = true;
        }

        $identifiers = [];
        foreach (array_keys($ranks) as $rank) {
            $identifier = $this->keys->findActiveIdentifierAt($poolId, $rank);
            if ($identifier === null) {
                throw new \RuntimeException('Active key set changed during challenge issuance.');
            }
            $identifiers[] = $identifier;
        }
        if (count($identifiers) !== count(array_unique($identifiers))) {
            throw new \RuntimeException('Active key set changed during challenge selection.');
        }
        if (count($this->keys->findActiveByIdentifiers($poolId, $identifiers)) !== $selectionCount) {
            throw new \RuntimeException('Selected keys changed status during challenge issuance.');
        }
        return $identifiers;
    }
}
