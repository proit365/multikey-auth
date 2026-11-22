<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Cryptography;

use ProIT365\MultiKeyAuth\Core\Encoding\BinaryEncoder;

final class CanonicalMessageEncoder
{
    public function hkdfContext(
        int $protocolVersion,
        string $clientId,
        string $requestId,
        int $poolVersion,
        array $keyIds
    ): string {
        $this->validateKeyIds($keyIds);

        $context = BinaryEncoder::lengthField('MKAP');
        $context .= BinaryEncoder::uint32($protocolVersion);
        $context .= BinaryEncoder::lengthField($clientId);
        $context .= BinaryEncoder::lengthField($requestId);
        $context .= BinaryEncoder::uint32($poolVersion);
        $context .= BinaryEncoder::uint32(count($keyIds));
        foreach ($keyIds as $keyId) {
            $context .= BinaryEncoder::lengthField($keyId);
        }

        return $context;
    }

    public function proofMessage(
        int $protocolVersion,
        string $clientId,
        string $requestId,
        string $nonce,
        int $expiresAt,
        string $httpMethod,
        string $requestTarget,
        string $bodySha256,
        int $poolVersion,
        array $keyIds
    ): string {
        if (strlen($nonce) !== 32) {
            throw new \InvalidArgumentException('Nonce must be exactly 32 bytes.');
        }
        if (strlen($bodySha256) !== 32) {
            throw new \InvalidArgumentException('Body SHA-256 must be exactly 32 bytes.');
        }
        $this->validateKeyIds($keyIds);

        $message = BinaryEncoder::uint32($protocolVersion);
        $message .= BinaryEncoder::lengthField($clientId);
        $message .= BinaryEncoder::lengthField($requestId);
        $message .= BinaryEncoder::lengthField($nonce);
        $message .= BinaryEncoder::uint64($expiresAt);
        $message .= BinaryEncoder::lengthField($httpMethod);
        $message .= BinaryEncoder::lengthField($requestTarget);
        $message .= BinaryEncoder::lengthField($bodySha256);
        $message .= BinaryEncoder::uint32($poolVersion);
        $message .= BinaryEncoder::uint32(count($keyIds));
        foreach ($keyIds as $keyId) {
            $message .= BinaryEncoder::lengthField($keyId);
        }

        return $message;
    }

    private function validateKeyIds(array $keyIds): void
    {
        if ($keyIds === [] || count($keyIds) > 0xffffffff) {
            throw new \InvalidArgumentException('Invalid key identifier count.');
        }
        $seen = [];
        foreach ($keyIds as $keyId) {
            if (!is_string($keyId) || $keyId === '' || strlen($keyId) > 256) {
                throw new \InvalidArgumentException('Invalid key identifier.');
            }
            if (isset($seen[$keyId])) {
                throw new \InvalidArgumentException('Duplicate key identifier.');
            }
            $seen[$keyId] = true;
        }
    }
}
