<?php

declare(strict_types=1);

namespace EnableBanking\Auth;

use EnableBanking\Exception\InvalidArgumentException;
use Firebase\JWT\JWT;
use OpenSSLAsymmetricKey;
use Psr\Clock\ClockInterface;

/**
 * Creates RS256-signed JWTs as required by the Enable Banking API.
 *
 * Tokens are cached and reused until shortly before they expire.
 *
 * @see https://enablebanking.com/docs/api/reference/#jwt-format-and-signature
 */
final class JwtTokenProvider implements TokenProviderInterface
{
    public const ISSUER = 'enablebanking.com';
    public const AUDIENCE = 'api.enablebanking.com';
    public const MAX_TTL = 86400;

    /** Regenerate the token this many seconds before it actually expires. */
    private const REFRESH_MARGIN = 60;

    private OpenSSLAsymmetricKey $privateKey;
    private ?string $token = null;
    private int $expiresAt = 0;

    /**
     * @param string                      $applicationId Application ID (JWT "kid") assigned on registration
     * @param string|OpenSSLAsymmetricKey $privateKey    PEM-encoded RSA private key or an OpenSSL key
     * @param int                         $ttl           Token lifetime in seconds (max. 86400)
     */
    public function __construct(
        private readonly string $applicationId,
        #[\SensitiveParameter] string|OpenSSLAsymmetricKey $privateKey,
        private readonly int $ttl = 3600,
        private readonly ?ClockInterface $clock = null,
        #[\SensitiveParameter] ?string $passphrase = null,
    ) {
        if ($applicationId === '') {
            throw new InvalidArgumentException('Application ID must not be empty.');
        }
        if ($ttl <= self::REFRESH_MARGIN || $ttl > self::MAX_TTL) {
            throw new InvalidArgumentException(sprintf(
                'Token TTL must be between %d and %d seconds, %d given.',
                self::REFRESH_MARGIN + 1,
                self::MAX_TTL,
                $ttl,
            ));
        }

        if (is_string($privateKey)) {
            $key = openssl_pkey_get_private($privateKey, $passphrase);
            if ($key === false) {
                throw new InvalidArgumentException('Unable to load the RSA private key: ' . (openssl_error_string() ?: 'unknown error'));
            }
            $privateKey = $key;
        }
        $this->privateKey = $privateKey;
    }

    /**
     * @param string $path Path to a PEM file with the RSA private key
     */
    public static function fromFile(
        string $applicationId,
        string $path,
        int $ttl = 3600,
        ?ClockInterface $clock = null,
        ?string $passphrase = null,
    ): self {
        $pem = @file_get_contents($path);
        if ($pem === false) {
            throw new InvalidArgumentException(sprintf('Unable to read private key file "%s".', $path));
        }

        return new self($applicationId, $pem, $ttl, $clock, $passphrase);
    }

    public function getToken(): string
    {
        $now = $this->now();
        if ($this->token === null || $now >= $this->expiresAt - self::REFRESH_MARGIN) {
            $this->expiresAt = $now + $this->ttl;
            $this->token = JWT::encode(
                [
                    'iss' => self::ISSUER,
                    'aud' => self::AUDIENCE,
                    'iat' => $now,
                    'exp' => $this->expiresAt,
                ],
                $this->privateKey,
                'RS256',
                $this->applicationId,
            );
        }

        return $this->token;
    }

    private function now(): int
    {
        return $this->clock?->now()->getTimestamp() ?? time();
    }
}
