<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Storage;

use ProIT365\MultiKeyAuth\Core\Random\RandomGeneratorInterface;

final class SecretCipher
{
    public function __construct(
        private readonly RandomGeneratorInterface $random
    ) {
    }

    public function encrypt(string $secret, MasterKey $masterKey, string $associatedData): EncryptedSecret
    {
        if (strlen($secret) !== 32) {
            throw new \InvalidArgumentException('Only 32-byte MKAP secrets can be encrypted.');
        }

        $nonce = $this->random->bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $secret,
            'aes-256-gcm',
            $masterKey->bytes(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $associatedData,
            16
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('AES-256-GCM encryption failed.');
        }

        return new EncryptedSecret($ciphertext, $nonce, $tag, $masterKey->version);
    }

    public function decrypt(
        EncryptedSecret $encrypted,
        MasterKey $masterKey,
        string $associatedData,
        ?int $exactLength = 32
    ): string
    {
        if ($encrypted->keyVersion !== $masterKey->version) {
            throw new \InvalidArgumentException('Master key version does not match the encrypted secret.');
        }

        $secret = openssl_decrypt(
            $encrypted->ciphertext,
            'aes-256-gcm',
            $masterKey->bytes(),
            OPENSSL_RAW_DATA,
            $encrypted->nonce,
            $encrypted->tag,
            $associatedData
        );

        if (!is_string($secret)) {
            throw new \RuntimeException('AES-256-GCM authentication failed.');
        }
        if ($exactLength !== null && $exactLength >= 0 && strlen($secret) !== $exactLength) {
            throw new \RuntimeException('Decrypted plaintext length is invalid.');
        }

        return $secret;
    }

    public function encryptEnvelope(string $plaintext, MasterKey $masterKey, string $associatedData): EncryptedSecret
    {
        $nonce = $this->random->bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $masterKey->bytes(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $associatedData,
            16
        );
        if ($ciphertext === false) {
            throw new \RuntimeException('AES-256-GCM encryption failed.');
        }

        return new EncryptedSecret($ciphertext, $nonce, $tag, $masterKey->version);
    }
}
