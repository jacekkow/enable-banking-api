<?php

declare(strict_types=1);

namespace EnableBanking\Model;

/**
 * Creditor or debtor of a transaction.
 */
final class Party
{
    /**
     * @param array<array-key, mixed>|null $postalAddress Raw PostalAddress object
     * @param array<array-key, mixed>      $raw
     */
    public function __construct(
        public readonly ?string $name,
        public readonly ?array $postalAddress,
        public readonly array $raw,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromNullableArray(array $data, string $key): ?self
    {
        $value = Data::nullableArray($data, $key);
        if ($value === null) {
            return null;
        }

        return new self(
            Data::nullableString($value, 'name'),
            Data::nullableArray($value, 'postal_address'),
            $value,
        );
    }
}
