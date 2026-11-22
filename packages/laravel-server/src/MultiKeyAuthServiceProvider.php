<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\LaravelServer;

use Illuminate\Cache\RateLimiter as IlluminateRateLimiter;
use Illuminate\Support\ServiceProvider;
use PDO;
use ProIT365\MultiKeyAuth\Core\Cryptography\BodyHasher;
use ProIT365\MultiKeyAuth\Core\Cryptography\CanonicalMessageEncoder;
use ProIT365\MultiKeyAuth\Core\Cryptography\HkdfSha256;
use ProIT365\MultiKeyAuth\Core\Cryptography\HmacSha256;
use ProIT365\MultiKeyAuth\Core\Random\RandomGeneratorInterface;
use ProIT365\MultiKeyAuth\Core\Random\RandomIntegerGeneratorInterface;
use ProIT365\MultiKeyAuth\Core\Random\SecureRandomGenerator;
use ProIT365\MultiKeyAuth\Core\Random\SecureRandomIntegerGenerator;
use ProIT365\MultiKeyAuth\Core\Server\AuthenticationVerifier;
use ProIT365\MultiKeyAuth\Core\Server\ChallengeIssuer;
use ProIT365\MultiKeyAuth\Core\Server\ChallengeRepositoryInterface;
use ProIT365\MultiKeyAuth\Core\Server\ClientPoolProviderInterface;
use ProIT365\MultiKeyAuth\Core\Server\ClockInterface;
use ProIT365\MultiKeyAuth\Core\Server\MasterKeyProviderInterface;
use ProIT365\MultiKeyAuth\Core\Server\PdoChallengeRepository;
use ProIT365\MultiKeyAuth\Core\Server\RateLimiterInterface;
use ProIT365\MultiKeyAuth\Core\Server\SystemClock;
use ProIT365\MultiKeyAuth\Core\Storage\KeyRepositoryInterface;
use ProIT365\MultiKeyAuth\Core\Storage\PdoKeyRepository;
use ProIT365\MultiKeyAuth\Core\Storage\SecretCipher;
use ProIT365\MultiKeyAuth\LaravelServer\Console\PruneChallengesCommand;
use ProIT365\MultiKeyAuth\LaravelServer\Console\GeneratePoolCommand;
use ProIT365\MultiKeyAuth\LaravelServer\Console\InspectPoolCommand;
use ProIT365\MultiKeyAuth\LaravelServer\Console\RevokeKeyCommand;
use ProIT365\MultiKeyAuth\LaravelServer\Console\RotateKeyCommand;
use ProIT365\MultiKeyAuth\LaravelServer\Console\ExportPoolCommand;
use ProIT365\MultiKeyAuth\LaravelServer\Database\PdoClientPoolProvider;
use ProIT365\MultiKeyAuth\LaravelServer\Http\ErrorResponder;
use ProIT365\MultiKeyAuth\LaravelServer\RateLimiting\LaravelRateLimiter;

