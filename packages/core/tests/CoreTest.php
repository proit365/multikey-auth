<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Tests;

use PHPUnit\Framework\TestCase;
use ProIT365\MultiKeyAuth\Core\Cryptography\BodyHasher;
use ProIT365\MultiKeyAuth\Core\Cryptography\CanonicalMessageEncoder;
use ProIT365\MultiKeyAuth\Core\Cryptography\HkdfSha256;
use ProIT365\MultiKeyAuth\Core\Cryptography\HmacSha256;
use ProIT365\MultiKeyAuth\Core\Encoding\Base64Url;
use ProIT365\MultiKeyAuth\Core\Encoding\BinaryEncoder;
use ProIT365\MultiKeyAuth\Core\Export\ExportValidator;
use ProIT365\MultiKeyAuth\Core\Key\IdentifierGenerator;
use ProIT365\MultiKeyAuth\Core\Key\KeyMaterial;
use ProIT365\MultiKeyAuth\Core\Random\RandomGeneratorInterface;
use ProIT365\MultiKeyAuth\Core\Random\SecureRandomGenerator;

final class CoreTest extends TestCase
{
    public function testKnownAnswerVectors(): void
    {
        $vector = $this->loadVector();
        $derived = (new HkdfSha256())->derive(
            hex2bin($vector['hkdf']['ikm_hex']),
            hex2bin($vector['hkdf']['salt_hex']),
            hex2bin($vector['hkdf']['info_hex'])
        );
        self::assertSame($vector['hkdf']['output_hex'], bin2hex($derived));

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
        self::assertSame($vector['proof']['message_hex'], bin2hex($proof));
        self::assertSame(
            $vector['proof']['hmac_hex'],
            bin2hex((new HmacSha256())->generate($proof, $derived))
        );
    }

    public function testHkdfContextMatchesVector(): void
    {
        $vector = $this->loadVector();
        $context = (new CanonicalMessageEncoder())->hkdfContext(
            1,
            'CLIENT_001',
            hex2bin('000102030405060708090a0b0c0d0e0f'),
            1,
            $vector['proof']['key_ids']
        );
        self::assertSame($vector['hkdf']['info_hex'], bin2hex($context));
    }

    public function testConstantTimeVerification(): void
    {
        $hmac = new HmacSha256();
        $key = str_repeat("\x11", 32);
        $proof = $hmac->generate('request', $key);
        self::assertTrue($hmac->verify('request', $key, $proof));
        self::assertFalse($hmac->verify('modified', $key, $proof));
        self::assertFalse($hmac->verify('request', $key, str_repeat("\x00", 31)));
    }

    public function testIdentifierGenerationAndUniqueness(): void
    {
        $generator = new IdentifierGenerator(new SecureRandomGenerator());
        $first = $generator->generate();
        $second = $generator->generate([$first]);
        self::assertSame(6, strlen($first));
        self::assertNotSame($first, $second);
    }

    public function testIdentifierGeneratorUsesUnbiasedAlphabetBytes(): void
    {
        $random = new class implements RandomGeneratorInterface {
            public function bytes(int $length): string
            {
                return str_repeat("\x00", $length);
            }
        };
        $generator = new IdentifierGenerator($random);
        self::assertSame('aaaaaa', $generator->generate());
    }

    public function testKeyMaterialAndBodyHash(): void
    {
        self::assertSame(32, (new KeyMaterial(str_repeat("\x01", 32)))->length());
        self::assertSame(
            'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            (new BodyHasher())->sha256Hex('')
        );
    }

    public function testBase64UrlAndBinaryEncoding(): void
    {
        $bytes = "\x00\xFF\x10";
        self::assertSame('AP8Q', Base64Url::encode($bytes));
        self::assertSame($bytes, Base64Url::decode('AP8Q'));
        self::assertSame(pack('N', 1), BinaryEncoder::uint32(1));
        self::assertSame(pack('N', 1) . 'A', BinaryEncoder::lengthField('A'));
        self::assertSame(8, strlen(BinaryEncoder::uint64(1)));
    }

    public function testExportValidation(): void
    {
        $manifest = $this->validExportManifest();
        $validated = (new ExportValidator())->validate($manifest);
        self::assertCount(2, $validated);

        $manifest['keys'][1]['id'] = 'a8f31x';
        $this->expectException(\InvalidArgumentException::class);
        (new ExportValidator())->validate($manifest);
    }

    public function testMalformedExportSecretLengthIsRejected(): void
    {
        $manifest = $this->validExportManifest();
        $manifest['key_count'] = 1;
        $manifest['keys'] = [['id' => 'a8f31x', 'secret' => Base64Url::encode(str_repeat("\x11", 31))]];

        $this->expectException(\InvalidArgumentException::class);
        (new ExportValidator())->validate($manifest);
    }

    private function loadVector(): array
    {
        $path = __DIR__ . '/fixtures/test-vectors-v1.json';
        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    private function validExportManifest(): array
    {
        return [
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
    }
}
