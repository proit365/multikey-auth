<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Key;

final class GenerationBatch
{
    public function __construct(
        public readonly string $batchId,
        public readonly int $requestedCount,
        private int $generatedCount = 0,
        private int $failedCount = 0,
        private string $status = 'pending'
    ) {
        if ($batchId === '') {
            throw new \InvalidArgumentException('Batch identifier must not be empty.');
        }
        if ($requestedCount < 1) {
            throw new \InvalidArgumentException('Requested key count must be positive.');
        }
    }

    public function markRunning(): void
    {
        $this->status = 'running';
    }

    public function recordGenerated(): void
    {
        $this->generatedCount++;
        if ($this->generatedCount > $this->requestedCount) {
            throw new \LogicException('Generated count exceeds requested count.');
        }
    }

    public function recordFailure(): void
    {
        $this->failedCount++;
    }

    public function complete(): void
    {
        $this->status = 'completed';
    }

    public function cancel(): void
    {
        $this->status = 'cancelled';
    }

    public function fail(): void
    {
        $this->status = 'failed';
    }

    public function progress(): float
    {
        return $this->generatedCount / $this->requestedCount;
    }

    public function generatedCount(): int
    {
        return $this->generatedCount;
    }

    public function failedCount(): int
    {
        return $this->failedCount;
    }

    public function status(): string
    {
        return $this->status;
    }
}
