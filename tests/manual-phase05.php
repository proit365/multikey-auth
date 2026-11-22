<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'ProIT365\\MultiKeyAuth\\Core\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    require dirname(__DIR__) . '/src/' . $relative . '.php';
});

use ProIT365\MultiKeyAuth\Core\Export\ExportManager;
use ProIT365\MultiKeyAuth\Core\Export\KeyExport;
use ProIT365\MultiKeyAuth\Core\Export\ExportValidator;
use ProIT365\MultiKeyAuth\Core\Encoding\Base64Url;
use ProIT365\MultiKeyAuth\Core\Key\KeyStatus;
use ProIT365\MultiKeyAuth\Core\Random\SecureRandomGenerator;
use ProIT365\MultiKeyAuth\Core\Storage\MasterKey;
use ProIT365\MultiKeyAuth\Core\Storage\SecretCipher;
use ProIT365\MultiKeyAuth\Core\Storage\EncryptedSecret;
use ProIT365\MultiKeyAuth\Core\Storage\KeyRecord;
use ProIT365\MultiKeyAuth\Core\Storage\InMemoryKeyRepository;
use ProIT365\MultiKeyAuth\Core\Storage\PdoKeyRepository;
use ProIT365\MultiKeyAuth\Core\Key\KeyMaterial;

$random = new SecureRandomGenerator();
$masterKey = new MasterKey(str_repeat("\x42", 32), 7);
$cipher = new SecretCipher($random);
$aad = 'MKAP-POOL-V1|' . 'POOL_001|' . 'a8f31x';
$secret = str_repeat("\x11", 32);

$encrypted = $cipher->encrypt($secret, $masterKey, $aad);
if (strlen($encrypted->nonce) !== 12 || strlen($encrypted->tag) !== 16) {
    throw new RuntimeException('AES-GCM envelope fields have invalid lengths.');
}
if ($cipher->decrypt($encrypted, $masterKey, $aad) !== $secret) {
    throw new RuntimeException('AES-GCM round trip failed.');
}

$tampered = new EncryptedSecret(
    $encrypted->ciphertext,
    $encrypted->nonce,
    substr_replace($encrypted->tag, "\x00", 0, 1),
    $encrypted->keyVersion
);
try {
    $cipher->decrypt($tampered, $masterKey, $aad);
    throw new RuntimeException('AES-GCM accepted tampered ciphertext.');
} catch (RuntimeException) {
}

$restored = EncryptedSecret::fromBinary($encrypted->toBinary());
if ($cipher->decrypt($restored, $masterKey, $aad) !== $secret) {
    throw new RuntimeException('Encrypted envelope binary round trip failed.');
}

$export = new KeyExport(
    1,
    1,
    'CLIENT_001',
    'POOL_001',
    1,
    1,
    '2026-11-22T00:00:00Z',
    6,
    'abcdefghijklmnopqrstuvwxyz0123456789',
    32,
    [
        ['id' => 'a8f31x', 'secret' => Base64Url::encode(str_repeat("\x11", 32))],
        ['id' => 'z71q5b', 'secret' => Base64Url::encode(str_repeat("\x12", 32))],
    ]
);
$exportManager = new ExportManager($cipher);
$plaintext = $exportManager->createPlaintextJson($export);
$manifest = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
(new ExportValidator())->validate($manifest);

$protected = $exportManager->createProtectedEnvelope($export, $masterKey);
$validated = $exportManager->openProtectedEnvelope(
    $protected,
    $masterKey,
    'CLIENT_001',
    'POOL_001',
    1
);
if (count($validated) !== 2) {
    throw new RuntimeException('Protected export round trip mismatch.');
}
if (strlen($exportManager->checksum($export)) !== 32) {
    throw new RuntimeException('Export checksum length mismatch.');
}

$inMemory = new InMemoryKeyRepository();
$record = new KeyRecord('a8f31x', $encrypted, KeyStatus::Active);
$inMemory->storeKey('POOL_001', $record);
if ($inMemory->countActive('POOL_001') !== 1) {
    throw new RuntimeException('In-memory repository count mismatch.');
}
if (count($inMemory->findActiveByIdentifiers('POOL_001', ['a8f31x'])) !== 1) {
    throw new RuntimeException('In-memory repository lookup mismatch.');
}
$inMemory->revokeKey('POOL_001', 'a8f31x', 1893456000);
if ($inMemory->countActive('POOL_001') !== 0) {
    throw new RuntimeException('In-memory revocation mismatch.');
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$migration = file_get_contents(dirname(__DIR__, 3) . '/database/sqlite/001_mkap_tables.sql');
$pdo->exec($migration);
$pdo->exec(
    "INSERT INTO mkap_clients (client_identifier, name, status, created_at, updated_at) "
    . "VALUES ('CLIENT_001', 'Test', 'active', '2026-11-22', '2026-11-22')"
);
$pdo->exec(
    "INSERT INTO mkap_key_pools "
    . "(client_id, pool_identifier, pool_version, identifier_length, identifier_alphabet, secret_length_bytes, "
    . "key_count, selection_count, status, created_at, updated_at) "
    . "VALUES (1, 'POOL_001', 1, 6, 'abcdefghijklmnopqrstuvwxyz0123456789', 32, 1, 1, 'active', '2026-11-22', '2026-11-22')"
);
$pdoRepository = new PdoKeyRepository($pdo);
$pdoRepository->storeKey('1', $record);
if ($pdoRepository->countActive('1') !== 1) {
    throw new RuntimeException('PDO repository count mismatch.');
}
if (count($pdoRepository->findActiveByIdentifiers('1', ['a8f31x'])) !== 1) {
    throw new RuntimeException('PDO repository lookup mismatch.');
}
$pdoRepository->revokeKey('1', 'a8f31x', 1893456000);
if ($pdoRepository->countActive('1') !== 0) {
    throw new RuntimeException('PDO revocation mismatch.');
}

echo "Manual phase 05 smoke: passed\n";
