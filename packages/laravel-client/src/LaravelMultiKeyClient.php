<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\LaravelClient;

use Illuminate\Http\Client\Factory as HttpFactory;
use ProIT365\MultiKeyAuth\Client\ClientConfig;
use ProIT365\MultiKeyAuth\Client\ExportLoader;
use ProIT365\MultiKeyAuth\Client\HttpResponse;
use ProIT365\MultiKeyAuth\Client\PhpMultiKeyClient;
use ProIT365\MultiKeyAuth\LaravelClient\Http\LaravelHttpTransport;

final class LaravelMultiKeyClient
{
    private readonly PhpMultiKeyClient $client;

    public function __construct(
        array $config,
        HttpFactory $http
    ) {
        $required = ['base_uri', 'client_id', 'pool_version', 'identifier_length', 'identifier_alphabet', 'export_path'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $config) || $config[$field] === null || $config[$field] === '') {
                throw new \InvalidArgumentException('Missing Laravel MKAP client configuration: ' . $field);
            }
        }
        $exportPath = (string) $config['export_path'];
        if (!is_file($exportPath) || !is_readable($exportPath)) {
            throw new \InvalidArgumentException('Configured MKAP export is not readable.');
        }
        $exportJson = file_get_contents($exportPath);
        if ($exportJson === false) {
            throw new \InvalidArgumentException('Unable to read configured MKAP export.');
        }
        $pool = (new ExportLoader())->fromJson($exportJson);
        $clientConfig = new ClientConfig(
            (string) $config['base_uri'],
            (string) $config['client_id'],
            (int) $config['pool_version'],
            (int) $config['identifier_length'],
            (string) $config['identifier_alphabet'],
            (int) ($config['maximum_challenge_response_bytes'] ?? 65_536),
            (bool) ($config['require_https'] ?? true)
        );
        $this->client = new PhpMultiKeyClient(
            $clientConfig,
            $pool,
            new LaravelHttpTransport($http)
        );
    }

    public function request(string $method, string $target, string $body = ''): HttpResponse
    {
        return $this->client->request($method, $target, $body);
    }

    public function get(string $target): HttpResponse
    {
        return $this->request('GET', $target);
    }

    public function post(string $target, string $body): HttpResponse
    {
        return $this->request('POST', $target, $body);
    }

    public function put(string $target, string $body): HttpResponse
    {
        return $this->request('PUT', $target, $body);
    }

    public function patch(string $target, string $body): HttpResponse
    {
        return $this->request('PATCH', $target, $body);
    }

    public function delete(string $target, string $body = ''): HttpResponse
    {
        return $this->request('DELETE', $target, $body);
    }
}
