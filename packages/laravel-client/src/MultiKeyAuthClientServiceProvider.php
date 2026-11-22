<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\LaravelClient;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Client\Factory as HttpFactory;

final class MultiKeyAuthClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/multikey-auth-client.php',
            'multikey-auth-client'
        );

        $this->app->singleton(LaravelMultiKeyClient::class, function ($app): LaravelMultiKeyClient {
            return new LaravelMultiKeyClient(
                (array) config('multikey-auth-client'),
                $app->make(HttpFactory::class)
            );
        });
        $this->app->alias(LaravelMultiKeyClient::class, 'mkap-client');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/multikey-auth-client.php' => config_path('multikey-auth-client.php'),
        ], 'mkap-client-config');
    }
}
