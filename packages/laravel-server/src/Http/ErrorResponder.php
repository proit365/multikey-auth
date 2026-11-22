<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\LaravelServer\Http;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use ProIT365\MultiKeyAuth\Core\Server\ErrorStatusMapper;
use Throwable;

final class ErrorResponder
{
    public function __construct(
        private readonly ResponseFactory $responses,
        private readonly ErrorStatusMapper $statusMapper
    ) {
    }

    public function respond(Throwable $error): JsonResponse
    {
        $status = $this->statusMapper->statusCode($error);
        if ($status >= 500) {
            report($error);
        }

        $code = match ($status) {
            400 => 'bad_request',
            401 => 'unauthorized',
            403 => 'forbidden',
            404 => 'not_found',
            409 => 'conflict',
            413 => 'payload_too_large',
            429 => 'too_many_requests',
            default => 'internal_server_error',
        };

        return $this->responses->json(['error' => $code], $status)
            ->header('Cache-Control', 'no-store');
    }
}
