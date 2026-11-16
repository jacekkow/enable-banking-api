<?php

declare(strict_types=1);

namespace EnableBanking\Model;

use EnableBanking\Enum\PsuType;

/**
 * ASPSP with its meta information, as returned by GET /aspsps.
 */
final class AspspData
{
    /**
     * @param list<PsuType>                       $psuTypes
     * @param list<array<array-key, mixed>>       $authMethods Raw AuthMethod objects
     * @param list<string>                        $requiredPsuHeaders
     * @param array<array-key, mixed>             $raw         Complete decoded payload
     */
    public function __construct(
        public readonly string $name,
        public readonly string $country,
        public readonly ?string $logo,
        public readonly ?string $bic,
        public readonly array $psuTypes,
        public readonly array $authMethods,
        public readonly ?int $maximumConsentValidity,
        public readonly bool $beta,
        public readonly array $requiredPsuHeaders,
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
            Data::string($data, 'country'),
            Data::nullableString($data, 'logo'),
            Data::nullableString($data, 'bic'),
            array_values(array_filter(array_map(
                static fn (string $t): ?PsuType => PsuType::tryFrom($t),
                Data::stringList($data, 'psu_types'),
            ))),
            Data::arrayList($data, 'auth_methods'),
            Data::nullableInt($data, 'maximum_consent_validity'),
            Data::nullableBool($data, 'beta') ?? false,
            Data::stringList($data, 'required_psu_headers'),
            $data,
        );
    }

    public function toAspsp(): Aspsp
    {
        return new Aspsp($this->name, $this->country);
    }
}
