<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\LaravelServer\Console;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use ProIT365\MultiKeyAuth\Core\Key\IdentifierGenerator;
use ProIT365\MultiKeyAuth\Core\Key\KeyStatus;
use ProIT365\MultiKeyAuth\Core\Random\SecureRandomGenerator;
use ProIT365\MultiKeyAuth\Core\Server\MasterKeyProviderInterface;
use ProIT365\MultiKeyAuth\Core\Storage\EncryptedSecret;
use ProIT365\MultiKeyAuth\Core\Storage\KeyAssociatedData;
use ProIT365\MultiKeyAuth\Core\Storage\SecretCipher;

final class GeneratePoolCommand extends Command
{
    protected $signature = 'mkap:pools:generate
        {client_id : Public client identifier}
        {pool_id : Public pool identifier}
        {--count=1000 : Number of keys to generate}
        {--selection=3 : Challenge selection count}
        {--pool-version=1 : Pool version}
        {--identifier-length=6 : Fixed identifier length}
        {--alphabet=abcdefghijklmnopqrstuvwxyz0123456789 : Identifier alphabet}
        {--force : Skip confirmation}';

    protected $description = 'Generate and encrypt a new MKAP key pool';

    public function handle(
        DatabaseManager $database,
        MasterKeyProviderInterface $masterKeys,
        SecretCipher $cipher
    ): int {
        $count = (int) $this->option('count');
        $selection = (int) $this->option('selection');
        $poolVersion = (int) $this->option('pool-version');
        $identifierLength = (int) $this->option('identifier-length');
        $alphabet = (string) $this->option('alphabet');

        if ($count < 1 || $count > 1_000_000
            || $selection < 1 || $selection > $count
            || $poolVersion < 1
            || $identifierLength < 1 || $identifierLength > 256
            || strlen($alphabet) < 2
            || strlen($alphabet) !== count(array_unique(str_split($alphabet)))) {
            $this->error('Invalid pool generation parameters.');
            return self::FAILURE;
        }

        $clientId = (string) $this->argument('client_id');
        $poolId = (string) $this->argument('pool_id');
        if ($clientId === '' || $poolId === '') {
            $this->error('Client and pool identifiers are required.');
            return self::FAILURE;
        }
        if (!$this->option('force')
            && !$this->confirm("Generate {$count} keys for pool {$poolId}?")) {
            $this->warn('Pool generation cancelled.');
            return self::SUCCESS;
        }

        $connection = $database->connection(config('multikey-auth-server.database_connection'));
        $client = $connection->table('mkap_clients')
            ->where('client_identifier', $clientId)
            ->first();
        if ($client === null) {
            $this->error('Unknown client identifier.');
            return self::FAILURE;
        }

        $exists = $connection->table('mkap_key_pools')
            ->where('client_id', $client->id)
            ->where('pool_identifier', $poolId)
            ->where('pool_version', $poolVersion)
            ->exists();
        if ($exists) {
            $this->error('That pool and version already exist.');
            return self::FAILURE;
        }

        $random = new SecureRandomGenerator();
        $identifierGenerator = new IdentifierGenerator($random, $alphabet, $identifierLength);
        $masterKey = $masterKeys->forPool($poolId);

        try {
            $connection->transaction(function () use (
                $connection,
                $client,
                $poolId,
                $poolVersion,
                $identifierLength,
                $alphabet,
                $count,
                $selection,
                $identifierGenerator,
                $random,
                $masterKey,
                $cipher
            ): void {
                $now = gmdate('Y-m-d H:i:s');
                $poolRecordId = $connection->table('mkap_key_pools')->insertGetId([
                    'client_id' => $client->id,
                    'pool_identifier' => $poolId,
                    'pool_version' => $poolVersion,
                    'identifier_length' => $identifierLength,
                    'identifier_alphabet' => $alphabet,
                    'secret_length_bytes' => 32,
                    'key_count' => $count,
                    'selection_count' => $selection,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $identifiers = [];
                for ($index = 0; $index < $count; $index++) {
                    $identifier = $identifierGenerator->generate($identifiers);
                    $identifiers[] = $identifier;
                    $secret = $random->bytes(32);
                    $encrypted = $cipher->encrypt(
                        $secret,
                        $masterKey,
                        KeyAssociatedData::forKey($poolId, $identifier)
                    );
                    $connection->table('mkap_authentication_keys')->insert([
                        'pool_id' => $poolRecordId,
                        'public_identifier' => $identifier,
                        'encrypted_secret' => $encrypted->toBinary(),
                        'encryption_key_version' => $encrypted->keyVersion,
                        'status' => KeyStatus::Active->value,
                        'generation_batch_id' => null,
                        'created_at' => $now,
                        'revoked_at' => null,
                    ]);

                    if (($index + 1) % 100 === 0) {
                        $this->info('Generated ' . ($index + 1) . " of {$count} keys.");
                    }
                }
            });
        } catch (\Throwable $error) {
            $this->error('Pool generation failed: ' . $error->getMessage());
            return self::FAILURE;
        }

        $this->info("Generated {$count} active keys for pool {$poolId}. Secret values were not printed.");
        return self::SUCCESS;
    }
}
