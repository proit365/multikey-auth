<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'ProIT365\\MultiKeyAuth\\Core\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    if (str_starts_with($relative, 'Server/')) {
        require dirname(__DIR__, 2) . '/server/src/' . $relative . '.php';
        return;
    }
    require dirname(__DIR__) . '/src/' . $relative . '.php';
});

use ProIT365\MultiKeyAuth\Core\Cryptography\BodyHasher;
use ProIT365\MultiKeyAuth\Core\Cryptography\CanonicalMessageEncoder;
use ProIT365\MultiKeyAuth\Core\Cryptography\HkdfSha256;
use ProIT365\MultiKeyAuth\Core\Cryptography\HmacSha256;
use ProIT365\MultiKeyAuth\Core\Key\IdentifierGenerator;
use ProIT365\MultiKeyAuth\Core\Key\KeyStatus;
use ProIT365\MultiKeyAuth\Core\Key\PoolMetadata;
use ProIT365\MultiKeyAuth\Core\Random\SecureRandomGenerator;
use ProIT365\MultiKeyAuth\Core\Random\SecureRandomIntegerGenerator;
use ProIT365\MultiKeyAuth\Core\Server\AuthenticatedRequest;
use ProIT365\MultiKeyAuth\Core\Server\AuthenticationFailedException;
use ProIT365\MultiKeyAuth\Core\Server\AuthenticationVerifier;
use ProIT365\MultiKeyAuth\Core\Server\ChallengeIssuer;
use ProIT365\MultiKeyAuth\Core\Server\ChallengeRequest;
use ProIT365\MultiKeyAuth\Core\Server\ClientPool;
use ProIT365\MultiKeyAuth\Core\Server\ExpiredChallengeException;
use ProIT365\MultiKeyAuth\Core\Server\ErrorStatusMapper;
use ProIT365\MultiKeyAuth\Core\Server\FixedWindowRateLimiter;
use ProIT365\MultiKeyAuth\Core\Server\InMemoryChallengeRepository;
use ProIT365\MultiKeyAuth\Core\Server\InMemoryClientPoolProvider;
use ProIT365\MultiKeyAuth\Core\Server\MasterKeyProviderInterface;
use ProIT365\MultiKeyAuth\Core\Server\RateLimitExceededException;
use ProIT365\MultiKeyAuth\Core\Server\RequestTooLargeException;
use ProIT365\MultiKeyAuth\Core\Server\ReplayDetectedException;
use ProIT365\MultiKeyAuth\Core\Server\UnknownChallengeException;
use ProIT365\MultiKeyAuth\Core\Storage\EncryptedSecret;
use ProIT365\MultiKeyAuth\Core\Storage\InMemoryKeyRepository;
use ProIT365\MultiKeyAuth\Core\Storage\KeyAssociatedData;
use ProIT365\MultiKeyAuth\Core\Storage\KeyRecord;
use ProIT365\MultiKeyAuth\Core\Storage\KeyRepositoryInterface;
use ProIT365\MultiKeyAuth\Core\Storage\MasterKey;
use ProIT365\MultiKeyAuth\Core\Storage\PdoKeyRepository;
use ProIT365\MultiKeyAuth\Core\Storage\SecretCipher;
use ProIT365\MultiKeyAuth\Core\Server\PdoChallengeRepository;
use ProIT365\MultiKeyAuth\Core\Server\UnknownClientException;
use ProIT365\MultiKeyAuth\Core\Server\ClockInterface;

$clock = new class implements ClockInterface {
    public int $timestamp = 1_893_456_000;

    public function now(): int
    {
        return $this->timestamp;
    }
};
$random = new SecureRandomGenerator();
$masterKey = new MasterKey(str_repeat("\x71", 32), 1);
$cipher = new SecretCipher($random);
$keyRepository = new InMemoryKeyRepository();
$pool = new ClientPool(
    1,
    1,
    'CLIENT_001',
    new PoolMetadata(
        'POOL_001',
        1,
        6,
        IdentifierGenerator::DEFAULT_ALPHABET,
        5,
        3
    )
);
$clientPools = new InMemoryClientPoolProvider();
$clientPools->add($pool);

$keyIds = ['a8f31x', 'z71q5b', 'k92p4m', 'm4n2p8', 'q2r6s9'];
$secrets = [];
foreach ($keyIds as $index => $keyId) {
    $secret = str_repeat(chr(0x20 + $index), 32);
    $secrets[$keyId] = $secret;
    $encrypted = $cipher->encrypt(
        $secret,
        $masterKey,
        KeyAssociatedData::forKey('POOL_001', $keyId)
    );
    $keyRepository->storeKey(
        '1',
        new KeyRecord($keyId, $encrypted, KeyStatus::Active)
    );
}

