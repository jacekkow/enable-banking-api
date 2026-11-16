<?php

declare(strict_types=1);

namespace EnableBanking\Model;

/**
 * Monetary amount. The amount is kept as a decimal string to avoid floating point rounding.
 */
final class Amount
{
    public function __construct(
        public readonly string $currency,
        public readonly string $amount,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(Data::string($data, 'currency'), Data::string($data, 'amount'));
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromNullableArray(array $data, string $key): ?self
    {
        $value = Data::nullableArray($data, $key);

        return $value !== null ? self::fromArray($value) : null;
    }
}
