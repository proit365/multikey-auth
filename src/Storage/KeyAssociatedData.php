<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Storage;

use ProIT365\MultiKeyAuth\Core\Encoding\BinaryEncoder;

final class KeyAssociatedData
{
    public static function forKey(string $poolIdentifier, string $keyIdentifier): string
    {
        if ($poolIdentifier === '' || $keyIdentifier === '') {
            throw new \InvalidArgumentException('Pool and key identifiers must not be empty.');
        }

        return BinaryEncoder::lengthField('MKAP-KEY-V1')
            . BinaryEncoder::lengthField($poolIdentifier)
            . BinaryEncoder::lengthField($keyIdentifier);
    }
}
