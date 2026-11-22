<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Client;

final class ChallengeRequestFailedException extends \RuntimeException
{
    public function __construct(public readonly int $statusCode)
    {
        parent::__construct('Challenge endpoint returned HTTP ' . $statusCode . '.');
    }
}
