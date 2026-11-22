<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Encoding;

final class Base64Url
{
    public static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function decode(string $value, int $exactLength = -1): string
    {
        if ($value === '') {
            $decoded = '';
        } else {
            if (strlen($value) > 8_388_608 || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
                throw new \InvalidArgumentException('Invalid base64url value.');
            }
            $paddingLength = (4 - (strlen($value) % 4)) % 4;
            $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', $paddingLength), true);
            if ($decoded === false) {
                throw new \InvalidArgumentException('Invalid base64url value.');
            }
        }

        if ($exactLength >= 0 && strlen($decoded) !== $exactLength) {
            throw new \InvalidArgumentException('Decoded value has an unexpected length.');
        }

        return $decoded;
    }
}
