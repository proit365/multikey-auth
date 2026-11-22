<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Cli;

use PDO;
use ProIT365\MultiKeyAuth\Core\Export\ExportValidator;
use ProIT365\MultiKeyAuth\Core\Export\ExportManager;
use ProIT365\MultiKeyAuth\Core\Export\KeyExport;
use ProIT365\MultiKeyAuth\Core\Key\IdentifierGenerator;
use ProIT365\MultiKeyAuth\Core\Key\KeyStatus;
use ProIT365\MultiKeyAuth\Core\Random\SecureRandomGenerator;
use ProIT365\MultiKeyAuth\Core\Storage\KeyAssociatedData;
use ProIT365\MultiKeyAuth\Core\Storage\MasterKey;
use ProIT365\MultiKeyAuth\Core\Storage\SecretCipher;
use ProIT365\MultiKeyAuth\Core\Encoding\Base64Url;
use ProIT365\MultiKeyAuth\Core\Storage\EncryptedSecret;

final class CliApplication
{
    public function __construct(
        private readonly CliOptions $options,
        private readonly ?PDO $database,
        private readonly AuditLogger $audit
    ) {
    }

    public function run(): int
    {
        $command = $this->options->command();
        return match ($command) {
            'help' => $this->help(),
            'client' => $this->client(),
            'pool' => $this->pool(),
            'key' => $this->key(),
            'export' => $this->export(),
            'challenge' => $this->challenge(),
            'audit' => $this->auditEvents(),
            default => $this->error('Unknown command: ' . $command),
        };
    }

