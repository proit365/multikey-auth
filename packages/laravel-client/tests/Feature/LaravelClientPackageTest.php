<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\LaravelClient\Tests\Feature;

use Illuminate\Http\Client\Factory;
use Orchestra\Testbench\TestCase;
use ProIT365\MultiKeyAuth\LaravelClient\Facades\MKAP;
use ProIT365\MultiKeyAuth\LaravelClient\LaravelMultiKeyClient;
use ProIT365\MultiKeyAuth\LaravelClient\MultiKeyAuthClientServiceProvider;

final class LaravelClientPackageTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [MultiKeyAuthClientServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
    }

    public function test_service_provider_is_registerable_without_constructing_missing_export(): void
    {
        $this->assertTrue($this->app->bound(LaravelMultiKeyClient::class));
        $this->assertTrue($this->app->bound('mkap-client'));
    }

    public function test_facade_resolver_uses_named_binding(): void
    {
        $this->assertTrue(class_exists(MKAP::class));
    }

    public function test_package_manifest_targets_laravel_12_components(): void
    {
        $manifest = json_decode(
            file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $this->assertSame('^12.0', $manifest['require']['illuminate/support']);
        $this->assertSame('^12.0', $manifest['require']['illuminate/http']);
    }

    public function test_laravel_http_factory_is_available(): void
    {
        $this->assertInstanceOf(Factory::class, $this->app->make(Factory::class));
    }
}
