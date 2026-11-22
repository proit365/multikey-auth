<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Server;

final class ErrorStatusMapper
{
    public function statusCode(\Throwable $error): int
    {
        return match (true) {
            $error instanceof RequestTooLargeException => 413,
            $error instanceof RateLimitExceededException => 429,
            $error instanceof UnknownChallengeException => 404,
            $error instanceof ReplayDetectedException => 409,
            $error instanceof AuthenticationFailedException,
            $error instanceof ExpiredChallengeException => 401,
            $error instanceof UnknownClientException => 403,
            $error instanceof \InvalidArgumentException => 400,
            default => 500,
        };
    }
}
