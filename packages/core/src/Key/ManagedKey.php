<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Key;

final class ManagedKey
{
    public function __construct(
        public readonly string $identifier,
        public readonly KeyMaterial $secret,
        private KeyStatus $status = KeyStatus::Active,
        private ?int $revokedAt = null
    ) {
        if ($identifier === '') {
            throw new \InvalidArgumentException('Key identifier must not be empty.');
        }
    }

    public function revoke(int $timestamp): void
    {
        if ($timestamp < 0) {
            throw new \InvalidArgumentException('Revocation timestamp must not be negative.');
        }
        $this->status = KeyStatus::Revoked;
        $this->revokedAt = $timestamp;
    }

    public function isActive(): bool
    {
        return $this->status === KeyStatus::Active;
    }

    public function status(): KeyStatus
    {
        return $this->status;
    }

    public function revokedAt(): ?int
    {
        return $this->revokedAt;
    }
}
