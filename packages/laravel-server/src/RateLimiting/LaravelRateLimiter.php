<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\LaravelServer\RateLimiting;

use Illuminate\Cache\RateLimiter as IlluminateRateLimiter;
use ProIT365\MultiKeyAuth\Core\Server\RateLimiterInterface;

final class LaravelRateLimiter implements RateLimiterInterface
{
    public function __construct(
        private readonly IlluminateRateLimiter $limiter,
        private readonly int $challengeLimit,
        private readonly int $authenticationLimit,
        private readonly int $decaySeconds
    ) {
        if ($challengeLimit < 1 || $authenticationLimit < 1 || $decaySeconds < 1) {
            throw new \InvalidArgumentException('Rate limit settings must be positive.');
        }
    }

    public function allow(string $clientIdentifier, string $operation): bool
    {
        $limit = match ($operation) {
            'challenge' => $this->challengeLimit,
            'authenticate' => $this->authenticationLimit,
            default => throw new \InvalidArgumentException('Unknown rate-limit operation.'),
        };
        $key = 'mkap:' . $operation . ':' . hash('sha256', $clientIdentifier);
        if ($this->limiter->tooManyAttempts($key, $limit)) {
            return false;
        }

        $this->limiter->hit($key, $this->decaySeconds);

        return true;
    }
}
