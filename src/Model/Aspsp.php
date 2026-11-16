<?php

declare(strict_types=1);

namespace EnableBanking\Model;

/**
 * ASPSP (bank or similar financial institution) reference: name and two-letter country code.
 */
final class Aspsp
{
    public function __construct(
        public readonly string $name,
        public readonly string $country,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(Data::string($data, 'name'), Data::string($data, 'country'));
    }

    /**
     * @return array{name: string, country: string}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'country' => $this->country];
    }
}