$challengeRepository = new InMemoryChallengeRepository();
$rateLimiter = new FixedWindowRateLimiter($clock, 100, 60);
$issuer = new ChallengeIssuer(
    $clientPools,
    $keyRepository,
    $challengeRepository,
    $rateLimiter,
    $random,
    new SecureRandomIntegerGenerator(),
    $clock,
    30
);
$body = '{"order":42}';
$request = new ChallengeRequest(1, 'CLIENT_001', 'POST', '/api/orders?state=new', (new BodyHasher())->sha256($body));
$response = $issuer->issue($request);
$wire = $response->toArray();
if ($wire['key_count'] !== 3 || strlen($wire['key_ids']) !== 18 || $wire['expires_in'] !== 30) {
    throw new RuntimeException('Challenge response metadata mismatch.');
}
$challenge = $response->challenge;
if (count(array_unique($challenge->keyIdentifiers)) !== 3) {
    throw new RuntimeException('Challenge selected duplicate key identifiers.');
}

$ikm = '';
foreach ($challenge->keyIdentifiers as $keyId) {
    $ikm .= $secrets[$keyId];
}
$encoder = new CanonicalMessageEncoder();
$proofKey = (new HkdfSha256())->derive(
    $ikm,
    $challenge->nonce,
    $encoder->hkdfContext(
        1,
        $challenge->clientIdentifier,
        $challenge->requestIdentifier,
        $challenge->poolVersion,
        $challenge->keyIdentifiers
    )
);
$proofMessage = $encoder->proofMessage(
    1,
    $challenge->clientIdentifier,
    $challenge->requestIdentifier,
    $challenge->nonce,
    $challenge->expiresAt,
    $request->method,
    $request->target,
    $request->bodySha256,
    $challenge->poolVersion,
    $challenge->keyIdentifiers
);
$proof = (new HmacSha256())->generate($proofMessage, $proofKey);
$masterKeys = new class($masterKey) implements MasterKeyProviderInterface {
    public function __construct(private readonly MasterKey $masterKey)
    {
    }
    public function forPool(string $poolIdentifier): MasterKey
    {
        if ($poolIdentifier !== 'POOL_001') {
            throw new RuntimeException('Unexpected pool.');
        }
        return $this->masterKey;
    }
};
$verifier = new AuthenticationVerifier(
    $challengeRepository,
    $keyRepository,
    $cipher,
    $masterKeys,
    $rateLimiter,
    $clock,
    new BodyHasher(),
    $encoder,
    new HkdfSha256(),
    new HmacSha256()
);
$authenticated = new AuthenticatedRequest(
    'CLIENT_001',
    $challenge->requestIdentifier,
    $proof,
    'POST',
    '/api/orders?state=new',
    $body
);
foreach ([
    new AuthenticatedRequest('CLIENT_001', $challenge->requestIdentifier, $proof, 'GET', '/api/orders?state=new', $body),
    new AuthenticatedRequest('CLIENT_001', $challenge->requestIdentifier, $proof, 'POST', '/api/other', $body),
    new AuthenticatedRequest('CLIENT_001', $challenge->requestIdentifier, $proof, 'POST', '/api/orders?state=new', $body . 'x'),
    new AuthenticatedRequest('OTHER_CLIENT', $challenge->requestIdentifier, $proof, 'POST', '/api/orders?state=new', $body),
    new AuthenticatedRequest('CLIENT_001', $challenge->requestIdentifier, $proof, 'POST', '/api/orders?state=new', $body, 2),
] as $mismatchedRequest) {
    try {
        $verifier->verify($mismatchedRequest);
        throw new RuntimeException('Request binding mismatch was accepted.');
    } catch (AuthenticationFailedException) {
    }
}
$verifier->verify($authenticated);
if ((new ErrorStatusMapper())->statusCode(new AuthenticationFailedException()) !== 401
    || (new ErrorStatusMapper())->statusCode(new RequestTooLargeException()) !== 413
    || (new ErrorStatusMapper())->statusCode(new RateLimitExceededException()) !== 429) {
    throw new RuntimeException('Authentication error status mapping mismatch.');
}

try {
    $verifier->verify($authenticated);
    throw new RuntimeException('Replay was accepted.');
} catch (ReplayDetectedException) {
}

$badChallenge = $issuer->issue($request)->challenge;
try {
    $verifier->verify(new AuthenticatedRequest(
        'CLIENT_001',
        $badChallenge->requestIdentifier,
        str_repeat("\x00", 32),
        'POST',
        '/api/orders?state=new',
        $body
    ));
    throw new RuntimeException('Invalid proof was accepted.');
} catch (AuthenticationFailedException) {
}

try {
    $verifier->verify(new AuthenticatedRequest(
        'CLIENT_001',
        str_repeat("\xFF", 16),
        $proof,
        'POST',
        '/api/orders?state=new',
        $body
    ));
    throw new RuntimeException('Unknown challenge was accepted.');
} catch (UnknownChallengeException) {
}

$expired = $issuer->issue($request)->challenge;
$clock->timestamp = $expired->expiresAt;
try {
    $verifier->verify(new AuthenticatedRequest(
        'CLIENT_001',
        $expired->requestIdentifier,
        str_repeat("\x00", 32),
        'POST',
        '/api/orders?state=new',
        $body
    ));
    throw new RuntimeException('Expired challenge was accepted.');
} catch (ExpiredChallengeException) {
}

