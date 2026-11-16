<?php

declare(strict_types=1);

namespace EnableBanking\Model;

/**
 * Application associated with the JWT key ID, as returned by GET /application.
 */
final class Application
{
    /**
     * @param list<string>            $redirectUrls
     * @param list<string>            $countries
     * @param list<string>            $services
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $kid,
        public readonly ?string $environment,
        public readonly array $redirectUrls,
        public readonly bool $active,
        public readonly array $countries,
        public readonly array $services,
        public readonly array $raw,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Data::string($data, 'name'),
            Data::nullableString($data, 'description'),
            Data::nullableString($data, 'kid'),
            Data::nullableString($data, 'environment'),
            Data::stringList($data, 'redirect_urls'),
            Data::nullableBool($data, 'active') ?? false,
            Data::stringList($data, 'countries'),
            Data::stringList($data, 'services'),
            $data,
        );
    }
}
