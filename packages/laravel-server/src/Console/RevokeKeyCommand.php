<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\LaravelServer\Console;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;

final class RevokeKeyCommand extends Command
{
    protected $signature = 'mkap:keys:revoke
        {pool_id : Public pool identifier}
        {identifier : Public key identifier}
        {--force : Skip confirmation}';

    protected $description = 'Immediately revoke one public key identifier';

    public function handle(DatabaseManager $database): int
    {
        $poolId = (string) $this->argument('pool_id');
        $identifier = (string) $this->argument('identifier');
        if (!$this->option('force')
            && !$this->confirm("Revoke key {$identifier} from pool {$poolId}?")) {
            $this->warn('Key revocation cancelled.');
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

        $now = gmdate('Y-m-d H:i:s');
        $updated = $connection->table('mkap_authentication_keys')
            ->where('pool_id', $pool->id)
            ->where('public_identifier', $identifier)
            ->where('status', 'active')
            ->update([
                'status' => 'revoked',
                'revoked_at' => $now,
            ]);
        if ($updated !== 1) {
            $this->error('Unknown active key or key already revoked.');
            return self::FAILURE;
        }

        $this->info("Key {$identifier} revoked.");
        return self::SUCCESS;
    }
}