$unregisteredLimiter = new FixedWindowRateLimiter($clock, 100, 60);
$unregisteredIssuer = new ChallengeIssuer(
    new InMemoryClientPoolProvider(),
    $keyRepository,
    $challengeRepository,
    $unregisteredLimiter,
    $random,
    new SecureRandomIntegerGenerator(),
    $clock
);
try {
    $unregisteredIssuer->issue($request);
    throw new RuntimeException('Unknown client was accepted.');
} catch (UnknownClientException) {
}

$limitedIssuer = new ChallengeIssuer(
    $clientPools,
    $keyRepository,
    $challengeRepository,
    new FixedWindowRateLimiter($clock, 1, 60),
    $random,
    new SecureRandomIntegerGenerator(),
    $clock
);
$limitedIssuer->issue($request);
try {
    $limitedIssuer->issue($request);
    throw new RuntimeException('Rate limit was not enforced.');
} catch (RateLimitExceededException) {
}

$sqlite = new PDO('sqlite::memory:');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->exec(file_get_contents(dirname(__DIR__, 3) . '/database/sqlite/001_mkap_tables.sql'));
$sqlite->exec(
    "INSERT INTO mkap_clients (client_identifier, name, status, created_at, updated_at) "
    . "VALUES ('CLIENT_001', 'Test', 'active', '2030-01-01', '2030-01-01')"
);
$sqlite->exec(
    "INSERT INTO mkap_key_pools "
    . "(client_id, pool_identifier, pool_version, identifier_length, identifier_alphabet, secret_length_bytes, "
    . "key_count, selection_count, status, created_at, updated_at) "
    . "VALUES (1, 'POOL_001', 1, 6, 'abcdefghijklmnopqrstuvwxyz0123456789', 32, 5, 3, 'active', '2030-01-01', '2030-01-01')"
);
$pdoKeys = new PdoKeyRepository($sqlite);
foreach ($keyIds as $keyId) {
    $encrypted = $cipher->encrypt(
        $secrets[$keyId],
        $masterKey,
        KeyAssociatedData::forKey('POOL_001', $keyId)
    );
    $pdoKeys->storeKey('1', new KeyRecord($keyId, $encrypted, KeyStatus::Active));
}
$pdoChallenges = new PdoChallengeRepository($sqlite);
$pdoIssuer = new ChallengeIssuer(
    $clientPools,
    $pdoKeys,
    $pdoChallenges,
    new FixedWindowRateLimiter($clock, 100, 60),
    $random,
    new SecureRandomIntegerGenerator(),
    $clock
);
$clock->timestamp = 1_893_456_000;
$pdoChallenge = $pdoIssuer->issue($request)->challenge;
if ($pdoChallenges->findByRequestIdentifier($pdoChallenge->requestIdentifier) === null) {
    throw new RuntimeException('PDO challenge persistence lookup failed.');
}
$pdoIkm = '';
foreach ($pdoChallenge->keyIdentifiers as $keyId) {
    $pdoIkm .= $secrets[$keyId];
}
$pdoProofKey = (new HkdfSha256())->derive(
    $pdoIkm,
    $pdoChallenge->nonce,
    $encoder->hkdfContext(
        1,
        $pdoChallenge->clientIdentifier,
        $pdoChallenge->requestIdentifier,
        $pdoChallenge->poolVersion,
        $pdoChallenge->keyIdentifiers
    )
);
$pdoProofMessage = $encoder->proofMessage(
    1,
    $pdoChallenge->clientIdentifier,
    $pdoChallenge->requestIdentifier,
    $pdoChallenge->nonce,
    $pdoChallenge->expiresAt,
    $request->method,
    $request->target,
    $request->bodySha256,
    $pdoChallenge->poolVersion,
    $pdoChallenge->keyIdentifiers
);
$pdoProof = (new HmacSha256())->generate($pdoProofMessage, $pdoProofKey);
$pdoVerifier = new AuthenticationVerifier(
    $pdoChallenges,
    $pdoKeys,
    $cipher,
    $masterKeys,
    new FixedWindowRateLimiter($clock, 100, 60),
    $clock,
    new BodyHasher(),
    $encoder,
    new HkdfSha256(),
    new HmacSha256()
);
$pdoVerifier->verify(new AuthenticatedRequest(
    'CLIENT_001',
    $pdoChallenge->requestIdentifier,
    $pdoProof,
    $request->method,
    $request->target,
    $body
));
try {
    $pdoVerifier->verify(new AuthenticatedRequest(
        'CLIENT_001',
        $pdoChallenge->requestIdentifier,
        $pdoProof,
        $request->method,
        $request->target,
        $body
    ));
    throw new RuntimeException('PDO challenge was consumed twice.');
} catch (ReplayDetectedException) {
}

echo "Manual phase 06 smoke: passed\n";
