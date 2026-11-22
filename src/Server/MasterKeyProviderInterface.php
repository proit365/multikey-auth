<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Server;

use ProIT365\MultiKeyAuth\Core\Storage\MasterKey;

interface MasterKeyProviderInterface
{
    public function forPool(string $poolIdentifier): MasterKey;
}
