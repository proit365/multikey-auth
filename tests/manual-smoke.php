<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'ProIT365\\MultiKeyAuth\\Core\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    require dirname(__DIR__) . '/src/' . $relative . '.php';
});

use ProIT365\MultiKeyAuth\Core\Cryptography\CanonicalMessageEncoder;
use ProIT365\MultiKeyAuth\Core\Cryptography\HkdfSha256;
use ProIT365\MultiKeyAuth\Core\Cryptography\HmacSha256;
use ProIT365\MultiKeyAuth\Core\Cryptography\BodyHasher;
use ProIT365\MultiKeyAuth\Core\Encoding\Base64Url;
use ProIT365\MultiKeyAuth\Core\Encoding\BinaryEncoder;
use ProIT365\MultiKeyAuth\Core\Export\ExportValidator;
use ProIT365\MultiKeyAuth\Core\Key\IdentifierGenerator;
use ProIT365\MultiKeyAuth\Core\Key\KeyMaterial;
use ProIT365\MultiKeyAuth\Core\Random\RandomGeneratorInterface;
use ProIT365\MultiKeyAuth\Core\Random\SecureRandomGenerator;

$vector = json_decode(
    (string) file_get_contents(dirname(__DIR__, 3) . '/specification/test-vectors-v1.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);

$ikm = hex2bin($vector['hkdf']['ikm_hex']);
$salt = hex2bin($vector['hkdf']['salt_hex']);
$info = hex2bin($vector['hkdf']['info_hex']);
$derived = (new HkdfSha256())->derive($ikm, $salt, $info);
if (bin2hex($derived) !== $vector['hkdf']['output_hex']) {
    throw new RuntimeException('HKDF vector mismatch');
}

$proof = (new CanonicalMessageEncoder())->proofMessage(
    1,
    'CLIENT_001',
    hex2bin('000102030405060708090a0b0c0d0e0f'),
    hex2bin($vector['challenge_fixture']['nonce_hex']),
    $vector['proof']['expires_at'],
    $vector['proof']['http_method'],
    $vector['proof']['request_target'],
    hex2bin($vector['proof']['body_sha256_hex']),
    $vector['proof']['pool_version'],
    $vector['proof']['key_ids']
);
if (bin2hex($proof) !== $vector['proof']['message_hex']) {
    throw new RuntimeException('Proof message vector mismatch');
}

$hmac = (new HmacSha256())->generate($proof, $derived);
if (bin2hex($hmac) !== $vector['proof']['hmac_hex']) {
    throw new RuntimeException('HMAC vector mismatch');
}

if ((new HmacSha256())->verify($proof, $derived, $hmac) !== true) {
    throw new RuntimeException('HMAC verification mismatch');
}
if ((new HmacSha256())->verify($proof . "\x00", $derived, $hmac) !== false) {
    throw new RuntimeException('HMAC verification accepted modified message');
}

$context = (new CanonicalMessageEncoder())->hkdfContext(
    1,
    'CLIENT_001',
    hex2bin('000102030405060708090a0b0c0d0e0f'),
    1,
    $vector['proof']['key_ids']
);
if (bin2hex($context) !== $vector['hkdf']['info_hex']) {
    throw new RuntimeException('HKDF context mismatch');
}

if ((new BodyHasher())->sha256Hex('') !== $vector['proof']['body_sha256_hex']) {
    throw new RuntimeException('Empty body hash mismatch');
}

$encoded = Base64Url::encode("\x00\xFF\x10");
if ($encoded !== 'AP8Q' || Base64Url::decode($encoded) !== "\x00\xFF\x10") {
    throw new RuntimeException('Base64url mismatch');
}
if (BinaryEncoder::uint32(1) !== pack('N', 1)) {
    throw new RuntimeException('Uint32 mismatch');
}
if (BinaryEncoder::uint64(1) !== pack('J', 1)) {
    throw new RuntimeException('Uint64 mismatch');
}

if ((new KeyMaterial(str_repeat("\x01", 32)))->length() !== 32) {
    throw new RuntimeException('Key material length mismatch');
}
foreach ([str_repeat("\x01", 31), str_repeat("\x01", 33)] as $invalidKey) {
    try {
        new KeyMaterial($invalidKey);
        throw new RuntimeException('KeyMaterial accepted invalid length');
    } catch (\InvalidArgumentException) {
    }
}

$deterministic = new class implements RandomGeneratorInterface {
    public function bytes(int $length): string
    {
        return str_repeat("\x00", $length);
    }
};
$identifier = (new IdentifierGenerator($deterministic))->generate();
if ($identifier !== 'aaaaaa') {
    throw new RuntimeException('Identifier fixture mismatch');
}

$secureIdentifier = (new IdentifierGenerator(new SecureRandomGenerator()))->generate();
if (strlen($secureIdentifier) !== 6) {
    throw new RuntimeException('Secure identifier length mismatch');
}

$export = [
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
    'key_count' => 2,
    'keys' => [
        ['id' => 'a8f31x', 'secret' => Base64Url::encode(str_repeat("\x11", 32))],
        ['id' => 'z71q5b', 'secret' => Base64Url::encode(str_repeat("\x12", 32))],
    ],
];
$validated = (new ExportValidator())->validate($export);
if (count($validated) !== 2) {
    throw new RuntimeException('Export validation mismatch');
}
$export['keys'][1]['id'] = 'a8f31x';
try {
    (new ExportValidator())->validate($export);
    throw new RuntimeException('Export validator accepted duplicate identifier');
} catch (\InvalidArgumentException) {
}

$export = [
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
    'key_count' => 1,
    'keys' => [
        ['id' => 'a8f31x', 'secret' => Base64Url::encode(str_repeat("\x11", 31))],
    ],
];
try {
    (new ExportValidator())->validate($export);
    throw new RuntimeException('Export validator accepted malformed secret length');
} catch (\InvalidArgumentException) {
}

echo "Manual core smoke: passed\n";
