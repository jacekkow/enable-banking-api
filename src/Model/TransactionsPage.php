<?php

declare(strict_types=1);

namespace EnableBanking\Model;

/**
 * One page of transactions, as returned by GET /accounts/{account_id}/transactions.
 */
final class TransactionsPage
{
    /**
     * @param list<Transaction> $transactions
     * @param string|null       $continuationKey Pass to the next request to fetch the following page; null when done
     */
    public function __construct(
        public readonly array $transactions,
        public readonly ?string $continuationKey,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $key = Data::nullableString($data, 'continuation_key');

        return new self(
            array_map(Transaction::fromArray(...), Data::arrayList($data, 'transactions')),
            $key !== '' ? $key : null,
        );
    }

    public function hasMore(): bool
    {
        return $this->continuationKey !== null;
    }
}