    private function client(): int
    {
        if ($this->database === null) {
            return $this->error('A database DSN is required.');
        }
        $subcommand = $this->options->subcommand();
        $clientId = $this->options->positional(1);
        if ($subcommand === 'create') {
            if ($clientId === null) {
                return $this->error('Usage: mkap client create <client_id> [--name=<name>] [--dry-run] [--confirm]');
            }
            if ($this->options->has('dry-run')) {
                $this->audit->record('client.create', 'dry-run', ['client_id' => $clientId]);
                fwrite(STDOUT, "DRY-RUN create client {$clientId}." . PHP_EOL);
                return 0;
            }
            if (!$this->confirmed()) {
                return $this->error('Client creation requires --confirm.');
            }
            $name = $this->options->value('name') ?? $clientId;
            $now = gmdate('Y-m-d H:i:s');
            try {
                $statement = $this->database->prepare(
                    'INSERT INTO mkap_clients '
                    . '(client_identifier, name, status, created_at, updated_at) '
                    . 'VALUES (?, ?, ?, ?, ?)'
                );
                $statement->execute([$clientId, $name, 'active', $now, $now]);
            } catch (\Throwable $error) {
                $this->audit->record('client.create', 'failed', ['client_id' => $clientId]);
                return $this->error('Client creation failed: ' . $error->getMessage());
            }
            $this->audit->record('client.create', 'success', ['client_id' => $clientId]);
            fwrite(STDOUT, "Created client {$clientId}." . PHP_EOL);
            return 0;
        }
        if ($subcommand === 'inspect') {
            if ($clientId === null) {
                return $this->error('Usage: mkap client inspect <client_id>');
            }
            $statement = $this->database->prepare(
                'SELECT client_identifier, name, status, created_at, updated_at '
                . 'FROM mkap_clients WHERE client_identifier = ?'
            );
            $statement->execute([$clientId]);
            $client = $statement->fetch(PDO::FETCH_ASSOC);
            if ($client === false) {
                return $this->error('Unknown client identifier.');
            }
            fwrite(STDOUT, json_encode($client, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
            $this->audit->record('client.inspect', 'success', ['client_id' => $clientId]);
            return 0;
        }
        if ($subcommand === 'revoke') {
            if ($clientId === null) {
                return $this->error('Usage: mkap client revoke <client_id> [--dry-run] [--confirm]');
            }
            if ($this->options->has('dry-run')) {
                $this->audit->record('client.revoke', 'dry-run', ['client_id' => $clientId]);
                fwrite(STDOUT, "DRY-RUN revoke client {$clientId}." . PHP_EOL);
                return 0;
            }
            if (!$this->confirmed()) {
                return $this->error('Client revocation requires --confirm.');
            }
            $statement = $this->database->prepare(
                'UPDATE mkap_clients SET status = ?, updated_at = ? '
                . 'WHERE client_identifier = ? AND status = ?'
            );
            $now = gmdate('Y-m-d H:i:s');
            $statement->execute(['revoked', $now, $clientId, 'active']);
            if ($statement->rowCount() !== 1) {
                return $this->error('Unknown active client or client already revoked.');
            }
            $this->audit->record('client.revoke', 'success', ['client_id' => $clientId]);
            fwrite(STDOUT, "Revoked client {$clientId}." . PHP_EOL);
            return 0;
        }
        return $this->error('Unknown client subcommand.');
    }

    private function pool(): int
    {
        $subcommand = $this->options->subcommand();
        if ($subcommand === 'create') {
            return $this->poolCreate();
        }
        if ($subcommand === 'inspect') {
            return $this->poolInspect();
        }
        if ($subcommand === 'list') {
            return $this->poolList();
        }
        if ($subcommand === 'revoke') {
            return $this->poolRevoke();
        }
        if ($subcommand === 'generate') {
            return $this->poolGenerate();
        }
        return $this->error('Unknown pool subcommand.');
    }

    private function poolCreate(): int
    {
        if ($this->database === null) {
            return $this->error('A database DSN is required.');
        }
        $clientId = $this->options->positional(1);
        $poolId = $this->options->positional(2);
        if ($clientId === null || $poolId === null) {
            return $this->error('Usage: mkap pool create <client_id> <pool_id> --count=1000 --selection=3 [--dry-run]');
        }
        $count = (int) ($this->options->value('count') ?? '1000');
        $selection = (int) ($this->options->value('selection') ?? '3');
        $poolVersion = (int) ($this->options->value('pool-version') ?? '1');
        $length = (int) ($this->options->value('identifier-length') ?? '6');
        $alphabet = $this->options->value('alphabet')
            ?? IdentifierGenerator::DEFAULT_ALPHABET;
        if ($count < 1 || $count > 1_000_000
            || $selection < 1 || $selection > $count
            || $poolVersion < 1
            || $length < 1 || $length > 256
            || strlen($alphabet) < 2
            || strlen($alphabet) !== count(array_unique(str_split($alphabet)))) {
            return $this->error('Pool parameters are invalid.');
        }
        if ($this->options->has('dry-run')) {
            $this->audit->record('pool.create', 'dry-run', [
                'client_id' => $clientId,
                'pool_id' => $poolId,
                'count' => $count,
                'selection' => $selection,
            ]);
            fwrite(STDOUT, "DRY-RUN pool creation: {$clientId}/{$poolId}, {$count} keys, selection {$selection}." . PHP_EOL);
            return 0;
        }
        if (!$this->confirmed()) {
            return $this->error('Destructive pool creation requires --confirm.');
        }

        try {
            $this->database->beginTransaction();
            $statement = $this->database->prepare(
                'SELECT id, status FROM mkap_clients WHERE client_identifier = ?'
            );
            $statement->execute([$clientId]);
            $client = $statement->fetch(PDO::FETCH_ASSOC);
            if ($client === false) {
                $this->database->rollBack();
                $this->audit->record('pool.create', 'failed', ['reason' => 'unknown-client']);
                return $this->error('Unknown client identifier.');
            }
            $statement = $this->database->prepare(
                'SELECT id FROM mkap_key_pools '
                . 'WHERE client_id = ? AND pool_identifier = ? AND pool_version = ?'
            );
            $statement->execute([$client['id'], $poolId, $poolVersion]);
            if ($statement->fetchColumn() !== false) {
                $this->database->rollBack();
                $this->audit->record('pool.create', 'failed', ['reason' => 'pool-exists']);
                return $this->error('Pool and version already exist.');
            }
            $now = gmdate('Y-m-d H:i:s');
            $statement = $this->database->prepare(
                'INSERT INTO mkap_key_pools '
                . '(client_id, pool_identifier, pool_version, identifier_length, identifier_alphabet, '
                . 'secret_length_bytes, key_count, selection_count, status, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, ?, 32, ?, ?, \'active\', ?, ?)'
            );
            $statement->execute([
                $client['id'],
                $poolId,
                $poolVersion,
                $length,
                $alphabet,
                $count,
                $selection,
                $now,
                $now,
            ]);
            $this->database->commit();
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            $this->audit->record('pool.create', 'failed', ['reason' => 'database-error']);
            return $this->error('Pool creation failed: ' . $error->getMessage());
        }
        $this->audit->record('pool.create', 'success', [
            'client_id' => $clientId,
            'pool_id' => $poolId,
        ]);
        fwrite(STDOUT, "Created pool {$poolId} for {$clientId}. Run pool generate to create keys." . PHP_EOL);
        return 0;
    }

    private function poolInspect(): int
    {
        if ($this->database === null) {
            return $this->error('A database DSN is required.');
        }
        $poolId = $this->options->positional(1);
        if ($poolId === null) {
            return $this->error('Usage: mkap pool inspect <pool_id>');
        }
        $statement = $this->database->prepare(
            'SELECT * FROM mkap_key_pools WHERE pool_identifier = ? '
            . 'ORDER BY pool_version DESC LIMIT 1'
        );
        $statement->execute([$poolId]);
        $pool = $statement->fetch(PDO::FETCH_ASSOC);
        if ($pool === false) {
            return $this->error('Unknown pool identifier.');
        }
        $statement = $this->database->prepare(
            'SELECT status, COUNT(*) AS count FROM mkap_authentication_keys '
            . 'WHERE pool_id = ? GROUP BY status'
        );
        $statement->execute([$pool['id']]);
        $counts = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(string) $row['status']] = (int) $row['count'];
        }
        fwrite(STDOUT, json_encode([
            'pool_id' => $pool['pool_identifier'],
            'pool_version' => (int) $pool['pool_version'],
            'status' => $pool['status'],
            'identifier_length' => (int) $pool['identifier_length'],
            'selection_count' => (int) $pool['selection_count'],
            'configured_key_count' => (int) $pool['key_count'],
            'active_keys' => $counts['active'] ?? 0,
            'revoked_keys' => $counts['revoked'] ?? 0,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        $this->audit->record('pool.inspect', 'success', ['pool_id' => $poolId]);
        return 0;
    }

    private function poolList(): int
    {
        if ($this->database === null) {
            return $this->error('A database DSN is required.');
        }
        $rows = $this->database->query(
            'SELECT pool_identifier, pool_version, status, key_count, selection_count '
            . 'FROM mkap_key_pools ORDER BY pool_identifier, pool_version'
        )->fetchAll(PDO::FETCH_ASSOC);
        fwrite(STDOUT, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        $this->audit->record('pool.list', 'success', ['count' => count($rows)]);
        return 0;
    }

    private function poolRevoke(): int
    {
        if ($this->database === null) {
            return $this->error('A database DSN is required.');
        }
        $poolId = $this->options->positional(1);
        if ($poolId === null) {
            return $this->error('Usage: mkap pool revoke <pool_id> [--dry-run] [--confirm]');
        }
        if ($this->options->has('dry-run')) {
            $this->audit->record('pool.revoke', 'dry-run', ['pool_id' => $poolId]);
            fwrite(STDOUT, "DRY-RUN revoke pool {$poolId}." . PHP_EOL);
            return 0;
        }
        if (!$this->confirmed()) {
            return $this->error('Pool revocation requires --confirm.');
        }
        $statement = $this->database->prepare(
            'UPDATE mkap_key_pools SET status = ?, updated_at = ? '
            . 'WHERE pool_identifier = ? AND status = ?'
        );
        $now = gmdate('Y-m-d H:i:s');
        $statement->execute(['revoked', $now, $poolId, 'active']);
        if ($statement->rowCount() !== 1) {
            $this->audit->record('pool.revoke', 'failed', ['pool_id' => $poolId]);
            return $this->error('Unknown active pool or pool already revoked.');
        }
        $this->audit->record('pool.revoke', 'success', ['pool_id' => $poolId]);
        fwrite(STDOUT, "Revoked pool {$poolId}." . PHP_EOL);
        return 0;
    }

    private function poolGenerate(): int
    {
        if ($this->database === null) {
            return $this->error('A database DSN is required.');
        }
        $poolId = $this->options->positional(1);
        $poolVersion = (int) ($this->options->value('pool-version') ?? '1');
        if ($poolId === null) {
            return $this->error(
                'Usage: mkap pool generate <pool_id> [--pool-version=1] [--master-key-stdin] [--dry-run] [--confirm]'
            );
        }
        $pool = $this->database->prepare(
            'SELECT * FROM mkap_key_pools '
            . 'WHERE pool_identifier = ? AND pool_version = ?'
        );
        $pool->execute([$poolId, $poolVersion]);
        $metadata = $pool->fetch(PDO::FETCH_ASSOC);
        if ($metadata === false) {
            return $this->error('Unknown pool identifier or version.');
        }
        $count = (int) $metadata['key_count'];
        if ($this->options->has('dry-run')) {
            $this->audit->record('pool.generate', 'dry-run', [
                'pool_id' => $poolId,
                'count' => $count,
            ]);
            fwrite(STDOUT, "DRY-RUN generate {$count} keys for {$poolId}." . PHP_EOL);
            return 0;
        }
        if (!$this->confirmed()) {
            return $this->error('Key generation requires --confirm.');
        }
        $secretHex = $this->readMasterKeyHex();
        if ($secretHex === null) {
            return $this->error(
                'A master key is required via MKAP_CLI_MASTER_KEY_HEX or --master-key-stdin.'
            );
        }
        $decoded = @hex2bin($secretHex);
        if ($decoded === false || strlen($decoded) !== 32) {
            return $this->error('Master key must be exactly 32 bytes in hexadecimal.');
        }

        $random = new SecureRandomGenerator();
        $identifierGenerator = new IdentifierGenerator(
            $random,
            (string) $metadata['identifier_alphabet'],
            (int) $metadata['identifier_length']
        );
        $cipher = new SecretCipher($random);
        $masterKey = new MasterKey($decoded, 1);

        try {
            $this->database->beginTransaction();
            $existing = $this->database->prepare(
                'SELECT public_identifier FROM mkap_authentication_keys WHERE pool_id = ?'
            );
            $existing->execute([$metadata['id']]);
            $identifiers = $existing->fetchAll(PDO::FETCH_COLUMN);
            $insert = $this->database->prepare(
                'INSERT INTO mkap_authentication_keys '
                . '(pool_id, public_identifier, encrypted_secret, encryption_key_version, '
                . 'status, created_at) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $now = gmdate('Y-m-d H:i:s');
            for ($index = 0; $index < $count; $index++) {
                $identifier = $identifierGenerator->generate($identifiers);
                $identifiers[] = $identifier;
                $secret = $random->bytes(32);
                $encrypted = $cipher->encrypt(
                    $secret,
                    $masterKey,
                    KeyAssociatedData::forKey($poolId, $identifier)
                );
                $insert->execute([
                    $metadata['id'],
                    $identifier,
                    $encrypted->toBinary(),
                    $encrypted->keyVersion,
                    KeyStatus::Active->value,
                    $now,
                ]);
                if (($index + 1) % 100 === 0) {
                    fwrite(STDOUT, 'Generated ' . ($index + 1) . " of {$count} keys." . PHP_EOL);
                }
            }
            $this->database->commit();
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            $this->audit->record('pool.generate', 'failed', ['pool_id' => $poolId]);
            return $this->error('Key generation failed: ' . $error->getMessage());
        }
        $this->audit->record('pool.generate', 'success', [
            'pool_id' => $poolId,
            'count' => $count,
        ]);
        fwrite(STDOUT, "Generated {$count} keys for {$poolId}; secret values were not printed." . PHP_EOL);
        return 0;
    }

    private function key(): int
    {
        $subcommand = $this->options->subcommand();
        if ($subcommand === 'revoke') {
            return $this->keyRevoke();
        }
        if ($subcommand === 'rotate') {
            return $this->keyRotate();
        }
        if ($subcommand === 'inspect') {
            return $this->keyInspect();
        }
        return $this->error('Unknown key subcommand.');
    }

    private function keyInspect(): int
    {
        if ($this->database === null) {
            return $this->error('A database DSN is required.');
        }
        $poolId = $this->options->positional(1);
        $identifier = $this->options->positional(2);
        if ($poolId === null || $identifier === null) {
            return $this->error('Usage: mkap key inspect <pool_id> <identifier>');
        }
        $statement = $this->database->prepare(
            'SELECT k.public_identifier, k.status, k.encryption_key_version, k.created_at, k.revoked_at '
            . 'FROM mkap_authentication_keys k '
            . 'JOIN mkap_key_pools p ON p.id = k.pool_id '
            . 'WHERE p.pool_identifier = ? AND k.public_identifier = ?'
        );
        $statement->execute([$poolId, $identifier]);
        $key = $statement->fetch(PDO::FETCH_ASSOC);
        if ($key === false) {
            return $this->error('Unknown key identifier.');
        }
        unset($key['encrypted_secret']);
        fwrite(STDOUT, json_encode($key, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        $this->audit->record('key.inspect', 'success', [
            'pool_id' => $poolId,
            'identifier' => $identifier,
        ]);
        return 0;
    }

    private function keyRevoke(): int
    {
        if ($this->database === null) {
            return $this->error('A database DSN is required.');
        }
        $poolId = $this->options->positional(1);
        $identifier = $this->options->positional(2);
        if ($poolId === null || $identifier === null) {
            return $this->error('Usage: mkap key revoke <pool_id> <identifier> [--dry-run] [--confirm]');
        }
        if ($this->options->has('dry-run')) {
            $this->audit->record('key.revoke', 'dry-run', [
                'pool_id' => $poolId,
                'identifier' => $identifier,
            ]);
            fwrite(STDOUT, "DRY-RUN revoke {$poolId}/{$identifier}." . PHP_EOL);
            return 0;
        }
        if (!$this->confirmed()) {
            return $this->error('Key revocation requires --confirm.');
        }
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->database->prepare(
            'UPDATE mkap_authentication_keys SET status = ?, revoked_at = ? '
            . 'WHERE pool_id = (SELECT id FROM mkap_key_pools WHERE pool_identifier = ?) '
            . 'AND public_identifier = ? AND status = ?'
        );
        $statement->execute(['revoked', $now, $poolId, $identifier, 'active']);
        if ($statement->rowCount() !== 1) {
            $this->audit->record('key.revoke', 'failed', ['pool_id' => $poolId, 'identifier' => $identifier]);
            return $this->error('Unknown active key or key already revoked.');
        }
        $this->audit->record('key.revoke', 'success', [
            'pool_id' => $poolId,
            'identifier' => $identifier,
        ]);
        fwrite(STDOUT, "Revoked {$poolId}/{$identifier}." . PHP_EOL);
        return 0;
    }

    private function keyRotate(): int
    {
        if ($this->database === null) {
            return $this->error('A database DSN is required.');
        }
        $poolId = $this->options->positional(1);
        $identifier = $this->options->positional(2);
        if ($poolId === null || $identifier === null) {
            return $this->error(
                'Usage: mkap key rotate <pool_id> <identifier> [--master-key-stdin] [--dry-run] [--confirm]'
            );
        }
        if ($this->options->has('dry-run')) {
            $this->audit->record('key.rotate', 'dry-run', [
                'pool_id' => $poolId,
                'identifier' => $identifier,
            ]);
            fwrite(STDOUT, "DRY-RUN rotate {$poolId}/{$identifier}." . PHP_EOL);
            return 0;
        }
        if (!$this->confirmed()) {
            return $this->error('Key rotation requires --confirm.');
        }
        $secretHex = $this->readMasterKeyHex();
        if ($secretHex === null) {
            return $this->error(
                'A master key is required via MKAP_CLI_MASTER_KEY_HEX or --master-key-stdin.'
            );
        }
        $decoded = @hex2bin($secretHex);
        if ($decoded === false || strlen($decoded) !== 32) {
            return $this->error('Master key must be exactly 32 bytes in hexadecimal.');
        }

        try {
            $this->database->beginTransaction();
            $statement = $this->database->prepare(
                'SELECT p.*, k.id AS old_key_id '
                . 'FROM mkap_key_pools p '
                . 'JOIN mkap_authentication_keys k ON k.pool_id = p.id '
                . 'WHERE p.pool_identifier = ? AND k.public_identifier = ? AND k.status = ?'
            );
            $statement->execute([$poolId, $identifier, 'active']);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                $this->database->rollBack();
                return $this->error('Unknown active key or key already revoked.');
            }
            $existing = $this->database->prepare(
                'SELECT public_identifier FROM mkap_authentication_keys WHERE pool_id = ?'
            );
            $existing->execute([$row['id']]);
            $identifiers = $existing->fetchAll(PDO::FETCH_COLUMN);
            $random = new SecureRandomGenerator();
            $generator = new IdentifierGenerator(
                $random,
                (string) $row['identifier_alphabet'],
                (int) $row['identifier_length']
            );
            $newIdentifier = $generator->generate($identifiers);
            $secret = $random->bytes(32);
            $cipher = new SecretCipher($random);
            $masterKey = new MasterKey($decoded, 1);
            $encrypted = $cipher->encrypt(
                $secret,
                $masterKey,
                KeyAssociatedData::forKey($poolId, $newIdentifier)
            );
            $now = gmdate('Y-m-d H:i:s');
            $revoke = $this->database->prepare(
                'UPDATE mkap_authentication_keys SET status = ?, revoked_at = ? WHERE id = ?'
            );
            $revoke->execute(['revoked', $now, $row['old_key_id']]);
            $insert = $this->database->prepare(
                'INSERT INTO mkap_authentication_keys '
                . '(pool_id, public_identifier, encrypted_secret, encryption_key_version, '
                . 'status, created_at) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                $row['id'],
                $newIdentifier,
                $encrypted->toBinary(),
                $encrypted->keyVersion,
                'active',
                $now,
            ]);
            $newKeyId = (int) $this->database->lastInsertId();
            $rotation = $this->database->prepare(
                'INSERT INTO mkap_key_rotations '
                . '(pool_id, old_key_id, new_key_id, reason, created_at) '
                . 'VALUES (?, ?, ?, ?, ?)'
            );
            $rotation->execute([
                $row['id'],
                $row['old_key_id'],
                $newKeyId,
                $this->options->value('reason') ?? 'CLI key replacement',
                $now,
            ]);
            $this->database->commit();
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            $this->audit->record('key.rotate', 'failed', ['pool_id' => $poolId, 'identifier' => $identifier]);
            return $this->error('Key rotation failed: ' . $error->getMessage());
        }
        $this->audit->record('key.rotate', 'success', [
            'pool_id' => $poolId,
            'old_identifier' => $identifier,
            'new_identifier' => $newIdentifier,
        ]);
        fwrite(STDOUT, "Rotated {$poolId}/{$identifier} to {$newIdentifier}. Issue a new export." . PHP_EOL);
        return 0;
    }

    private function export(): int
    {
        $subcommand = $this->options->subcommand();
        if ($subcommand === 'validate') {
            return $this->exportValidate();
        }
        if ($subcommand === 'create') {
            return $this->exportCreate();
        }
        return $this->error('Unknown export subcommand. Use create or validate.');
    }

    private function exportCreate(): int
    {
        if ($this->database === null) {
            return $this->error('A database DSN is required.');
        }
        $poolId = $this->options->positional(1);
        $output = $this->options->value('output');
        if ($poolId === null || $output === null) {
            return $this->error(
                'Usage: mkap export create <pool_id> --output=<path> [--master-key-stdin] [--development-json] [--dry-run] [--confirm]'
            );
        }
        $statement = $this->database->prepare(
            'SELECT p.*, c.client_identifier '
            . 'FROM mkap_key_pools p '
            . 'JOIN mkap_clients c ON c.id = p.client_id '
            . 'WHERE p.pool_identifier = ? ORDER BY p.pool_version DESC LIMIT 1'
        );
        $statement->execute([$poolId]);
        $pool = $statement->fetch(PDO::FETCH_ASSOC);
        if ($pool === false) {
            return $this->error('Unknown pool identifier.');
        }

        $rows = $this->database->prepare(
            'SELECT public_identifier, encrypted_secret '
            . 'FROM mkap_authentication_keys '
            . 'WHERE pool_id = ? AND status = ? '
            . 'ORDER BY public_identifier'
        );
        $rows->execute([$pool['id'], 'active']);
        $active = $rows->fetchAll(PDO::FETCH_ASSOC);
        if ($active === []) {
            return $this->error('Pool has no active keys.');
        }
        if ($this->options->has('dry-run')) {
            $this->audit->record('export.create', 'dry-run', [
                'pool_id' => $poolId,
                'key_count' => count($active),
                'output' => $output,
            ]);
            fwrite(STDOUT, 'DRY-RUN create export with '
                . count($active) . " active key(s) at {$output}." . PHP_EOL);
            return 0;
        }
        if (!$this->confirmed()) {
            return $this->error('Export creation requires --confirm.');
        }

        $secretHex = $this->readMasterKeyHex();
        if ($secretHex === null) {
            return $this->error(
                'A master key is required via MKAP_CLI_MASTER_KEY_HEX or --master-key-stdin.'
            );
        }
        $decoded = @hex2bin($secretHex);
        if ($decoded === false || strlen($decoded) !== 32) {
            return $this->error('Master key must be exactly 32 bytes in hexadecimal.');
        }

        try {
            $random = new SecureRandomGenerator();
            $cipher = new SecretCipher($random);
            $masterKey = new MasterKey($decoded, 1);
            $keys = [];
            foreach ($active as $row) {
                $encrypted = EncryptedSecret::fromBinary((string) $row['encrypted_secret']);
                $secret = $cipher->decrypt(
                    $encrypted,
                    $masterKey,
                    KeyAssociatedData::forKey($poolId, (string) $row['public_identifier'])
                );
                $keys[] = [
                    'id' => (string) $row['public_identifier'],
                    'secret' => Base64Url::encode($secret),
                ];
            }
            $exportVersion = 1;
            $statement = $this->database->prepare(
                'SELECT COALESCE(MAX(export_version), 0) FROM mkap_key_exports WHERE pool_id = ?'
            );
            $statement->execute([$pool['id']]);
            $exportVersion += (int) $statement->fetchColumn();
            $export = new KeyExport(
                1,
                1,
                (string) $pool['client_identifier'],
                $poolId,
                (int) $pool['pool_version'],
                $exportVersion,
                gmdate('Y-m-d\TH:i:s\Z'),
                (int) $pool['identifier_length'],
                (string) $pool['identifier_alphabet'],
                32,
                $keys
            );
            $exports = new ExportManager($cipher);
            $development = $this->options->has('development-json');
            if ($development) {
                $payload = $exports->createPlaintextJson($export);
            } else {
                return $this->error(
                    'Protected exports are not enabled until PHP, JavaScript, and Flutter client loaders support authenticated envelope opening.'
                );
            }

            $directory = dirname($output);
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                return $this->error('Unable to create export directory.');
            }
            $previousUmask = umask(0077);
            $temporary = $directory . DIRECTORY_SEPARATOR
                . '.' . basename($output) . '.'
                . bin2hex(random_bytes(8))
                . '.tmp';
            $handle = @fopen($temporary, 'xb');
            if ($handle === false) {
                umask($previousUmask);
                return $this->error('Unable to create a secure export temp file.');
            }
            $bytes = strlen($payload);
            $written = 0;
            while ($written < $bytes) {
                $chunk = fwrite($handle, substr($payload, $written));
                if ($chunk === false || $chunk === 0) {
                    fclose($handle);
                    @unlink($temporary);
                    umask($previousUmask);
                    return $this->error('Unable to write export file.');
                }
                $written += $chunk;
            }
            fflush($handle);
            fclose($handle);
            @chmod($temporary, 0600);
            if (!@rename($temporary, $output)) {
                @unlink($temporary);
                umask($previousUmask);
                return $this->error('Unable to write export file.');
            }
            umask($previousUmask);
            @chmod($output, 0600);

            $insert = $this->database->prepare(
                'INSERT INTO mkap_key_exports '
                . '(pool_id, export_version, format_version, key_count, checksum, status, created_at) '
                . 'VALUES (?, ?, 1, ?, ?, ?, ?)'
            );
            $insert->execute([
                $pool['id'],
                $exportVersion,
                count($keys),
                $exports->checksum($export),
                'active',
                gmdate('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $error) {
            $this->audit->record('export.create', 'failed', ['pool_id' => $poolId]);
            return $this->error('Export creation failed: ' . $error->getMessage());
        }

        $this->audit->record('export.create', 'success', [
            'pool_id' => $poolId,
            'export_version' => $exportVersion,
            'development_json' => $development,
        ]);
        fwrite(STDOUT, 'Export ' . $exportVersion . ' written with mode 0600; secret values were not printed.' . PHP_EOL);
        return 0;
    }

    private function exportValidate(): int
    {
        $path = $this->options->positional(1);
        if ($path === null || !is_file($path) || !is_readable($path)) {
            return $this->error('Usage: mkap export validate <path>');
        }
        if (filesize($path) === false || filesize($path) > 67108864) {
            return $this->error('Export exceeds the configured 64 MiB size limit.');
        }
        try {
            $json = file_get_contents($path);
            if ($json === false) {
                throw new \RuntimeException('Unable to read export.');
            }
            $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $keys = (new ExportValidator())->validate($manifest);
            fwrite(STDOUT, json_encode([
                'valid' => true,
                'client_id' => $manifest['client_id'],
                'pool_id' => $manifest['pool_id'],
                'pool_version' => $manifest['pool_version'],
                'export_version' => $manifest['export_version'],
                'key_count' => count($keys),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
            $this->audit->record('export.validate', 'success', ['path' => $path]);
            return 0;
        } catch (\Throwable $error) {
            $this->audit->record('export.validate', 'failed', ['path' => $path]);
            return $this->error('Invalid export: ' . $error->getMessage());
        }
    }

    private function challenge(): int
    {
        if ($this->database === null) {
            return $this->error('A database DSN is required.');
        }
        $subcommand = $this->options->subcommand();
        if ($subcommand !== 'cleanup') {
            return $this->error('Unknown challenge subcommand.');
        }
        $count = (int) $this->database->query(
            "SELECT COUNT(*) FROM mkap_authentication_challenges "
            . "WHERE expires_at <= '" . gmdate('Y-m-d H:i:s') . "'"
        )->fetchColumn();
        if ($this->options->has('dry-run')) {
            $this->audit->record('challenge.cleanup', 'dry-run', ['eligible' => $count]);
            fwrite(STDOUT, "DRY-RUN eligible expired challenges: {$count}." . PHP_EOL);
            return 0;
        }
        if (!$this->confirmed()) {
            return $this->error('Challenge cleanup requires --confirm.');
        }
        $statement = $this->database->prepare(
            'DELETE FROM mkap_authentication_challenges '
            . 'WHERE expires_at <= ? '
            . 'AND NOT EXISTS ('
            . 'SELECT 1 FROM mkap_authentication_logs '
            . 'WHERE mkap_authentication_logs.challenge_id = mkap_authentication_challenges.id'
            . ')'
        );
        $statement->execute([gmdate('Y-m-d H:i:s')]);
        $deleted = $statement->rowCount();
        $this->audit->record('challenge.cleanup', 'success', ['deleted' => $deleted]);
        fwrite(STDOUT, "Removed {$deleted} expired challenge(s); audit-linked challenges retained." . PHP_EOL);
        return 0;
    }

    private function auditEvents(): int
    {
        if ($this->database === null) {
            return $this->error('A database DSN is required.');
        }
        $limit = (int) ($this->options->value('limit') ?? '50');
        if ($limit < 1 || $limit > 1000) {
            return $this->error('Audit limit must be between 1 and 1000.');
        }
        $statement = $this->database->prepare(
            'SELECT event_type, result, failure_reason, created_at '
            . 'FROM mkap_authentication_logs '
            . 'ORDER BY created_at DESC LIMIT ?'
        );
        $statement->bindValue(1, $limit, PDO::PARAM_INT);
        $statement->execute();
        fwrite(STDOUT, json_encode(
            $statement->fetchAll(PDO::FETCH_ASSOC),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL);
        return 0;
    }

    private function confirmed(): bool
    {
        return $this->options->has('confirm');
    }

    private function readMasterKeyHex(): ?string
    {
        if ($this->options->has('master-key-hex')) {
            return null;
        }
        if ($this->options->has('master-key-stdin')) {
            if (defined('STDIN')) {
                $value = fgets(STDIN);
                return is_string($value) ? trim($value) : null;
            }
            $value = stream_get_contents(STDIN);
            return is_string($value) ? trim($value) : null;
        }
        $value = getenv('MKAP_CLI_MASTER_KEY_HEX');
        if (!is_string($value) || $value === '') {
            return null;
        }
        return trim($value);
    }

    private function help(): int
    {
        fwrite(STDOUT, <<<'TEXT'
MKAP administrative CLI

mkap client create <client_id> [--name=<name>] [--dry-run] [--confirm]
mkap client inspect <client_id>
mkap client revoke <client_id> [--dry-run] [--confirm]
mkap pool create <client_id> <pool_id> [--count=1000] [--selection=3] [--dry-run] [--confirm]
mkap pool inspect <pool_id>
mkap pool list
mkap pool revoke <pool_id> [--dry-run] [--confirm]
mkap pool generate <pool_id> [--master-key-stdin] [--dry-run] [--confirm]
mkap key inspect <pool_id> <identifier>
mkap key revoke <pool_id> <identifier> [--dry-run] [--confirm]
mkap key rotate <pool_id> <identifier> [--master-key-stdin] [--dry-run] [--confirm]
mkap export create <pool_id> --output=<path> [--master-key-stdin] [--development-json] [--dry-run] [--confirm]
mkap export validate <path>
mkap challenge cleanup [--dry-run] [--confirm]
mkap audit list [--limit=50]

Destructive operations require --confirm unless --dry-run is supplied.
Secret values are never printed. Use a 32-byte hexadecimal master key only
through a protected environment and external secret-management process.
TEXT
            . PHP_EOL);
        return 0;
    }

    private function error(string $message): int
    {
        fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL);
        return 1;
    }
}
