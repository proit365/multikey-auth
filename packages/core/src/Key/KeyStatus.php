<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Key;

enum KeyStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
}
