<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Server;

final class FixedWindowRateLimiter implements RateLimiterInterface
{
    private array $windows = [];

    public function __construct(
        private readonly ClockInterface $clock,
        private readonly int $maximumRequests,
        private readonly int $windowSeconds
    ) {
        if ($maximumRequests < 1 || $windowSeconds < 1) {
            throw new \InvalidArgumentException('Rate limit settings must be positive.');
        }
    }

    public function allow(string $clientIdentifier, string $operation): bool
    {
        $now = $this->clock->now();
        $window = intdiv($now, $this->windowSeconds);
        $key = hash('sha256', $clientIdentifier . "\0" . $operation);
        if (($this->windows[$key]['window'] ?? null) !== $window) {
            $this->windows[$key] = ['window' => $window, 'count' => 0];
        }
        if ($this->windows[$key]['count'] >= $this->maximumRequests) {
            return false;
        }
        $this->windows[$key]['count']++;
        return true;
    }
}
