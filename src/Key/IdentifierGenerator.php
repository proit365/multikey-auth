<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Key;

use ProIT365\MultiKeyAuth\Core\Random\RandomGeneratorInterface;

final class IdentifierGenerator
{
    public const DEFAULT_ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';

    public function __construct(
        private readonly RandomGeneratorInterface $random,
        private readonly string $alphabet = self::DEFAULT_ALPHABET,
        private readonly int $length = 6
    ) {
        if (strlen($alphabet) < 2) {
            throw new \InvalidArgumentException('The identifier alphabet is too short.');
        }
        if (strlen($alphabet) !== count(array_unique(str_split($alphabet)))) {
            throw new \InvalidArgumentException('The identifier alphabet must not contain duplicates.');
        }
        if ($length < 1 || $length > 256) {
            throw new \InvalidArgumentException('The identifier length is outside the supported range.');
        }
    }

    public function generate(array $existing = []): string
    {
        $existingSet = array_fill_keys($existing, true);
        $alphabetLength = strlen($this->alphabet);
        $limit = 256 - (256 % $alphabetLength);

        for ($attempt = 0; $attempt < 1024; $attempt++) {
            $identifier = '';

            while (strlen($identifier) < $this->length) {
                $byte = ord($this->random->bytes(1));
                if ($byte >= $limit) {
                    continue;
                }
                $identifier .= $this->alphabet[$byte % $alphabetLength];
            }

            if (!isset($existingSet[$identifier])) {
                return $identifier;
            }
        }

        throw new \RuntimeException('Unable to generate a unique identifier after 1024 attempts.');
    }
}
