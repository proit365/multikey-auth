<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\LaravelClient\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \ProIT365\MultiKeyAuth\Client\HttpResponse request(string $method, string $target, string $body = '')
 * @method static \ProIT365\MultiKeyAuth\Client\HttpResponse get(string $target)
 * @method static \ProIT365\MultiKeyAuth\Client\HttpResponse post(string $target, string $body)
 * @method static \ProIT365\MultiKeyAuth\Client\HttpResponse put(string $target, string $body)
 * @method static \ProIT365\MultiKeyAuth\Client\HttpResponse patch(string $target, string $body)
 * @method static \ProIT365\MultiKeyAuth\Client\HttpResponse delete(string $target, string $body = '')
 */
final class MKAP extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'mkap-client';
    }
}
