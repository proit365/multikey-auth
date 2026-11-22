<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Cryptography;

final class HmacSha256
{
    public function generate(string $message, string $key): string
    {
        if (strlen($key) !== 32) {
            throw new \InvalidArgumentException('HMAC-SHA256 key must be exactly 32 bytes.');
        }

        return hash_hmac('sha256', $message, $key, true);
    }

    public function verify(string $message, string $key, string $receivedProof): bool
    {
        if (strlen($receivedProof) !== 32) {
            return false;
        }

        return hash_equals($this->generate($message, $key), $receivedProof);
    }
}
