<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\LaravelServer\Console;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use ProIT365\MultiKeyAuth\Core\Key\IdentifierGenerator;
use ProIT365\MultiKeyAuth\Core\Key\KeyStatus;
use ProIT365\MultiKeyAuth\Core\Random\SecureRandomGenerator;
use ProIT365\MultiKeyAuth\Core\Server\MasterKeyProviderInterface;
use ProIT365\MultiKeyAuth\Core\Storage\KeyAssociatedData;
use ProIT365\MultiKeyAuth\Core\Storage\SecretCipher;

final class RotateKeyCommand extends Command
{
    protected $signature = 'mkap:keys:rotate
        {pool_id : Public pool identifier}
        {identifier : Public key identifier to replace}
        {--reason=Scheduled key replacement : Rotation reason}
        {--force : Skip confirmation}';

    protected $description = 'Atomically revoke and replace one active key';

    public function handle(
        DatabaseManager $database,
        MasterKeyProviderInterface $masterKeys,
        SecretCipher $cipher
    ): int {
        $poolId = (string) $this->argument('pool_id');
        $oldIdentifier = (string) $this->argument('identifier');
        $reason = trim((string) $this->option('reason'));
        if ($reason === '') {
            $this->error('Rotation reason is required.');
            return self::FAILURE;
        }
        if (!$this->option('force')
            && !$this->confirm("Replace key {$oldIdentifier} in pool {$poolId}?")) {
            $this->warn('Key rotation cancelled.');
            return self::SUCCESS;
        }

        $connection = $database->connection(config('multikey-auth-server.database_connection'));
        $pool = $connection->table('mkap_key_pools')
            ->where('pool_identifier', $poolId)
            ->orderByDesc('pool_version')
            ->first();
        if ($pool === null) {
            $this->error('Unknown pool identifier.');
            return self::FAILURE;
        }

        $random = new SecureRandomGenerator();
        $identifierGenerator = new IdentifierGenerator(
            $random,
            $pool->identifier_alphabet,
            (int) $pool->identifier_length
        );
        $existing = $connection->table('mkap_authentication_keys')
            ->where('pool_id', $pool->id)
            ->pluck('public_identifier')
            ->all();
        $old = $connection->table('mkap_authentication_keys')
            ->where('pool_id', $pool->id)
            ->where('public_identifier', $oldIdentifier)
            ->where('status', 'active')
            ->first();
        if ($old === null) {
            $this->error('Unknown active key or key already revoked.');
            return self::FAILURE;
        }

        $newIdentifier = $identifierGenerator->generate($existing);
        $secret = $random->bytes(32);
        $masterKey = $masterKeys->forPool($poolId);
        $encrypted = $cipher->encrypt(
            $secret,
            $masterKey,
            KeyAssociatedData::forKey($poolId, $newIdentifier)
        );
        $now = gmdate('Y-m-d H:i:s');

        try {
            $connection->transaction(function () use (
                $connection,
                $pool,
                $poolId,
                $old,
                $oldIdentifier,
                $newIdentifier,
                $encrypted,
                $reason,
                $now
            ): void {
                $revoked = $connection->table('mkap_authentication_keys')
                    ->where('id', $old->id)
                    ->where('status', 'active')
                    ->update([
                        'status' => 'revoked',
                        'revoked_at' => $now,
                    ]);
                if ($revoked !== 1) {
                    throw new \RuntimeException('Key changed before rotation.');
                }

                $newKeyId = $connection->table('mkap_authentication_keys')->insertGetId([
                    'pool_id' => $pool->id,
                    'public_identifier' => $newIdentifier,
                    'encrypted_secret' => $encrypted->toBinary(),
                    'encryption_key_version' => $encrypted->keyVersion,
                    'status' => KeyStatus::Active->value,
                    'generation_batch_id' => null,
                    'created_at' => $now,
                    'revoked_at' => null,
                ]);
                $connection->table('mkap_key_rotations')->insert([
                    'pool_id' => $pool->id,
                    'old_key_id' => $old->id,
                    'new_key_id' => $newKeyId,
                    'reason' => $reason,
                    'created_at' => $now,
                ]);
            });
        } catch (\Throwable $error) {
            $this->error('Key rotation failed: ' . $error->getMessage());
            return self::FAILURE;
        }

        $this->info("Rotated key {$oldIdentifier} to {$newIdentifier}. Issue a new export before use.");
        return self::SUCCESS;
    }
}
