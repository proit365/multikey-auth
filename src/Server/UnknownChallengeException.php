<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Server;

final class UnknownChallengeException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Unknown challenge.');
    }
}
