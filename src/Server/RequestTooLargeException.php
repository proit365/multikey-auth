<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Server;

final class RequestTooLargeException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Request body exceeds the configured limit.');
    }
}
