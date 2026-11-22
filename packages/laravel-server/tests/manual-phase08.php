<?php

declare(strict_types=1);

use ProIT365\MultiKeyAuth\LaravelServer\Database\PdoClientPoolProvider;

$root = dirname(__DIR__, 3);
spl_autoload_register(static function (string $class) use ($root): void {
    $prefixes = [
        'ProIT365\\MultiKeyAuth\\Core\\Server\\' => $root . '/packages/server/src/Server/',
        'ProIT365\\MultiKeyAuth\\Core\\' => $root . '/packages/core/src/',
        'ProIT365\\MultiKeyAuth\\LaravelServer\\' => dirname(__DIR__) . '/src/',
    ];
    foreach ($prefixes as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $file = $directory . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});

$pdo = new PDO('sqlite::memory:');
$pdo->exec(
    'CREATE TABLE mkap_clients (id INTEGER PRIMARY KEY, client_identifier TEXT, status TEXT);
     CREATE TABLE mkap_key_pools (
       id INTEGER PRIMARY KEY, client_id INTEGER, pool_identifier TEXT, pool_version INTEGER,
       identifier_length INTEGER, identifier_alphabet TEXT, key_count INTEGER,
       selection_count INTEGER, status TEXT
     );'
);
$pdo->exec("INSERT INTO mkap_clients VALUES (1, 'CLIENT_001', 'active')");
$pdo->exec(
    "INSERT INTO mkap_key_pools VALUES (1, 1, 'POOL_A', 1, 6, 'abcdefghijklmnopqrstuvwxyz0123456789', 10, 3, 'active')"
);
$provider = new PdoClientPoolProvider($pdo);
$pool = $provider->findActivePool('CLIENT_001');
if ($pool === null
    || $pool->clientRecordId !== 1
    || $pool->poolRecordId !== 1
    || $pool->metadata->selectionCount !== 3
    || $pool->metadata->identifierAlphabet !== 'abcdefghijklmnopqrstuvwxyz0123456789') {
    throw new RuntimeException('Laravel client-pool provider failed to load active pool metadata.');
}
if ($provider->findActivePool('UNKNOWN') !== null) {
    throw new RuntimeException('Laravel client-pool provider returned an unknown client.');
}
$pdo->exec(
    "INSERT INTO mkap_key_pools VALUES (2, 1, 'POOL_B', 1, 6, 'abcdefghijklmnopqrstuvwxyz0123456789', 10, 3, 'active')"
);
try {
    $provider->findActivePool('CLIENT_001');
    throw new RuntimeException('Ambiguous active pools were not rejected.');
} catch (LogicException) {
}

echo "Phase 08 Laravel adapter smoke tests passed.\n";
