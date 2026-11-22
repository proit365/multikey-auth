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

use ProIT365\MultiKeyAuth\Core\Key\GenerationBatch;
use ProIT365\MultiKeyAuth\Core\Key\IdentifierGenerator;
use ProIT365\MultiKeyAuth\Core\Key\KeyLifecycle;
use ProIT365\MultiKeyAuth\Core\Key\KeyRotation;
use ProIT365\MultiKeyAuth\Core\Key\KeyStatus;
use ProIT365\MultiKeyAuth\Core\Key\PoolGenerationCancelledException;
use ProIT365\MultiKeyAuth\Core\Key\PoolGenerator;
use ProIT365\MultiKeyAuth\Core\Key\PoolMetadata;
use ProIT365\MultiKeyAuth\Core\Random\SecureRandomGenerator;

$random = new SecureRandomGenerator();
$generator = new IdentifierGenerator($random);
$metadata = new PoolMetadata(
    'POOL_001',
    1,
    6,
    IdentifierGenerator::DEFAULT_ALPHABET,
    100,
    3
);
$batch = new GenerationBatch('BATCH_001', 100);
$progress = [];
$keys = (new PoolGenerator($random, $generator))->generate(
    $metadata,
    $batch,
    static function (GenerationBatch $current) use (&$progress): void {
        $progress[] = $current->progress();
    }
);

if (count($keys) !== 100) {
    throw new RuntimeException('Pool generation returned an unexpected count.');
}
$identifiers = array_map(static fn ($key): string => $key->identifier, $keys);
if (count($identifiers) !== count(array_unique($identifiers))) {
    throw new RuntimeException('Pool generation created duplicate identifiers.');
}
if ($batch->status() !== 'completed' || $batch->generatedCount() !== 100) {
    throw new RuntimeException('Generation batch did not complete correctly.');
}
if ($progress === [] || end($progress) !== 1.0) {
    throw new RuntimeException('Generation progress did not reach completion.');
}

$lifecycle = new KeyLifecycle($random, $generator);
$oldKey = $keys[0];
$lifecycle->revoke($oldKey, 1893456000);
if ($oldKey->status() !== KeyStatus::Revoked || $oldKey->revokedAt() !== 1893456000) {
    throw new RuntimeException('Key revocation did not update lifecycle state.');
}

$oldKey = $keys[1];
$newKey = $lifecycle->replace($keys, $oldKey, 1893456001);
if (!$newKey->isActive() || $oldKey->isActive()) {
    throw new RuntimeException('Key replacement lifecycle mismatch.');
}
$rotation = new KeyRotation(
    'POOL_001',
    $oldKey->identifier,
    $newKey->identifier,
    'scheduled rotation',
    1893456001
);
if ($rotation->reason !== 'scheduled rotation') {
    throw new RuntimeException('Rotation record mismatch.');
}

$poolCounts = [10_000, 100_000];
foreach ($poolCounts as $poolCount) {
    $scaleBatch = new GenerationBatch('BATCH_' . $poolCount, $poolCount);
    $scaleMetadata = new PoolMetadata(
        'POOL_' . $poolCount,
        1,
        6,
        IdentifierGenerator::DEFAULT_ALPHABET,
        $poolCount,
        3
    );
    $scaleKeys = (new PoolGenerator($random, $generator))->generate($scaleMetadata, $scaleBatch);
    if (count($scaleKeys) !== $poolCount || $scaleBatch->status() !== 'completed') {
        throw new RuntimeException('Large pool generation mismatch: ' . $poolCount);
    }
}

$cancelBatch = new GenerationBatch('BATCH_CANCEL', 10);
try {
    (new PoolGenerator($random, $generator))->generate(
        new PoolMetadata('POOL_CANCEL', 1, 6, IdentifierGenerator::DEFAULT_ALPHABET, 10, 3),
        $cancelBatch,
        null,
        static fn (): bool => true
    );
    throw new RuntimeException('Pool generation did not honour cancellation.');
} catch (PoolGenerationCancelledException $exception) {
    if ($exception->batch->status() !== 'cancelled') {
        throw new RuntimeException('Cancellation batch status mismatch.');
    }
}

echo "Manual phase 04 smoke: passed\n";
