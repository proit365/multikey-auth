<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\LaravelServer\Tests\Feature;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use ProIT365\MultiKeyAuth\Core\Export\ExportManager;
use ProIT365\MultiKeyAuth\Core\Random\SecureRandomGenerator;
use ProIT365\MultiKeyAuth\Core\Server\MasterKeyProviderInterface;
use ProIT365\MultiKeyAuth\Core\Storage\KeyAssociatedData;
use ProIT365\MultiKeyAuth\Core\Storage\MasterKey;
use ProIT365\MultiKeyAuth\Core\Storage\SecretCipher;
use ProIT365\MultiKeyAuth\Core\Server\ClientPoolProviderInterface;
use ProIT365\MultiKeyAuth\LaravelServer\Http\AuthenticateMkapRequest;
use Orchestra\Testbench\TestCase;
use ProIT365\MultiKeyAuth\LaravelServer\MultiKeyAuthServiceProvider;

final class LaravelServerPackageTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [MultiKeyAuthServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('cache.default', 'array');
    }

    public function test_migration_creates_tables_and_active_pool_provider_loads_metadata(): void
    {
        $this->artisan('migrate')->assertExitCode(0);

        $this->assertTrue($this->app->make(DatabaseManager::class)
            ->connection()->getSchemaBuilder()->hasTable('mkap_authentication_challenges'));
        $this->assertTrue($this->app->make(DatabaseManager::class)
            ->connection()->getSchemaBuilder()->hasColumn('mkap_key_pools', 'identifier_alphabet'));

        DB::table('mkap_clients')->insert([
            'client_identifier' => 'CLIENT_001',
            'name' => 'Test client',
            'status' => 'active',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        DB::table('mkap_key_pools')->insert([
            'client_id' => 1,
            'pool_identifier' => 'POOL_001',
            'pool_version' => 1,
            'identifier_length' => 6,
            'identifier_alphabet' => 'abcdefghijklmnopqrstuvwxyz0123456789',
            'secret_length_bytes' => 32,
            'key_count' => 10,
            'selection_count' => 3,
            'status' => 'active',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        $pool = $this->app->make(ClientPoolProviderInterface::class)
            ->findActivePool('CLIENT_001');
        $this->assertNotNull($pool);
        $this->assertSame('POOL_001', $pool->metadata->poolId);
        $this->assertSame(3, $pool->metadata->selectionCount);
    }

    public function test_authentication_middleware_is_aliased_but_routes_are_opt_in(): void
    {
        $this->assertSame(
            AuthenticateMkapRequest::class,
            $this->app['router']->getMiddleware()['multikey.auth']
        );
        foreach (Route::getRoutes() as $route) {
            $this->assertNotSame('v1/auth/challenges', $route->uri());
        }
    }

    public function test_export_uses_the_pool_record_id_when_client_and_pool_ids_differ(): void
    {
        $this->artisan('migrate')->assertExitCode(0);

        DB::table('mkap_clients')->insert([
            'client_identifier' => 'CLIENT_EXPORT',
            'name' => 'Export test client',
            'status' => 'active',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $clientRecordId = (int) DB::table('mkap_clients')
            ->where('client_identifier', 'CLIENT_EXPORT')
            ->value('id');

        $poolAttributes = [
            'client_id' => $clientRecordId,
            'pool_identifier' => 'POOL_EXPORT',
            'identifier_length' => 6,
            'identifier_alphabet' => 'abcdefghijklmnopqrstuvwxyz0123456789',
            'secret_length_bytes' => 32,
            'selection_count' => 3,
            'status' => 'active',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ];
        DB::table('mkap_key_pools')->insert(array_merge($poolAttributes, [
            'pool_version' => 1,
            'key_count' => 2,
        ]));
        $latestPoolId = (int) DB::table('mkap_key_pools')->insertGetId(array_merge($poolAttributes, [
            'pool_version' => 3,
            'key_count' => 5,
        ]));
        $this->assertNotSame($clientRecordId, $latestPoolId);

        $masterKey = new MasterKey(str_repeat('k', 32));
        $cipher = new SecretCipher(new SecureRandomGenerator());
        $this->app->instance(MasterKeyProviderInterface::class, new class($masterKey) implements MasterKeyProviderInterface {
            public function __construct(private readonly MasterKey $masterKey)
            {
            }

            public function forPool(string $poolIdentifier): MasterKey
            {
                return $this->masterKey;
            }
        });
        $this->app->instance(SecretCipher::class, $cipher);
        $this->app->instance(ExportManager::class, new ExportManager($cipher));

        foreach ([1 => 2, 3 => 5] as $poolVersion => $keyCount) {
            $poolId = (int) DB::table('mkap_key_pools')
                ->where('pool_identifier', 'POOL_EXPORT')
                ->where('pool_version', $poolVersion)
                ->value('id');
            for ($index = 1; $index <= $keyCount; $index++) {
                $identifier = sprintf('KEY_%d_%02d', $poolVersion, $index);
                $encrypted = $cipher->encrypt(
                    str_repeat(chr($index), 32),
                    $masterKey,
                    KeyAssociatedData::forKey('POOL_EXPORT', $identifier)
                );
                DB::table('mkap_authentication_keys')->insert([
                    'pool_id' => $poolId,
                    'public_identifier' => $identifier,
                    'encrypted_secret' => $encrypted->toBinary(),
                    'encryption_key_version' => $masterKey->version,
                    'status' => 'active',
                    'generation_batch_id' => null,
                    'created_at' => '2026-01-01 00:00:00',
                    'revoked_at' => null,
                ]);
            }
        }

        $outputPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'mkap-export-' . bin2hex(random_bytes(8)) . '.json';
        try {
            $this->artisan('mkap:exports:create', [
                'pool_id' => 'POOL_EXPORT',
                '--output' => $outputPath,
                '--development-json' => true,
                '--force' => true,
            ])->assertExitCode(0);

            $manifest = json_decode((string) file_get_contents($outputPath), true, 512, JSON_THROW_ON_ERROR);
            $activeKeyCount = DB::table('mkap_authentication_keys')
                ->where('pool_id', $latestPoolId)
                ->where('status', 'active')
                ->count();
            $this->assertSame($activeKeyCount, count($manifest['keys']));
            $this->assertSame($activeKeyCount, $manifest['key_count']);

            $export = DB::table('mkap_key_exports')->first();
            $this->assertSame($latestPoolId, (int) $export->pool_id);
            $this->assertSame($activeKeyCount, (int) $export->key_count);
        } finally {
            if (is_file($outputPath)) {
                unlink($outputPath);
            }
        }
    }
}
