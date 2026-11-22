<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Storage;

final class EncryptedSecret
{
    public function __construct(
        public readonly string $ciphertext,
        public readonly string $nonce,
        public readonly string $tag,
        public readonly int $keyVersion
    ) {
        if ($nonce === '' || strlen($nonce) !== 12) {
            throw new \InvalidArgumentException('AES-GCM nonce must be exactly 12 bytes.');
        }
        if (strlen($tag) !== 16) {
            throw new \InvalidArgumentException('AES-GCM tag must be exactly 16 bytes.');
        }
        if ($keyVersion < 1) {
            throw new \InvalidArgumentException('Encryption key version must be positive.');
        }
    }

    public function toBinary(): string
    {
        return pack('C', 1)
            . pack('N', $this->keyVersion)
            . $this->nonce
            . $this->tag
            . $this->ciphertext;
    }

    public static function fromBinary(string $value): self
    {
        if (strlen($value) < 33) {
            throw new \InvalidArgumentException('Encrypted secret envelope is truncated.');
        }

        $format = ord($value[0]);
        $keyVersion = unpack('N', substr($value, 1, 4))[1];
        $nonce = substr($value, 5, 12);
        $tag = substr($value, 17, 16);
        $ciphertext = substr($value, 33);

        if ($format !== 1) {
            throw new \InvalidArgumentException('Unsupported encrypted secret envelope format.');
        }

        return new self($ciphertext, $nonce, $tag, $keyVersion);
    }
}