final class MultiKeyAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/multikey-auth-server.php',
            'multikey-auth-server'
        );

        $this->app->singleton(PDO::class, function ($app): PDO {
            return $app['db']->connection(
                config('multikey-auth-server.database_connection')
            )->getPdo();
        });
        $this->app->singleton(ChallengeRepositoryInterface::class, PdoChallengeRepository::class);
        $this->app->singleton(KeyRepositoryInterface::class, PdoKeyRepository::class);
        $this->app->singleton(ClientPoolProviderInterface::class, function ($app): ClientPoolProviderInterface {
            $provider = config('multikey-auth-server.client_pool_provider');
            if ($provider !== null) {
                $resolved = is_string($provider) ? $app->make($provider) : $provider;
                if (!$resolved instanceof ClientPoolProviderInterface) {
                    throw new \LogicException('Configured client pool provider must implement ClientPoolProviderInterface.');
                }
                return $resolved;
            }
            return new PdoClientPoolProvider($app->make(PDO::class));
        });
        $this->app->singleton(MasterKeyProviderInterface::class, function ($app): MasterKeyProviderInterface {
            $provider = config('multikey-auth-server.master_key_provider');
            if (!is_string($provider) || $provider === '') {
                throw new \LogicException('Configure an external MKAP master key provider before enabling authentication.');
            }
            $resolved = $app->make($provider);
            if (!$resolved instanceof MasterKeyProviderInterface) {
                throw new \LogicException('Configured master key provider must implement MasterKeyProviderInterface.');
            }
            return $resolved;
        });
        $this->app->singleton(ClockInterface::class, SystemClock::class);
        $this->app->singleton(RandomGeneratorInterface::class, SecureRandomGenerator::class);
        $this->app->singleton(RandomIntegerGeneratorInterface::class, SecureRandomIntegerGenerator::class);
        $this->app->singleton(SecretCipher::class, function ($app): SecretCipher {
            return new SecretCipher($app->make(RandomGeneratorInterface::class));
        });
        $this->app->singleton(\ProIT365\MultiKeyAuth\Core\Export\ExportManager::class, function ($app): \ProIT365\MultiKeyAuth\Core\Export\ExportManager {
            return new \ProIT365\MultiKeyAuth\Core\Export\ExportManager($app->make(SecretCipher::class));
        });
        $this->app->singleton(RateLimiterInterface::class, function ($app): RateLimiterInterface {
            return new LaravelRateLimiter(
                $app->make(IlluminateRateLimiter::class),
                (int) config('multikey-auth-server.rate_limits.challenge', 60),
                (int) config('multikey-auth-server.rate_limits.authenticate', 120),
                (int) config('multikey-auth-server.rate_limits.decay_seconds', 60)
            );
        });
        $this->app->singleton(ChallengeIssuer::class, function ($app): ChallengeIssuer {
            return new ChallengeIssuer(
                $app->make(ClientPoolProviderInterface::class),
                $app->make(KeyRepositoryInterface::class),
                $app->make(ChallengeRepositoryInterface::class),
                $app->make(RateLimiterInterface::class),
                $app->make(RandomGeneratorInterface::class),
                $app->make(RandomIntegerGeneratorInterface::class),
                $app->make(ClockInterface::class),
                (int) config('multikey-auth-server.challenge_lifetime_seconds', 30)
            );
        });
        $this->app->singleton(AuthenticationVerifier::class, function ($app): AuthenticationVerifier {
            return new AuthenticationVerifier(
                $app->make(ChallengeRepositoryInterface::class),
                $app->make(KeyRepositoryInterface::class),
                $app->make(SecretCipher::class),
                $app->make(MasterKeyProviderInterface::class),
                $app->make(RateLimiterInterface::class),
                $app->make(ClockInterface::class),
                $app->make(BodyHasher::class),
                $app->make(CanonicalMessageEncoder::class),
                $app->make(HkdfSha256::class),
                $app->make(HmacSha256::class),
                (int) config('multikey-auth-server.maximum_authenticated_body_bytes', 1_048_576)
            );
        });
        $this->app->singleton(ErrorResponder::class);
        $this->app->when(\ProIT365\MultiKeyAuth\LaravelServer\Http\ChallengeController::class)
            ->needs('$maximumBodyBytes')
            ->give(static fn (): int => (int) config('multikey-auth-server.maximum_challenge_body_bytes', 65_536));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/multikey-auth-server.php' => config_path('multikey-auth-server.php'),
        ], 'mkap-config');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->app['router']->aliasMiddleware(
            'multikey.auth',
            \ProIT365\MultiKeyAuth\LaravelServer\Http\AuthenticateMkapRequest::class
        );
        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneChallengesCommand::class,
                GeneratePoolCommand::class,
                InspectPoolCommand::class,
                RevokeKeyCommand::class,
                RotateKeyCommand::class,
                ExportPoolCommand::class,
            ]);
        }
        if (config('multikey-auth-server.routes.enabled', false)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }
    }
}
