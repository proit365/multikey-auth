<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Server;

use ProIT365\MultiKeyAuth\Core\Encoding\Base64Url;

final class ChallengeResponse
{
    public function __construct(public readonly ChallengeRecord $challenge)
    {
    }

    public function toArray(): array
    {
        return [
            'version' => $this->challenge->protocolVersion,
            'request_id' => Base64Url::encode($this->challenge->requestIdentifier),
            'nonce' => Base64Url::encode($this->challenge->nonce),
            'key_ids' => implode('', $this->challenge->keyIdentifiers),
            'key_count' => count($this->challenge->keyIdentifiers),
            'id_length' => $this->challenge->identifierLength,
            'expires_in' => $this->challenge->expiresAt - $this->challenge->createdAt,
            'expires_at' => $this->challenge->expiresAt,
        ];
    }
}
