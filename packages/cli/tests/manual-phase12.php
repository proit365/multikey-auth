<?php

declare(strict_types=1);

use ProIT365\MultiKeyAuth\Cli\AuditLogger;
use ProIT365\MultiKeyAuth\Cli\CliApplication;
use ProIT365\MultiKeyAuth\Cli\CliOptions;

$packageRoot = dirname(__DIR__);
$monorepoRoot = dirname(__DIR__, 3);
$isMonorepo = is_file($monorepoRoot . '/packages/cli/composer.json')
    && is_dir($monorepoRoot . '/packages/core/src')
    && is_dir($monorepoRoot . '/packages/server/src');

if ($isMonorepo) {
    $root = $monorepoRoot;
    spl_autoload_register(static function (string $class) use ($root): void {
        $prefixes = [
            'ProIT365\\MultiKeyAuth\\Cli\\' => $root . '/packages/cli/src/',
            'ProIT365\\MultiKeyAuth\\Core\\Server\\' => $root . '/packages/server/src/Server/',
            'ProIT365\\MultiKeyAuth\\Core\\' => $root . '/packages/core/src/',
        ];
        foreach ($prefixes as $prefix => $directory) {
            if (str_starts_with($class, $prefix)) {
                $file = $directory . str_replace(
                    '\\',
                    DIRECTORY_SEPARATOR,
                    substr($class, strlen($prefix))
                ) . '.php';
                if (is_file($file)) {
                    require $file;
                }
                return;
            }
        }
    });
} else {
    require $packageRoot . '/vendor/autoload.php';
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$schemaPath = __DIR__ . '/fixtures/001_mkap_tables.sql';
$schema = file_get_contents($schemaPath);
if ($schema === false) {
    throw new RuntimeException('Unable to read the CLI SQLite test fixture.');
}
if ($isMonorepo) {
    $canonicalSchema = file_get_contents($root . '/database/sqlite/001_mkap_tables.sql');
    if ($canonicalSchema === false
        || str_replace("\r\n", "\n", $canonicalSchema) !== str_replace("\r\n", "\n", $schema)) {
        throw new RuntimeException('The CLI SQLite fixture differs from the canonical monorepo schema.');
    }
}
$pdo->exec($schema);
$pdo->exec(
    "INSERT INTO mkap_clients "
    . "(client_identifier, name, status, created_at, updated_at) "
    . "VALUES ('CLIENT_001', 'Test', 'active', '2026-11-22', '2026-11-22')"
);
$audit = sys_get_temp_dir() . '/mkap-phase12-' . bin2hex(random_bytes(6)) . '.jsonl';
$masterHex = str_repeat('71', 32);
putenv('MKAP_CLI_MASTER_KEY_HEX=' . $masterHex);

$run = static function (array $arguments) use ($pdo, $audit): int {
    array_unshift($arguments, 'mkap');
    return (new CliApplication(
        new CliOptions($arguments),
        $pdo,
        new AuditLogger($audit)
    ))->run();
};

if ($run(['pool', 'create', 'CLIENT_001', 'POOL_001',
    '--count=3', '--selection=2', '--confirm']) !== 0) {
    throw new RuntimeException('CLI pool-create failed.');
}
if ($run(['pool', 'inspect', 'POOL_001']) !== 0) {
    throw new RuntimeException('CLI pool-inspect failed.');
}
if ($run(['pool', 'generate', 'POOL_001',
    '--confirm']) !== 0) {
    throw new RuntimeException('CLI key-generation failed.');
}
$keyCount = (int) $pdo->query(
    'SELECT COUNT(*) FROM mkap_authentication_keys'
)->fetchColumn();
if ($keyCount !== 3) {
    throw new RuntimeException('CLI generated key count mismatch.');
}

$exportPath = sys_get_temp_dir() . '/mkap-phase12-' . bin2hex(random_bytes(6)) . '.json';
if ($run(['export', 'create', 'POOL_001',
    '--output=' . $exportPath,
    '--development-json',
    '--confirm']) !== 0) {
    throw new RuntimeException('CLI export-create failed.');
}
if (!is_file($exportPath) || filesize($exportPath) < 100) {
    throw new RuntimeException('CLI did not create a development export.');
}
if ($run(['export', 'validate', $exportPath]) !== 0) {
    throw new RuntimeException('CLI export-validation failed.');
}

$identifier = (string) $pdo->query(
    'SELECT public_identifier FROM mkap_authentication_keys '
    . 'WHERE status = \'active\' ORDER BY public_identifier LIMIT 1'
)->fetchColumn();
$revokeIdentifier = (string) $pdo->query(
    'SELECT public_identifier FROM mkap_authentication_keys '
    . 'WHERE status = \'active\' AND public_identifier != '
    . $pdo->quote($identifier)
    . ' ORDER BY public_identifier LIMIT 1'
)->fetchColumn();
if ($run(['key', 'revoke', 'POOL_001', $revokeIdentifier, '--confirm']) !== 0) {
    throw new RuntimeException('CLI key-revoke failed.');
}
if ($run(['key', 'inspect', 'POOL_001', $revokeIdentifier]) !== 0) {
    throw new RuntimeException('CLI key-inspect failed.');
}
if ($run(['key', 'rotate', 'POOL_001', $identifier,
    '--dry-run']) !== 0) {
    throw new RuntimeException('CLI key-rotate dry-run failed.');
}
if ($run(['key', 'rotate', 'POOL_001', $identifier,
    '--confirm']) !== 0) {
    throw new RuntimeException('CLI key-rotate failed.');
}
if ($run(['pool', 'generate', 'POOL_001',
    '--master-key-hex=' . $masterHex, '--confirm']) === 0) {
    throw new RuntimeException('CLI accepted a master key as a process argument.');
}
if ((int) $pdo->query(
    'SELECT COUNT(*) FROM mkap_key_rotations'
)->fetchColumn() !== 1) {
    throw new RuntimeException('CLI key-rotation history was not recorded.');
}
if ($run(['challenge', 'cleanup', '--dry-run']) !== 0) {
    throw new RuntimeException('CLI challenge-cleanup dry-run failed.');
}
if ($run(['pool', 'revoke', 'POOL_001', '--dry-run']) !== 0) {
    throw new RuntimeException('CLI pool-revoke dry-run failed.');
}
if ($run(['audit', 'list', '--limit=20']) !== 0) {
    throw new RuntimeException('CLI audit-list failed.');
}

$auditContent = file_get_contents($audit);
if ($auditContent === false || !str_contains($auditContent, 'export.validate')) {
    throw new RuntimeException('CLI audit output was not recorded.');
}
@unlink($exportPath);
@unlink($audit);
putenv('MKAP_CLI_MASTER_KEY_HEX');

echo "Phase 12 CLI administrative tests passed.\n";
