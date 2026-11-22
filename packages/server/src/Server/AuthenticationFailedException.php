<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Server;

final class AuthenticationFailedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Authentication failed.');
    }
}
