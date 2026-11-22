<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Cryptography;

final class HkdfSha256
{
    public function derive(string $inputKeyMaterial, string $salt, string $info, int $length = 32): string
    {
        if ($inputKeyMaterial === '') {
            throw new \InvalidArgumentException('HKDF input key material must not be empty.');
        }
        if (strlen($salt) !== 32) {
            throw new \InvalidArgumentException('HKDF salt must be exactly 32 bytes.');
        }
        if ($length < 1 || $length > 255 * 32) {
            throw new \InvalidArgumentException('HKDF output length is outside the supported range.');
        }

        $derived = hash_hkdf('sha256', $inputKeyMaterial, $length, $info, $salt);
        if (strlen($derived) !== $length) {
            throw new \RuntimeException('HKDF returned an unexpected output length.');
        }

        return $derived;
    }
}
