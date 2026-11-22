<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Encoding;

final class BinaryEncoder
{
    public static function uint32(int $value): string
    {
        if ($value < 0 || $value > 0xffffffff) {
            throw new \InvalidArgumentException('Uint32 value is outside the supported range.');
        }

        return pack('N', $value);
    }

    public static function uint64(int $value): string
    {
        if ($value < 0) {
            throw new \InvalidArgumentException('Uint64 value must not be negative.');
        }
        if (PHP_INT_SIZE < 8) {
            throw new \InvalidArgumentException('A 64-bit integer is required for this platform.');
        }

        return pack('J', $value);
    }

    public static function lengthField(string $value): string
    {
        $length = strlen($value);
        if ($length > 0xffffffff) {
            throw new \InvalidArgumentException('Length-delimited field is too large.');
        }

        return self::uint32($length) . $value;
    }
}
