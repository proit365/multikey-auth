<?php

declare(strict_types=1);

use ProIT365\MultiKeyAuth\Core\Server\ClockInterface;
use ProIT365\MultiKeyAuth\Core\Server\RateLimiterInterface;

final class DemoSqliteRateLimiter implements RateLimiterInterface
{
    public function __construct(
        private readonly PDO $database,
        private readonly ClockInterface $clock,
        private readonly int $maximumRequests,
        private readonly int $windowSeconds
    ) {
        if ($maximumRequests < 1 || $windowSeconds < 1) {
            throw new InvalidArgumentException('Rate limit settings must be positive.');
        }
    }

    public function allow(string $clientIdentifier, string $operation): bool
    {
        $window = intdiv($this->clock->now(), $this->windowSeconds);
        $upsert = $this->database->prepare(
            'INSERT INTO mkap_demo_rate_limits '
            . '(client_identifier, operation, window_number, request_count) VALUES (?, ?, ?, 1) '
            . 'ON CONFLICT (client_identifier, operation) DO UPDATE SET '
            . 'request_count = CASE '
            . 'WHEN window_number = excluded.window_number THEN request_count + 1 '
            . 'ELSE 1 END, '
            . 'window_number = excluded.window_number'
        );
        $upsert->execute([$clientIdentifier, $operation, $window]);

        $select = $this->database->prepare(
            'SELECT request_count FROM mkap_demo_rate_limits '
            . 'WHERE client_identifier = ? AND operation = ?'
        );
        $select->execute([$clientIdentifier, $operation]);
        return (int) $select->fetchColumn() <= $this->maximumRequests;
    }
}
