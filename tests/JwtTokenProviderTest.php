<?php

declare(strict_types=1);

namespace EnableBanking\Tests;

use DateTimeImmutable;
use EnableBanking\Auth\JwtTokenProvider;
use EnableBanking\Exception\InvalidArgumentException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\TestCase;

final class JwtTokenProviderTest extends TestCase
{
    private static string $privateKey;
    private static string $publicKey;

    public static function setUpBeforeClass(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        openssl_pkey_export($key, $pem);
        self::$privateKey = $pem;
        self::$publicKey = openssl_pkey_get_details($key)['key'];
    }

    public function testTokenHasRequiredHeaderAndClaims(): void
    {
        $now = time();
        $provider = new JwtTokenProvider('app-id', self::$privateKey, 600);
        $token = $provider->getToken();

        [$headerB64] = explode('.', $token);
        $header = json_decode(JWT::urlsafeB64Decode($headerB64), true);
        self::assertSame(['typ' => 'JWT', 'alg' => 'RS256', 'kid' => 'app-id'], $header);

        $claims = (array) JWT::decode($token, new Key(self::$publicKey, 'RS256'));
        self::assertSame('enablebanking.com', $claims['iss']);
        self::assertSame('api.enablebanking.com', $claims['aud']);
        self::assertEqualsWithDelta($now, $claims['iat'], 2);
        self::assertSame($claims['iat'] + 600, $claims['exp']);
    }

    public function testTokenIsCachedUntilNearExpiry(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('@1700000000'));
        $provider = new JwtTokenProvider('app-id', self::$privateKey, 3600, $clock);

        $first = $provider->getToken();
        $clock->now = $clock->now->modify('+3000 seconds');
        self::assertSame($first, $provider->getToken());

        $clock->now = $clock->now->modify('+550 seconds');
        self::assertNotSame($first, $provider->getToken());
    }

    public function testFromFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'eb');
        file_put_contents($path, self::$privateKey);
        try {
            self::assertNotSame('', JwtTokenProvider::fromFile('app-id', $path)->getToken());
        } finally {
            unlink($path);
        }
    }

    public function testRejectsTooLongTtl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new JwtTokenProvider('app-id', self::$privateKey, 86401);
    }

    public function testRejectsInvalidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new JwtTokenProvider('app-id', 'not a key');
    }
}
