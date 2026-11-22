<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Server;

final class UnknownClientException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Client is not registered or active.');
    }
}
