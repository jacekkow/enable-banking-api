<?php

declare(strict_types=1);

namespace EnableBanking\Model;

/**
 * Account identified either by IBAN or by another identification scheme.
 */
final class AccountIdentification
{
    public function __construct(
        public readonly ?string $iban = null,
        public readonly ?GenericIdentification $other = null,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $other = Data::nullableArray($data, 'other');

        return new self(
            Data::nullableString($data, 'iban'),
            $other !== null ? GenericIdentification::fromArray($other) : null,
        );
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromNullableArray(array $data, string $key): ?self
    {
        $value = Data::nullableArray($data, $key);

        return $value !== null ? self::fromArray($value) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];
        if ($this->iban !== null) {
            $result['iban'] = $this->iban;
        }
        if ($this->other !== null) {
            $result['other'] = $this->other->toArray();
        }

        return $result;
    }
}
