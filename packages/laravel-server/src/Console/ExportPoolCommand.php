<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\LaravelServer\Console;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use ProIT365\MultiKeyAuth\Core\Encoding\Base64Url;
use ProIT365\MultiKeyAuth\Core\Export\ExportManager;
use ProIT365\MultiKeyAuth\Core\Export\KeyExport;
use ProIT365\MultiKeyAuth\Core\Server\MasterKeyProviderInterface;
use ProIT365\MultiKeyAuth\Core\Storage\EncryptedSecret;
use ProIT365\MultiKeyAuth\Core\Storage\KeyAssociatedData;
use ProIT365\MultiKeyAuth\Core\Storage\SecretCipher;

final class ExportPoolCommand extends Command
{
    protected $signature = 'mkap:exports:create
        {pool_id : Public pool identifier}
        {--client= : Public client identifier}
        {--output= : Output file path}
        {--development-json : Create plaintext JSON; requires explicit confirmation}
        {--force : Skip confirmation}';

    protected $description = 'Create a protected or development client export';

    public function handle(
        DatabaseManager $database,
        MasterKeyProviderInterface $masterKeys,
        SecretCipher $cipher,
        ExportManager $exports
    ): int {
        $poolId = (string) $this->argument('pool_id');
        $connection = $database->connection(config('multikey-auth-server.database_connection'));
        $pool = $connection->table('mkap_key_pools')
            ->join('mkap_clients', 'mkap_clients.id', '=', 'mkap_key_pools.client_id')
            ->where('mkap_key_pools.pool_identifier', $poolId)
            ->orderByDesc('mkap_key_pools.pool_version')
            ->first();
        if ($pool === null) {
            $this->error('Unknown pool identifier.');
            return self::FAILURE;
        }

        $clientId = (string) ($this->option('client') ?: $pool->client_identifier);
        if ($clientId !== $pool->client_identifier) {
            $this->error('Client identifier does not own the requested pool.');
            return self::FAILURE;
        }
        $output = (string) ($this->option('output') ?: '');
        $development = (bool) $this->option('development-json');
        if (!$this->option('force')
            && !$this->confirm($development
                ? 'Create an insecure plaintext export containing all active secrets?'
                : 'Create a protected export containing all active secrets?')) {
            $this->warn('Export cancelled.');
            return self::SUCCESS;
        }

        $rows = $connection->table('mkap_authentication_keys')
            ->where('pool_id', $pool->id)
            ->where('status', 'active')
            ->orderBy('public_identifier')
            ->get();
        if ($rows->isEmpty()) {
            $this->error('Pool has no active keys.');
            return self::FAILURE;
        }

        $masterKey = $masterKeys->forPool($poolId);
        $keys = [];
        foreach ($rows as $row) {
            $encrypted = EncryptedSecret::fromBinary((string) $row->encrypted_secret);
            $secret = $cipher->decrypt(
                $encrypted,
                $masterKey,
                KeyAssociatedData::forKey($poolId, (string) $row->public_identifier)
            );
            $keys[] = [
                'id' => (string) $row->public_identifier,
                'secret' => Base64Url::encode($secret),
            ];
        }

        $exportVersion = (int) $connection->table('mkap_key_exports')
            ->where('pool_id', $pool->id)
            ->max('export_version');
        $exportVersion++;
        $export = new KeyExport(
            1,
            1,
            $clientId,
            $poolId,
            (int) $pool->pool_version,
            $exportVersion,
            gmdate('Y-m-d\TH:i:s\Z'),
            (int) $pool->identifier_length,
            (string) $pool->identifier_alphabet,
            32,
            $keys
        );

        $payload = $development
            ? $exports->createPlaintextJson($export)
            : null;
        if ($payload === null) {
            $this->error(
                'Protected exports are not enabled until PHP, JavaScript, and Flutter client loaders support authenticated envelope opening.'
            );
            return self::FAILURE;
        }
        if ($output === '') {
            $this->error('An --output file path is required; secret exports are never printed to the terminal.');
            return self::FAILURE;
        }
        $directory = dirname($output);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            $this->error('Unable to create export directory.');
            return self::FAILURE;
        }
        $previousUmask = umask(0077);
        $temporary = $directory . DIRECTORY_SEPARATOR
            . '.' . basename($output) . '.'
            . bin2hex(random_bytes(8))
            . '.tmp';
        $handle = @fopen($temporary, 'xb');
        if ($handle === false) {
            umask($previousUmask);
            $this->error('Unable to create a secure export temp file.');
            return self::FAILURE;
        }
        $bytes = strlen($payload);
        $written = 0;
        while ($written < $bytes) {
            $chunk = fwrite($handle, substr($payload, $written));
            if ($chunk === false || $chunk === 0) {
                fclose($handle);
                @unlink($temporary);
                umask($previousUmask);
                $this->error('Unable to write export file.');
                return self::FAILURE;
            }
            $written += $chunk;
        }
        fflush($handle);
        fclose($handle);
        @chmod($temporary, 0600);
        if (!@rename($temporary, $output)) {
            @unlink($temporary);
            umask($previousUmask);
            $this->error('Unable to write export file.');
            return self::FAILURE;
        }
        umask($previousUmask);
        @chmod($output, 0600);

        $connection->table('mkap_key_exports')->insert([
            'pool_id' => $pool->id,
            'export_version' => $exportVersion,
            'format_version' => 1,
            'key_count' => count($keys),
            'checksum' => $exports->checksum($export),
            'status' => 'active',
            'created_at' => gmdate('Y-m-d H:i:s'),
            'revoked_at' => null,
        ]);

        $this->info('Export ' . $exportVersion . ' written with mode 0600: ' . $output);
        $this->info('Secret values were not printed.');
        return self::SUCCESS;
    }
}
