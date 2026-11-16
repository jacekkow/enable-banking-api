<?php

declare(strict_types=1);

namespace EnableBanking\Model;

use DateTimeImmutable;

/**
 * Account balance, as returned by GET /accounts/{account_id}/balances.
 */
final class Balance
{
    /**
     * @param string                  $balanceType ISO 20022 balance type, e.g. "CLAV", "CLBD", "ITAV"
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public readonly ?string $name,
        public readonly Amount $balanceAmount,
        public readonly string $balanceType,
        public readonly ?DateTimeImmutable $lastChangeDateTime,
        public readonly ?DateTimeImmutable $referenceDate,
        public readonly ?string $lastCommittedTransaction,
        public readonly array $raw,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Data::nullableString($data, 'name'),
            Amount::fromArray(Data::nullableArray($data, 'balance_amount') ?? []),
            Data::string($data, 'balance_type'),
            Data::nullableDate($data, 'last_change_date_time'),
            Data::nullableDate($data, 'reference_date'),
            Data::nullableString($data, 'last_committed_transaction'),
            $data,
        );
    }
}
