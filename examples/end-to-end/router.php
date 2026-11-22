<?php

declare(strict_types=1);

use ProIT365\MultiKeyAuth\Core\Cryptography\BodyHasher;
use ProIT365\MultiKeyAuth\Core\Cryptography\CanonicalMessageEncoder;
use ProIT365\MultiKeyAuth\Core\Cryptography\HkdfSha256;
use ProIT365\MultiKeyAuth\Core\Cryptography\HmacSha256;
use ProIT365\MultiKeyAuth\Core\Encoding\Base64Url;
use ProIT365\MultiKeyAuth\Core\Key\PoolMetadata;
use ProIT365\MultiKeyAuth\Core\Random\SecureRandomGenerator;
use ProIT365\MultiKeyAuth\Core\Random\SecureRandomIntegerGenerator;
use ProIT365\MultiKeyAuth\Core\Server\AuthenticatedRequest;
use ProIT365\MultiKeyAuth\Core\Server\AuthenticationVerifier;
use ProIT365\MultiKeyAuth\Core\Server\ChallengeIssuer;
use ProIT365\MultiKeyAuth\Core\Server\ChallengeRequest;
use ProIT365\MultiKeyAuth\Core\Server\ChallengeResponse;
use ProIT365\MultiKeyAuth\Core\Server\ClientPool;
use ProIT365\MultiKeyAuth\Core\Server\ErrorStatusMapper;
use ProIT365\MultiKeyAuth\Core\Server\InMemoryClientPoolProvider;
use ProIT365\MultiKeyAuth\Core\Server\SystemClock;
use ProIT365\MultiKeyAuth\Core\Server\PdoChallengeRepository;
use ProIT365\MultiKeyAuth\Core\Server\MasterKeyProviderInterface;
use ProIT365\MultiKeyAuth\Core\Server\RequestTooLargeException;
use ProIT365\MultiKeyAuth\Core\Storage\MasterKey;
use ProIT365\MultiKeyAuth\Core\Storage\PdoKeyRepository;
use ProIT365\MultiKeyAuth\Core\Storage\SecretCipher;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/DemoSqliteRateLimiter.php';

const DEMO_CLIENT_ID = 'DEMO_CLIENT';
const DEMO_POOL_ID = 'DEMO_POOL';
const MAXIMUM_BODY_BYTES = 1_048_576;

