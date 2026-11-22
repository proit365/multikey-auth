<?php

declare(strict_types=1);

use ProIT365\MultiKeyAuth\Client\HttpRequest;
use ProIT365\MultiKeyAuth\Client\HttpResponse;
use ProIT365\MultiKeyAuth\Client\HttpTransportInterface;
use ProIT365\MultiKeyAuth\Client\PhpMultiKeyClient;
use ProIT365\MultiKeyAuth\Client\ClientConfig;
use ProIT365\MultiKeyAuth\Client\ExportLoader;

$root = dirname(__DIR__, 3);
spl_autoload_register(static function (string $class) use ($root): void {
    $prefixes = [
        'ProIT365\\MultiKeyAuth\\Core\\' => $root . '/packages/core/src/',
        'ProIT365\\MultiKeyAuth\\Client\\' => $root . '/packages/client-php/src/',
    ];
    foreach ($prefixes as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $file = $directory . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});

$vector = json_decode(
    file_get_contents($root . '/specification/test-vectors-v1.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$manifest = [
    'format_version' => 1,
    'protocol_version' => 1,
    'client_id' => 'CLIENT_001',
    'pool_id' => 'POOL_001',
    'pool_version' => 1,
    'export_version' => 1,
    'created_at' => '2026-11-22T00:00:00Z',
    'identifier_length' => 6,
    'identifier_alphabet' => 'abcdefghijklmnopqrstuvwxyz0123456789',
    'secret_length_bytes' => 32,
    'key_count' => 3,
    'keys' => [],
];
$secretHex = str_split($vector['hkdf']['ikm_hex'], 64);
foreach ($vector['proof']['key_ids'] as $index => $id) {
    $manifest['keys'][] = [
        'id' => $id,
        'secret' => rtrim(strtr(base64_encode(hex2bin($secretHex[$index])), '+/', '-_'), '='),
    ];
}
$pool = (new ExportLoader())->fromJson(json_encode($manifest, JSON_THROW_ON_ERROR));

$transport = new class implements HttpTransportInterface {
    public array $requests = [];

    public function send(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;
        if (count($this->requests) === 1) {
            return new HttpResponse(200, [], json_encode([
                'version' => 1,
                'request_id' => 'AAECAwQFBgcICQoLDA0ODw',
                'nonce' => 'ICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA',
                'key_ids' => 'a8f31xz71q5bk92p4m',
                'key_count' => 3,
                'id_length' => 6,
                'expires_in' => 30,
                'expires_at' => 1893456000,
            ], JSON_THROW_ON_ERROR));
        }
        return new HttpResponse(200, ['Content-Type' => 'application/json'], '{"ok":true}');
    }
};

$client = new PhpMultiKeyClient(
    new ClientConfig(
        'https://api.example.test',
        'CLIENT_001',
        1,
        6,
        'abcdefghijklmnopqrstuvwxyz0123456789'
    ),
    $pool,
    $transport
);
$response = $client->request(
    $vector['proof']['http_method'],
    $vector['proof']['request_target'],
    ''
);
if ($response->statusCode !== 200
    || $transport->requests[1]->body !== ''
    || $transport->requests[1]->headers['X-MKAP-Proof'] !== $vector['proof']['hmac_base64url']) {
    throw new RuntimeException('Laravel client wrapper prerequisites failed interoperability verification.');
}

echo "Phase 09 Laravel client smoke tests passed.\n";
