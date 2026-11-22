<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Client;

final class HttpResponse
{
    public function __construct(
        public readonly int $statusCode,
        public readonly array $headers,
        public readonly string $body
    ) {
        if ($statusCode < 100 || $statusCode > 599) {
            throw new \InvalidArgumentException('HTTP status code is invalid.');
        }
    }
}
