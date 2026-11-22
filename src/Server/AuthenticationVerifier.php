<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Server;

use ProIT365\MultiKeyAuth\Core\Cryptography\BodyHasher;
use ProIT365\MultiKeyAuth\Core\Cryptography\CanonicalMessageEncoder;
use ProIT365\MultiKeyAuth\Core\Cryptography\HkdfSha256;
use ProIT365\MultiKeyAuth\Core\Cryptography\HmacSha256;
use ProIT365\MultiKeyAuth\Core\Storage\KeyAssociatedData;
use ProIT365\MultiKeyAuth\Core\Storage\KeyRepositoryInterface;
use ProIT365\MultiKeyAuth\Core\Storage\SecretCipher;

final class AuthenticationVerifier
{
    public function __construct(
        private readonly ChallengeRepositoryInterface $challenges,
        private readonly KeyRepositoryInterface $keys,
        private readonly SecretCipher $cipher,
        private readonly MasterKeyProviderInterface $masterKeys,
        private readonly RateLimiterInterface $rateLimiter,
        private readonly ClockInterface $clock,
        private readonly BodyHasher $bodyHasher,
        private readonly CanonicalMessageEncoder $encoder,
        private readonly HkdfSha256 $hkdf,
        private readonly HmacSha256 $hmac,
        private readonly int $maximumBodyBytes = 1_048_576
    ) {
        if ($maximumBodyBytes < 0) {
            throw new \InvalidArgumentException('Maximum request body size must not be negative.');
        }
    }

    public function verify(AuthenticatedRequest $request): ChallengeRecord
    {
        if (!$this->rateLimiter->allow($request->clientIdentifier, 'authenticate')) {
            throw new RateLimitExceededException();
        }
        $challenge = $this->challenges->findByRequestIdentifier($request->requestIdentifier);
        if ($challenge === null) {
            throw new UnknownChallengeException();
        }
        $now = $this->clock->now();
        if ($challenge->status !== 'pending') {
            throw new ReplayDetectedException();
        }
        if ($challenge->expiresAt <= $now) {
            throw new ExpiredChallengeException();
        }
        if (strlen($request->body) > $this->maximumBodyBytes) {
            throw new RequestTooLargeException();
        }
        if ($request->clientIdentifier !== $challenge->clientIdentifier
            || $request->protocolVersion !== $challenge->protocolVersion
            || $request->method !== $challenge->method
            || $request->target !== $challenge->target
            || !hash_equals($challenge->bodySha256, $this->bodyHasher->sha256($request->body))) {
            throw new AuthenticationFailedException();
        }

        $records = $this->keys->findActiveByIdentifiers(
            (string) $challenge->poolRecordId,
            $challenge->keyIdentifiers
        );
        if (count($records) !== count($challenge->keyIdentifiers)) {
            throw new AuthenticationFailedException();
        }

        $inputKeyMaterial = '';
        $masterKey = $this->masterKeys->forPool($challenge->poolIdentifier);
        foreach ($challenge->keyIdentifiers as $identifier) {
            $record = $records[$identifier] ?? null;
            if ($record === null) {
                throw new AuthenticationFailedException();
            }
            $inputKeyMaterial .= $this->cipher->decrypt(
                $record->encryptedSecret,
                $masterKey,
                KeyAssociatedData::forKey($challenge->poolIdentifier, $identifier)
            );
        }

        $info = $this->encoder->hkdfContext(
            $challenge->protocolVersion,
            $challenge->clientIdentifier,
            $challenge->requestIdentifier,
            $challenge->poolVersion,
            $challenge->keyIdentifiers
        );
        $proofKey = $this->hkdf->derive($inputKeyMaterial, $challenge->nonce, $info);
        $message = $this->encoder->proofMessage(
            $challenge->protocolVersion,
            $challenge->clientIdentifier,
            $challenge->requestIdentifier,
            $challenge->nonce,
            $challenge->expiresAt,
            $challenge->method,
            $challenge->target,
            $challenge->bodySha256,
            $challenge->poolVersion,
            $challenge->keyIdentifiers
        );
        if (!$this->hmac->verify($message, $proofKey, $request->proof)) {
            throw new AuthenticationFailedException();
        }
        if (!$this->challenges->consumeIfPending($challenge->requestIdentifier, $now)) {
            throw new ReplayDetectedException();
        }

        return $challenge;
    }
}
