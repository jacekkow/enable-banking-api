<?php

declare(strict_types=1);

namespace EnableBanking\Model;

/**
 * Result of POST /auth: the URL the PSU must be redirected to.
 */
final class AuthorizationStart
{
    public function __construct(
        public readonly string $url,
        public readonly string $authorizationId,
        public readonly ?string $psuIdHash,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Data::string($data, 'url'),
            Data::string($data, 'authorization_id'),
            Data::nullableString($data, 'psu_id_hash'),
        );
    }
}
