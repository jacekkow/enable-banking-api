<?php

declare(strict_types=1);

namespace EnableBanking\Request;

/**
 * PSU context headers. Some ASPSPs require them (see "required_psu_headers" of an ASPSP) when
 * account data is fetched while the PSU is present, and they may lift ASPSP rate limits.
 */
final class PsuHeaders
{
    /**
     * @param string|null $geoLocation Comma separated latitude and longitude without spaces, e.g. "-1.2345,6.789"
     */
    public function __construct(
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $referer = null,
        public readonly ?string $accept = null,
        public readonly ?string $acceptCharset = null,
        public readonly ?string $acceptEncoding = null,
        public readonly ?string $acceptLanguage = null,
        public readonly ?string $geoLocation = null,
    ) {
    }

    /**
     * Builds the headers from the current HTTP request (by default PHP's $_SERVER).
     *
     * @param array<string, mixed>|null $server
     */
    public static function fromGlobals(?array $server = null): self
    {
        $server ??= $_SERVER;
        $get = static function (string $key) use ($server): ?string {
            $value = $server[$key] ?? null;

            return is_string($value) && $value !== '' ? $value : null;
        };

        return new self(
            $get('REMOTE_ADDR'),
            $get('HTTP_USER_AGENT'),
            $get('HTTP_REFERER'),
            $get('HTTP_ACCEPT'),
            $get('HTTP_ACCEPT_CHARSET'),
            $get('HTTP_ACCEPT_ENCODING'),
            $get('HTTP_ACCEPT_LANGUAGE'),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toHeaders(): array
    {
        return array_filter([
            'Psu-Ip-Address' => $this->ipAddress,
            'Psu-User-Agent' => $this->userAgent,
            'Psu-Referer' => $this->referer,
            'Psu-Accept' => $this->accept,
            'Psu-Accept-Charset' => $this->acceptCharset,
            'Psu-Accept-Encoding' => $this->acceptEncoding,
            'Psu-Accept-Language' => $this->acceptLanguage,
            'Psu-Geo-Location' => $this->geoLocation,
        ], static fn (?string $v): bool => $v !== null);
    }
}
