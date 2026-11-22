<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Storage;

use ProIT365\MultiKeyAuth\Core\Key\KeyStatus;

final class KeyRecord
{
    public function __construct(
        public readonly string $identifier,
        public readonly EncryptedSecret $encryptedSecret,
        public readonly KeyStatus $status,
        public readonly ?int $revokedAt = null
    ) {
        if ($identifier === '') {
            throw new \InvalidArgumentException('Stored key identifier must not be empty.');
        }
    }
}
