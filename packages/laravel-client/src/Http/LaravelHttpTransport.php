<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\LaravelClient\Http;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use ProIT365\MultiKeyAuth\Client\HttpRequest;
use ProIT365\MultiKeyAuth\Client\HttpResponse;
use ProIT365\MultiKeyAuth\Client\HttpTransportInterface;

final class LaravelHttpTransport implements HttpTransportInterface
{
    public function __construct(private readonly Factory $http)
    {
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $response = $this->http
            ->withHeaders($request->headers)
            ->withBody($request->body, $this->contentType($request))
            ->send($request->method, $request->uri);

        if (!$response instanceof Response) {
            throw new \RuntimeException('Laravel HTTP transport returned an unexpected response.');
        }

        return new HttpResponse(
            $response->status(),
            $response->headers(),
            $response->body()
        );
    }

    private function contentType(HttpRequest $request): string
    {
        foreach ($request->headers as $name => $value) {
            if (strcasecmp((string) $name, 'Content-Type') === 0) {
                return (string) $value;
            }
        }

        return 'application/octet-stream';
    }
}
