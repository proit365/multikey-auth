<?php

declare(strict_types=1);

use ProIT365\MultiKeyAuth\Core\Encoding\Base64Url;
use ProIT365\MultiKeyAuth\Core\Export\ExportManager;
use ProIT365\MultiKeyAuth\Core\Export\KeyExport;
use ProIT365\MultiKeyAuth\Core\Key\IdentifierGenerator;
use ProIT365\MultiKeyAuth\Core\Key\KeyStatus;
use ProIT365\MultiKeyAuth\Core\Key\PoolGenerator;
use ProIT365\MultiKeyAuth\Core\Key\PoolMetadata;
use ProIT365\MultiKeyAuth\Core\Random\SecureRandomGenerator;
use ProIT365\MultiKeyAuth\Core\Storage\KeyAssociatedData;
use ProIT365\MultiKeyAuth\Core\Storage\KeyRecord;
use ProIT365\MultiKeyAuth\Core\Storage\MasterKey;
use ProIT365\MultiKeyAuth\Core\Storage\PdoKeyRepository;
use ProIT365\MultiKeyAuth\Core\Storage\SecretCipher;

require __DIR__ . '/vendor/autoload.php';

$dataDirectory = __DIR__ . '/data';
$databasePath = $dataDirectory . '/mkap.sqlite';
$masterKeyPath = $dataDirectory . '/master-key.bin';
$exportPath = $dataDirectory . '/client-export.json';

foreach ([$databasePath, $masterKeyPath, $exportPath] as $path) {
    if (file_exists($path)) {
        fwrite(STDERR, "Demo data already exists. Refusing to overwrite: {$path}\n");
        exit(1);
    }
}

if (!is_dir($dataDirectory) && !mkdir($dataDirectory, 0700, true) && !is_dir($dataDirectory)) {
    throw new RuntimeException('Unable to create the demo data directory.');
}

$masterKeyBytes = random_bytes(32);
if (file_put_contents($masterKeyPath, $masterKeyBytes, LOCK_EX) !== 32) {
    throw new RuntimeException('Unable to write the demo master key.');
}
if (DIRECTORY_SEPARATOR === '\\') {
    fwrite(STDERR, "Restrict the data directory with Windows file permissions.\n");
} elseif (!chmod($masterKeyPath, 0600)) {
    fwrite(STDERR, "Warning: unable to restrict permissions on the demo master key.\n");
}

$database = new PDO('sqlite:' . $databasePath);
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database->exec('PRAGMA foreign_keys = ON');
$schemaPath = dirname(__DIR__, 2) . '/database/sqlite/001_mkap_tables.sql';
$schema = file_get_contents($schemaPath);
if ($schema === false) {
    throw new RuntimeException('Unable to read the SQLite schema.');
}
$database->exec($schema);
$database->exec(
    'CREATE TABLE mkap_demo_rate_limits ('
    . 'client_identifier TEXT NOT NULL, '
    . 'operation TEXT NOT NULL, '
    . 'window_number INTEGER NOT NULL, '
    . 'request_count INTEGER NOT NULL, '
    . 'PRIMARY KEY (client_identifier, operation)'
    . ')'
);

$clientIdentifier = 'DEMO_CLIENT';
$poolIdentifier = 'DEMO_POOL';
$poolVersion = 1;
$exportVersion = 1;
$identifierLength = 6;
$alphabet = IdentifierGenerator::DEFAULT_ALPHABET;
$keyCount = 12;
$selectionCount = 3;
$timestamp = gmdate('Y-m-d H:i:s');

$insertClient = $database->prepare(
    'INSERT INTO mkap_clients (client_identifier, name, status, created_at, updated_at) '
    . 'VALUES (?, ?, ?, ?, ?)'
);
$insertClient->execute([$clientIdentifier, 'Local MKAP example', 'active', $timestamp, $timestamp]);
$clientRecordId = (int) $database->lastInsertId();

$insertPool = $database->prepare(
    'INSERT INTO mkap_key_pools '
    . '(client_id, pool_identifier, pool_version, identifier_length, identifier_alphabet, '
    . 'secret_length_bytes, key_count, selection_count, status, created_at, updated_at) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$insertPool->execute([
    $clientRecordId,
    $poolIdentifier,
    $poolVersion,
    $identifierLength,
    $alphabet,
    32,
    $keyCount,
    $selectionCount,
    'active',
    $timestamp,
    $timestamp,
]);
$poolRecordId = (int) $database->lastInsertId();

$random = new SecureRandomGenerator();
$metadata = new PoolMetadata(
    $poolIdentifier,
    $poolVersion,
    $identifierLength,
    $alphabet,
    $keyCount,
    $selectionCount
);
$generatedKeys = (new PoolGenerator(
    $random,
    new IdentifierGenerator($random, $alphabet, $identifierLength)
))->generate($metadata);

$masterKey = new MasterKey($masterKeyBytes);
$secretCipher = new SecretCipher($random);
$keyRepository = new PdoKeyRepository($database);
$exportKeys = [];
foreach ($generatedKeys as $key) {
    $keyRepository->storeKey(
        (string) $poolRecordId,
        new KeyRecord(
            $key->identifier,
            $secretCipher->encrypt(
                $key->secret->bytes(),
                $masterKey,
                KeyAssociatedData::forKey($poolIdentifier, $key->identifier)
            ),
            KeyStatus::Active
        )
    );
    $exportKeys[] = [
        'id' => $key->identifier,
        'secret' => Base64Url::encode($key->secret->bytes()),
    ];
}

$export = new KeyExport(
    1,
    1,
    $clientIdentifier,
    $poolIdentifier,
    $poolVersion,
    $exportVersion,
    gmdate('Y-m-d\TH:i:s\Z'),
    $identifierLength,
    $alphabet,
    32,
    $exportKeys
);
$exportJson = (new ExportManager($secretCipher))->createPlaintextJson($export);
if (file_put_contents($exportPath, $exportJson, LOCK_EX) !== strlen($exportJson)) {
    throw new RuntimeException('Unable to write the local client export.');
}
if (DIRECTORY_SEPARATOR !== '\\' && !chmod($exportPath, 0600)) {
    fwrite(STDERR, "Warning: unable to restrict permissions on the client export.\n");
}

fwrite(STDOUT, "Demo initialized. Keep data/master-key.bin and data/client-export.json private.\n");
