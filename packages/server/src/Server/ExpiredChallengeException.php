<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Server;

final class ExpiredChallengeException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Challenge has expired.');
    }
}
