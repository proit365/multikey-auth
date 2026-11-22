<?php

declare(strict_types=1);

use ProIT365\MultiKeyAuth\Client\ChallengeRequestFailedException;
use ProIT365\MultiKeyAuth\Client\ClientConfig;
use ProIT365\MultiKeyAuth\Client\ExportLoader;
use ProIT365\MultiKeyAuth\Client\HttpRequest;
use ProIT365\MultiKeyAuth\Client\HttpResponse;
use ProIT365\MultiKeyAuth\Client\HttpTransportInterface;
use ProIT365\MultiKeyAuth\Client\InvalidChallengeResponseException;
use ProIT365\MultiKeyAuth\Client\PhpMultiKeyClient;
use ProIT365\MultiKeyAuth\Core\Encoding\Base64Url;
use ProIT365\MultiKeyAuth\Core\Key\IdentifierGenerator;

$root = dirname(__DIR__, 3);
spl_autoload_register(static function (string $class) use ($root): void {
    $prefixes = [
        'ProIT365\\MultiKeyAuth\\Core\\' => $root . '/packages/core/src/',
        'ProIT365\\MultiKeyAuth\\Client\\' => dirname(__DIR__) . '/src/',
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

final class Phase07MockTransport implements HttpTransportInterface
{
    public array $requests = [];

    public function __construct(
        private readonly HttpResponse $challengeResponse,
        private readonly HttpResponse $apiResponse
    ) {
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;
        return count($this->requests) === 1 ? $this->challengeResponse : $this->apiResponse;
    }
}

$vector = json_decode(
    file_get_contents($root . '/specification/test-vectors-v1.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$secretParts = str_split($vector['hkdf']['ikm_hex'], 64);
$manifest = [
    'format_version' => 1,
    'protocol_version' => 1,
    'client_id' => $vector['challenge_fixture']['client_id'],
    'pool_id' => 'POOL_TEST',
    'pool_version' => 1,
    'export_version' => 1,
    'created_at' => '2026-01-01T00:00:00Z',
    'identifier_length' => 6,
    'identifier_alphabet' => IdentifierGenerator::DEFAULT_ALPHABET,
    'secret_length_bytes' => 32,
    'key_count' => 3,
    'keys' => [],
];
foreach ($vector['proof']['key_ids'] as $index => $identifier) {
    $manifest['keys'][] = [
        'id' => $identifier,
        'secret' => Base64Url::encode(hex2bin($secretParts[$index])),
    ];
}
$keyPool = (new ExportLoader())->fromJson(json_encode($manifest, JSON_THROW_ON_ERROR));

$challenge = [
    'version' => 1,
    'request_id' => $vector['challenge_fixture']['request_id_base64url'],
    'nonce' => $vector['challenge_fixture']['nonce_base64url'],
    'key_ids' => $vector['challenge_fixture']['key_ids'],
    'key_count' => $vector['challenge_fixture']['key_count'],
    'id_length' => $vector['challenge_fixture']['id_length'],
    'expires_in' => 30,
    'expires_at' => $vector['proof']['expires_at'],
];
$mock = new Phase07MockTransport(
    new HttpResponse(200, [], json_encode($challenge, JSON_THROW_ON_ERROR)),
    new HttpResponse(202, ['Content-Type' => 'application/json'], '{"accepted":true}')
);
$client = new PhpMultiKeyClient(
    new ClientConfig(
        'https://api.example.test',
        $vector['challenge_fixture']['client_id'],
        1,
        6,
        IdentifierGenerator::DEFAULT_ALPHABET
    ),
    $keyPool,
    $mock
);
$response = $client->request(
    $vector['proof']['http_method'],
    $vector['proof']['request_target']
);
$apiRequest = $mock->requests[1];
if ($apiRequest->headers['X-MKAP-Proof'] !== $vector['proof']['hmac_base64url']) {
    throw new RuntimeException('Client proof did not match the published MKAP v1 known-answer vector.');
}
if ($apiRequest->headers['X-MKAP-Request'] !== $vector['challenge_fixture']['request_id_base64url']) {
    throw new RuntimeException('Client request identifier header did not match the fixture.');
}
if ($response->statusCode !== 202 || $response->body !== '{"accepted":true}') {
    throw new RuntimeException('Client did not return the normal API response.');
}
if ($mock->requests[0]->method !== 'POST'
    || $mock->requests[0]->uri !== 'https://api.example.test/v1/auth/challenges') {
    throw new RuntimeException('Client did not use the configured challenge endpoint.');
}

$exactBody = "\x00payload\r\n";
$challengeRequest = json_decode($mock->requests[0]->body, true, 512, JSON_THROW_ON_ERROR);
if ($challengeRequest['body_sha256'] !== Base64Url::encode(hash('sha256', '', true))) {
    throw new RuntimeException('Challenge request did not contain the expected body hash.');
}
if ($apiRequest->body !== '') {
    throw new RuntimeException('The empty vector request body changed.');
}

$bodyAwareMock = new Phase07MockTransport(
    new HttpResponse(200, [], json_encode($challenge, JSON_THROW_ON_ERROR)),
    new HttpResponse(200, [], 'ok')
);
$bodyAwareClient = new PhpMultiKeyClient(
    new ClientConfig(
        'https://api.example.test',
        $vector['challenge_fixture']['client_id'],
        1,
        6,
        IdentifierGenerator::DEFAULT_ALPHABET
    ),
    $keyPool,
    $bodyAwareMock
);
$bodyAwareClient->request('POST', '/api/body', $exactBody);
$bodyRequest = json_decode($bodyAwareMock->requests[0]->body, true, 512, JSON_THROW_ON_ERROR);
if ($bodyRequest['body_sha256'] !== Base64Url::encode(hash('sha256', $exactBody, true))
    || $bodyAwareMock->requests[1]->body !== $exactBody) {
    throw new RuntimeException('Client did not preserve the exact request body bytes.');
}

$failedChallengeMock = new Phase07MockTransport(
    new HttpResponse(429, [], ''),
    new HttpResponse(200, [], '')
);
try {
    (new PhpMultiKeyClient(
        new ClientConfig(
            'https://api.example.test',
            $vector['challenge_fixture']['client_id'],
            1,
            6,
            IdentifierGenerator::DEFAULT_ALPHABET
        ),
        $keyPool,
        $failedChallengeMock
    ))->request('GET', '/api/orders');
    throw new RuntimeException('Challenge HTTP failure was not surfaced.');
} catch (ChallengeRequestFailedException $error) {
    if ($error->statusCode !== 429) {
        throw $error;
    }
}

$unauthorizedApiMock = new Phase07MockTransport(
    new HttpResponse(200, [], json_encode($challenge, JSON_THROW_ON_ERROR)),
    new HttpResponse(401, ['Content-Type' => 'application/json'], '{"error":"expired"}')
);
$unauthorizedResponse = (new PhpMultiKeyClient(
    new ClientConfig(
        'https://api.example.test',
        $vector['challenge_fixture']['client_id'],
        1,
        6,
        IdentifierGenerator::DEFAULT_ALPHABET
    ),
    $keyPool,
    $unauthorizedApiMock
))->request('GET', '/api/orders');
if ($unauthorizedResponse->statusCode !== 401
    || $unauthorizedResponse->body !== '{"error":"expired"}'
    || count($unauthorizedApiMock->requests) !== 2) {
    throw new RuntimeException('Client did not return an authentication failure without replaying the request.');
}

$invalidChallengeMock = new Phase07MockTransport(
    new HttpResponse(200, [], json_encode(array_replace($challenge, ['key_ids' => 'a8f31xa8f31xz71q5b']), JSON_THROW_ON_ERROR)),
    new HttpResponse(200, [], '')
);
try {
    (new PhpMultiKeyClient(
        new ClientConfig(
            'https://api.example.test',
            $vector['challenge_fixture']['client_id'],
            1,
            6,
            IdentifierGenerator::DEFAULT_ALPHABET
        ),
        $keyPool,
        $invalidChallengeMock
    ))->request('GET', '/api/orders');
    throw new RuntimeException('Duplicate challenge identifiers were not rejected.');
} catch (InvalidChallengeResponseException) {
}

echo "Phase 07 client SDK smoke tests passed.\n";
