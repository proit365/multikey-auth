<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\LaravelServer\Tests\Feature;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
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
}
