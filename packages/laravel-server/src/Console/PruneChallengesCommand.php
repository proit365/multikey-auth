<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\LaravelServer\Console;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;

final class PruneChallengesCommand extends Command
{
    protected $signature = 'mkap:challenges:prune {--dry-run : Report the number of eligible rows without deleting them}';

    protected $description = 'Remove expired challenges that have no retained audit records';

    public function handle(DatabaseManager $database): int
    {
        $connection = $database->connection(config('multikey-auth-server.database_connection'));
        $cutoff = gmdate('Y-m-d H:i:s');
        $query = $connection->table('mkap_authentication_challenges')
            ->where('expires_at', '<=', $cutoff)
            ->whereNotExists(static function ($subquery): void {
                $subquery->selectRaw('1')
                    ->from('mkap_authentication_logs')
                    ->whereColumn(
                        'mkap_authentication_logs.challenge_id',
                        'mkap_authentication_challenges.id'
                    );
            });
        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info($count . ' expired challenge(s) are eligible for pruning.');
            return self::SUCCESS;
        }

        $deleted = $query->delete();
        $this->info($deleted . ' expired challenge(s) pruned; audit-linked challenges were retained.');

        return self::SUCCESS;
    }
}
