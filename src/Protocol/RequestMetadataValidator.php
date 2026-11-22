<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Protocol;

final class RequestMetadataValidator
{
    public static function clientIdentifier(string $value): void
    {
        if ($value === '' || strlen($value) > 128 || preg_match('//u', $value) !== 1) {
            throw new \InvalidArgumentException('Invalid client identifier.');
        }
    }

    public static function method(string $value): void
    {
        if (strlen($value) < 1 || strlen($value) > 32
            || preg_match("/^[!#$%&'*+.^_`|~0-9A-Z-]+$/D", $value) !== 1) {
            throw new \InvalidArgumentException('HTTP method must be an uppercase token.');
        }
    }

    public static function target(string $value): void
    {
        if (strlen($value) < 1 || strlen($value) > 4096
            || $value[0] !== '/'
            || str_contains($value, '#')
            || preg_match('//u', $value) !== 1
            || preg_match('/[\x00-\x20\x7f]/', $value) === 1) {
            throw new \InvalidArgumentException('Invalid origin-form request target.');
        }
    }

    public static function bodySha256(string $value): void
    {
        if (strlen($value) !== 32) {
            throw new \InvalidArgumentException('Body SHA-256 must be exactly 32 bytes.');
        }
    }
}
