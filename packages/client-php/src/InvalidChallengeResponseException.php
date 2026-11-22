<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Client;

final class InvalidChallengeResponseException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
