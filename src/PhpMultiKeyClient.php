<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Client;

use ProIT365\MultiKeyAuth\Core\Cryptography\BodyHasher;
use ProIT365\MultiKeyAuth\Core\Cryptography\CanonicalMessageEncoder;
use ProIT365\MultiKeyAuth\Core\Cryptography\HkdfSha256;
use ProIT365\MultiKeyAuth\Core\Cryptography\HmacSha256;
use ProIT365\MultiKeyAuth\Core\Encoding\Base64Url;
use ProIT365\MultiKeyAuth\Core\Protocol\RequestMetadataValidator;

final class PhpMultiKeyClient
{
    public function __construct(
        private readonly ClientConfig $config,
        private readonly ClientKeyPool $keyPool,
        private readonly HttpTransportInterface $transport
    ) {
        if ($keyPool->clientIdentifier !== $config->clientIdentifier) {
            throw new \InvalidArgumentException('Client configuration does not match the key export.');
        }
        if ($keyPool->poolVersion !== $config->poolVersion
            || $keyPool->identifierLength !== $config->identifierLength
            || $keyPool->identifierAlphabet !== $config->identifierAlphabet) {
            throw new \InvalidArgumentException('Client configuration does not match the key pool metadata.');
        }
    }

    public function request(string $method, string $target, string $body = ''): HttpResponse
    {
        RequestMetadataValidator::method($method);
        RequestMetadataValidator::target($target);
        $bodyHash = (new BodyHasher())->sha256($body);

        $challengeRequestJson = json_encode([
            'version' => 1,
            'client_id' => $this->config->clientIdentifier,
            'method' => $method,
            'target' => $target,
            'body_sha256' => Base64Url::encode($bodyHash),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $challengeResponse = $this->transport->send(new HttpRequest(
            'POST',
            $this->config->challengeUri(),
            ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
            $challengeRequestJson
        ));
        if ($challengeResponse->statusCode < 200 || $challengeResponse->statusCode >= 300) {
            throw new ChallengeRequestFailedException($challengeResponse->statusCode);
        }
        if (strlen($challengeResponse->body) > $this->config->maximumChallengeResponseBytes) {
            throw new InvalidChallengeResponseException('Challenge response exceeds the configured size limit.');
        }

        $challenge = $this->parseChallenge($challengeResponse->body);
        $inputKeyMaterial = '';
        foreach ($challenge['key_ids'] as $identifier) {
            $secret = $this->keyPool->secretFor($identifier);
            if ($secret === null) {
                throw new InvalidChallengeResponseException('Challenge selected a key absent from the local export.');
            }
            $inputKeyMaterial .= $secret;
        }

        $encoder = new CanonicalMessageEncoder();
        $proofKey = (new HkdfSha256())->derive(
            $inputKeyMaterial,
            $challenge['nonce'],
            $encoder->hkdfContext(
                $challenge['version'],
                $this->config->clientIdentifier,
                $challenge['request_id'],
                $this->config->poolVersion,
                $challenge['key_ids']
            )
        );
        $proofMessage = $encoder->proofMessage(
            $challenge['version'],
            $this->config->clientIdentifier,
            $challenge['request_id'],
            $challenge['nonce'],
            $challenge['expires_at'],
            $method,
            $target,
            $bodyHash,
            $this->config->poolVersion,
            $challenge['key_ids']
        );
        $proof = (new HmacSha256())->generate($proofMessage, $proofKey);

        return $this->transport->send(new HttpRequest(
            $method,
            $this->config->requestUri($target),
            [
                'X-MKAP-Version' => '1',
                'X-MKAP-Client' => $this->config->clientIdentifier,
                'X-MKAP-Request' => Base64Url::encode($challenge['request_id']),
                'X-MKAP-Proof' => Base64Url::encode($proof),
            ],
            $body
        ));
    }

    private function parseChallenge(string $json): array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new InvalidChallengeResponseException('Challenge response is not valid JSON.');
        }
        if (!is_array($data) || array_is_list($data)) {
            throw new InvalidChallengeResponseException('Challenge response must be a JSON object.');
        }
        foreach (['version', 'request_id', 'nonce', 'key_ids', 'key_count', 'id_length', 'expires_in', 'expires_at'] as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidChallengeResponseException('Challenge response is missing field: ' . $field);
            }
        }
        if ($data['version'] !== 1
            || !is_string($data['request_id'])
            || !is_string($data['nonce'])
            || !is_string($data['key_ids'])
            || !is_int($data['key_count'])
            || !is_int($data['id_length'])
            || !is_int($data['expires_in'])
            || !is_int($data['expires_at'])
            || $data['key_count'] < 1
            || $data['key_count'] > $this->keyPool->keyCount()
            || $data['id_length'] !== $this->keyPool->identifierLength
            || $data['expires_in'] < 1
            || $data['expires_at'] < 1
            || strlen($data['key_ids']) !== $data['key_count'] * $data['id_length']) {
            throw new InvalidChallengeResponseException('Challenge response fields are invalid.');
        }
        try {
            $requestId = Base64Url::decode($data['request_id'], 16);
            $nonce = Base64Url::decode($data['nonce'], 32);
        } catch (\InvalidArgumentException $error) {
            throw new InvalidChallengeResponseException('Challenge binary fields are malformed.');
        }
        if (Base64Url::encode($requestId) !== $data['request_id']
            || Base64Url::encode($nonce) !== $data['nonce']) {
            throw new InvalidChallengeResponseException('Challenge binary fields must use canonical base64url encoding.');
        }

        $keyIds = str_split($data['key_ids'], $data['id_length']);
        if (count($keyIds) !== $data['key_count']
            || count($keyIds) !== count(array_unique($keyIds))) {
            throw new InvalidChallengeResponseException('Challenge key identifiers are malformed or duplicated.');
        }
        foreach ($keyIds as $identifier) {
            for ($index = 0; $index < strlen($identifier); $index++) {
                if (strpos($this->keyPool->identifierAlphabet, $identifier[$index]) === false) {
                    throw new InvalidChallengeResponseException('Challenge key identifier contains an invalid character.');
                }
            }
        }

        return [
            'version' => $data['version'],
            'request_id' => $requestId,
            'nonce' => $nonce,
            'key_ids' => $keyIds,
            'expires_at' => $data['expires_at'],
        ];
    }
}
