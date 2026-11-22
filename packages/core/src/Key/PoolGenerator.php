<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Key;

use ProIT365\MultiKeyAuth\Core\Random\RandomGeneratorInterface;

final class PoolGenerator
{
    public function __construct(
        private readonly RandomGeneratorInterface $random,
        private readonly IdentifierGenerator $identifierGenerator
    ) {
    }

    public function generate(
        PoolMetadata $metadata,
        ?GenerationBatch $batch = null,
        ?callable $onProgress = null,
        ?callable $isCancelled = null
    ): array {
        if ($metadata->keyCount > 1_000_000) {
            throw new \InvalidArgumentException('Pool exceeds the supported 1,000,000-key limit.');
        }
        if ($batch !== null && $batch->requestedCount !== $metadata->keyCount) {
            throw new \InvalidArgumentException('Batch count does not match pool key count.');
        }

        $batch ??= new GenerationBatch('inline', $metadata->keyCount);
        $batch->markRunning();
        $keys = [];
        $identifiers = [];

        for ($index = 0; $index < $metadata->keyCount; $index++) {
            if ($isCancelled !== null && $isCancelled()) {
                $batch->cancel();
                throw new PoolGenerationCancelledException($batch);
            }

            try {
                $identifier = $this->identifierGenerator->generate($identifiers);
                $secret = new KeyMaterial($this->random->bytes(32));
                $keys[] = new ManagedKey($identifier, $secret);
                $identifiers[] = $identifier;
                $batch->recordGenerated();
            } catch (\Throwable $error) {
                $batch->recordFailure();
                $batch->fail();
                throw $error;
            }

            if ($onProgress !== null) {
                $onProgress($batch);
            }
        }

        $batch->complete();
        return $keys;
    }
}
