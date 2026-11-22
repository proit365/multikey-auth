<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Server;

final class ReplayDetectedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Challenge has already been consumed.');
    }
}
