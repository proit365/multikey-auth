<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Client;

final class ClientConfig
{
    public readonly string $baseUri;

    public function __construct(
        string $baseUri,
        public readonly string $clientIdentifier,
        public readonly int $poolVersion,
        public readonly int $identifierLength,
        public readonly string $identifierAlphabet,
        public readonly int $maximumChallengeResponseBytes = 65_536,
        bool $requireHttps = true
    ) {
        $parts = parse_url($baseUri);
        if (!is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), $requireHttps ? ['https'] : ['https', 'http'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['path']) && !in_array($parts['path'], ['', '/'], true))) {
            throw new \InvalidArgumentException('Base URI must be an origin with no credentials, path, query, or fragment.');
        }
        if ($clientIdentifier === '' || strlen($clientIdentifier) > 128 || preg_match('//u', $clientIdentifier) !== 1) {
            throw new \InvalidArgumentException('Invalid client identifier.');
        }
        if ($poolVersion < 1) {
            throw new \InvalidArgumentException('Pool version must be positive.');
        }
        if ($identifierLength < 1 || $identifierLength > 256) {
            throw new \InvalidArgumentException('Identifier length is outside the supported range.');
        }
        if ($identifierAlphabet === ''
            || strlen($identifierAlphabet) !== count(array_unique(str_split($identifierAlphabet)))) {
            throw new \InvalidArgumentException('Identifier alphabet must be non-empty and contain unique characters.');
        }
        if ($maximumChallengeResponseBytes < 1 || $maximumChallengeResponseBytes > 16_777_216) {
            throw new \InvalidArgumentException('Maximum challenge response size is outside the supported range.');
        }

        $this->baseUri = rtrim($baseUri, '/');
    }

    public function challengeUri(): string
    {
        return $this->baseUri . '/v1/auth/challenges';
    }

    public function requestUri(string $target): string
    {
        return $this->baseUri . $target;
    }
}
