<?php

declare(strict_types=1);

$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    $prefixes = [
        'ProIT365\\MultiKeyAuth\\Core\\' => $root . '/packages/core/src/',
        'ProIT365\\MultiKeyAuth\\Core\\Server\\' => $root . '/packages/server/src/Server/',
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

use ProIT365\MultiKeyAuth\Core\Cryptography\CanonicalMessageEncoder;
use ProIT365\MultiKeyAuth\Core\Cryptography\HkdfSha256;
use ProIT365\MultiKeyAuth\Core\Cryptography\HmacSha256;

$encoder = new CanonicalMessageEncoder();
$hkdf = new HkdfSha256();
$hmac = new HmacSha256();
$requestId = str_repeat("\x11", 16);
$nonce = str_repeat("\x22", 32);
$bodyHash = str_repeat("\x33", 32);
$keyIds = ['a8f31x', 'z71q5b', 'k92p4m'];
$context = $encoder->hkdfContext(1, 'CLIENT_001', $requestId, 1, $keyIds);
$proofKey = $hkdf->derive(str_repeat("\x44", 96), $nonce, $context);
$message = $encoder->proofMessage(
    1,
    'CLIENT_001',
    $requestId,
    $nonce,
    1893456000,
    'POST',
    '/api/orders?status=pending',
    $bodyHash,
    1,
    $keyIds
);

$iterations = 10000;
$start = hrtime(true);
for ($index = 0; $index < $iterations; $index++) {
    $hmac->generate($message, $proofKey);
}
$elapsedSeconds = (hrtime(true) - $start) / 1_000_000_000;

$exportSize = 1000;
$jsonBytes = $exportSize * 96;
$start = hrtime(true);
$json = str_repeat(' ', $jsonBytes);
$elapsedExport = (hrtime(true) - $start) / 1_000_000_000;

$result = [
    'runtime' => PHP_VERSION,
    'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
    'hmac_iterations' => $iterations,
    'hmac_elapsed_seconds' => round($elapsedSeconds, 6),
    'hmac_operations_per_second' => (int) round($iterations / max($elapsedSeconds, 0.000001)),
    'small_simulated_export_bytes' => strlen($json),
    'small_simulated_export_elapsed_seconds' => round($elapsedExport, 6),
];
fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
