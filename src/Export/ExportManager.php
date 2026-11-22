<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Export;

use ProIT365\MultiKeyAuth\Core\Storage\EncryptedSecret;
use ProIT365\MultiKeyAuth\Core\Storage\MasterKey;
use ProIT365\MultiKeyAuth\Core\Storage\SecretCipher;

final class ExportManager
{
    public function __construct(private readonly SecretCipher $cipher)
    {
    }

    public function createPlaintextJson(KeyExport $export): string
    {
        $json = json_encode(
            $export->manifest(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
        return $json;
    }

    public function checksum(KeyExport $export): string
    {
        return hash('sha256', $this->createPlaintextJson($export), true);
    }

    public function createProtectedEnvelope(KeyExport $export, MasterKey $wrappingKey): EncryptedSecret
    {
        $plaintext = $this->createPlaintextJson($export);
        $associatedData = 'MKAP-EXPORT-V1'
            . chr(0)
            . $export->clientId
            . chr(0)
            . $export->poolId
            . chr(0)
            . pack('N', $export->exportVersion);

        return $this->cipher->encryptEnvelope(
            $plaintext,
            $wrappingKey,
            $associatedData
        );
    }

    public function openProtectedEnvelope(
        EncryptedSecret $envelope,
        MasterKey $wrappingKey,
        string $clientId,
        string $poolId,
        int $exportVersion
    ): array {
        $associatedData = 'MKAP-EXPORT-V1'
            . chr(0)
            . $clientId
            . chr(0)
            . $poolId
            . chr(0)
            . pack('N', $exportVersion);

        $plaintext = $this->cipher->decrypt($envelope, $wrappingKey, $associatedData, null);
        $manifest = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);

        return (new ExportValidator())->validate($manifest);
    }
}
