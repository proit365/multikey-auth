<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\LaravelServer\Console;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;

final class InspectPoolCommand extends Command
{
    protected $signature = 'mkap:pools:inspect {pool_id : Public pool identifier}';
    protected $description = 'Inspect pool metadata and key counts without printing secrets';

    public function handle(DatabaseManager $database): int
    {
        $connection = $database->connection(config('multikey-auth-server.database_connection'));
        $pool = $connection->table('mkap_key_pools')
            ->where('pool_identifier', (string) $this->argument('pool_id'))
            ->orderByDesc('pool_version')
            ->first();
        if ($pool === null) {
            $this->error('Unknown pool identifier.');
            return self::FAILURE;
        }

        $active = $connection->table('mkap_authentication_keys')
            ->where('pool_id', $pool->id)
            ->where('status', 'active')
            ->count();
        $revoked = $connection->table('mkap_authentication_keys')
            ->where('pool_id', $pool->id)
            ->where('status', 'revoked')
            ->count();

        $this->table(
            ['Field', 'Value'],
            [
                ['Pool identifier', $pool->pool_identifier],
                ['Pool version', $pool->pool_version],
                ['Status', $pool->status],
                ['Identifier length', $pool->identifier_length],
                ['Selection count', $pool->selection_count],
                ['Configured key count', $pool->key_count],
                ['Active keys', $active],
                ['Revoked keys', $revoked],
            ]
        );

        return self::SUCCESS;
    }
}
