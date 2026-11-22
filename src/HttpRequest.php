<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Client;

final class HttpRequest
{
    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly array $headers,
        public readonly string $body
    ) {
    }
}
