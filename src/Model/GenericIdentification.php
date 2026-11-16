<?php

declare(strict_types=1);

namespace EnableBanking\Model;

final class GenericIdentification
{
    public function __construct(
        public readonly string $identification,
        public readonly ?string $schemeName = null,
        public readonly ?string $issuer = null,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Data::string($data, 'identification'),
            Data::nullableString($data, 'scheme_name'),
            Data::nullableString($data, 'issuer'),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'identification' => $this->identification,
            'scheme_name' => $this->schemeName,
            'issuer' => $this->issuer,
        ], static fn (?string $v): bool => $v !== null);
    }
}