function respond(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function readRequestBody(): string
{
    $body = file_get_contents('php://input', false, null, 0, MAXIMUM_BODY_BYTES + 1);
    if ($body === false) {
        throw new RuntimeException('Unable to read request body.');
    }
    if (strlen($body) > MAXIMUM_BODY_BYTES) {
        throw new RequestTooLargeException();
    }
    return $body;
}

function protocolHeader(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    $value = $_SERVER[$key] ?? null;
    if (!is_string($value) || $value === '') {
        throw new InvalidArgumentException('A required MKAP header is missing.');
    }
    return $value;
}

try {
    $dataDirectory = __DIR__ . '/data';
    $database = new PDO('sqlite:' . $dataDirectory . '/mkap.sqlite');
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $database->exec('PRAGMA foreign_keys = ON');
    $database->exec('PRAGMA busy_timeout = 5000');

    $masterKeyBytes = file_get_contents($dataDirectory . '/master-key.bin');
    if ($masterKeyBytes === false) {
        throw new RuntimeException('Demo master key is missing. Run setup.php first.');
    }
    $masterKey = new MasterKey($masterKeyBytes);

    $statement = $database->prepare(
        'SELECT cl.id AS client_record_id, kp.id AS pool_record_id, kp.pool_version, '
        . 'kp.identifier_length, kp.identifier_alphabet, kp.key_count, kp.selection_count '
        . 'FROM mkap_clients cl JOIN mkap_key_pools kp ON kp.client_id = cl.id '
        . 'WHERE cl.client_identifier = ? AND cl.status = ? AND kp.pool_identifier = ? AND kp.status = ?'
    );
    $statement->execute([DEMO_CLIENT_ID, 'active', DEMO_POOL_ID, 'active']);
    $poolRow = $statement->fetch(PDO::FETCH_ASSOC);
    if ($poolRow === false) {
        throw new RuntimeException('Demo client pool is missing. Run setup.php first.');
    }

    $metadata = new PoolMetadata(
        DEMO_POOL_ID,
        (int) $poolRow['pool_version'],
        (int) $poolRow['identifier_length'],
        (string) $poolRow['identifier_alphabet'],
        (int) $poolRow['key_count'],
        (int) $poolRow['selection_count']
    );
    $clients = new InMemoryClientPoolProvider();
    $clients->add(new ClientPool(
        (int) $poolRow['client_record_id'],
        (int) $poolRow['pool_record_id'],
        DEMO_CLIENT_ID,
        $metadata
    ));

    $clock = new SystemClock();
    $random = new SecureRandomGenerator();
    $rateLimiter = new DemoSqliteRateLimiter($database, $clock, 100, 60);
    $keys = new PdoKeyRepository($database);
    $challenges = new PdoChallengeRepository($database);
    $cipher = new SecretCipher($random);
    $masterKeys = new class($masterKey) implements MasterKeyProviderInterface {
        public function __construct(private readonly MasterKey $masterKey)
        {
        }

        public function forPool(string $poolIdentifier): MasterKey
        {
            if ($poolIdentifier !== DEMO_POOL_ID) {
                throw new RuntimeException('Unknown demo key pool.');
            }
            return $this->masterKey;
        }
    };

    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    $target = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($target, PHP_URL_PATH);

    if ($method === 'POST' && $path === '/v1/auth/challenges') {
        $requestBody = readRequestBody();
        try {
            $data = json_decode($requestBody, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Malformed challenge JSON.', 0, $error);
        }
        if (!is_array($data)
            || ($data['version'] ?? null) !== 1
            || !is_string($data['client_id'] ?? null)
            || !is_string($data['method'] ?? null)
            || !is_string($data['target'] ?? null)
            || !is_string($data['body_sha256'] ?? null)) {
            throw new InvalidArgumentException('Malformed challenge request.');
        }
        $bodyHash = Base64Url::decode($data['body_sha256'], 32);
        if (Base64Url::encode($bodyHash) !== $data['body_sha256']) {
            throw new InvalidArgumentException('Non-canonical body hash.');
        }

        $issuer = new ChallengeIssuer(
            $clients,
            $keys,
            $challenges,
            $rateLimiter,
            $random,
            new SecureRandomIntegerGenerator(),
            $clock
        );
        $challenge = $issuer->issue(new ChallengeRequest(
            $data['version'],
            $data['client_id'],
            $data['method'],
            $data['target'],
            $bodyHash
        ));
        respond(200, (new ChallengeResponse($challenge->challenge))->toArray());
        return;
    }

    if ($method === 'POST' && $path === '/api/orders') {
        if (protocolHeader('X-MKAP-Version') !== '1') {
            throw new InvalidArgumentException('Unsupported MKAP protocol version.');
        }
        $requestIdentifierValue = protocolHeader('X-MKAP-Request');
        $proofValue = protocolHeader('X-MKAP-Proof');
        $requestIdentifier = Base64Url::decode($requestIdentifierValue, 16);
        $proof = Base64Url::decode($proofValue, 32);
        if (Base64Url::encode($requestIdentifier) !== $requestIdentifierValue
            || Base64Url::encode($proof) !== $proofValue) {
            throw new InvalidArgumentException('Non-canonical MKAP header encoding.');
        }

        $verifier = new AuthenticationVerifier(
            $challenges,
            $keys,
            $cipher,
            $masterKeys,
            $rateLimiter,
            $clock,
            new BodyHasher(),
            new CanonicalMessageEncoder(),
            new HkdfSha256(),
            new HmacSha256()
        );
        $verifier->verify(new AuthenticatedRequest(
            protocolHeader('X-MKAP-Client'),
            $requestIdentifier,
            $proof,
            $method,
            $target,
            readRequestBody()
        ));
        respond(200, [
            'authenticated' => true,
            'message' => 'The PHP server accepted the authenticated order request.',
        ]);
        return;
    }

    respond(404, ['error' => 'not_found']);
} catch (Throwable $error) {
    $status = (new ErrorStatusMapper())->statusCode($error);
    if ($status >= 500) {
        error_log('MKAP example server error: ' . $error->getMessage());
        respond(500, ['error' => 'internal_error']);
    } else {
        respond($status, ['error' => 'request_rejected']);
    }
}
