<?php

declare(strict_types=1);

namespace EnableBanking\Model;

/**
 * Account resource, as returned by POST /sessions and GET /accounts/{account_id}/details.
 */
final class Account
{
    /**
     * @param string|null                  $uid               Account ID to be used with the /accounts endpoints
     * @param list<GenericIdentification>  $allAccountIds
     * @param list<string>                 $identificationHashes
     * @param array<array-key, mixed>      $raw               Complete decoded payload
     */
    public function __construct(
        public readonly ?string $uid,
        public readonly ?AccountIdentification $accountId,
        public readonly array $allAccountIds,
        public readonly ?string $name,
        public readonly ?string $details,
        public readonly ?string $usage,
        public readonly ?string $cashAccountType,
        public readonly ?string $product,
        public readonly ?string $currency,
        public readonly ?Amount $creditLimit,
        public readonly ?string $identificationHash,
        public readonly array $identificationHashes,
        public readonly array $raw,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Data::nullableString($data, 'uid'),
            AccountIdentification::fromNullableArray($data, 'account_id'),
            array_map(GenericIdentification::fromArray(...), Data::arrayList($data, 'all_account_ids')),
            Data::nullableString($data, 'name'),
            Data::nullableString($data, 'details'),
            Data::nullableString($data, 'usage'),
            Data::nullableString($data, 'cash_account_type'),
            Data::nullableString($data, 'product'),
            Data::nullableString($data, 'currency'),
            Amount::fromNullableArray($data, 'credit_limit'),
            Data::nullableString($data, 'identification_hash'),
            Data::stringList($data, 'identification_hashes'),
            $data,
        );
    }
}
