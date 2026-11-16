<?php

declare(strict_types=1);

namespace EnableBanking\Model;

use DateTimeImmutable;
use EnableBanking\Enum\TransactionStatus;

/**
 * Account transaction.
 *
 * Fields that are rarely provided by ASPSPs (exchange rate, bank transaction code, agents, ...)
 * are available through {@see self::$raw}.
 */
final class Transaction
{
    /**
     * @param string                  $creditDebitIndicator "CRDT" or "DBIT"
     * @param string                  $status               Raw status; see {@see TransactionStatus}
     * @param list<string>            $remittanceInformation
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public readonly ?string $transactionId,
        public readonly ?string $entryReference,
        public readonly Amount $transactionAmount,
        public readonly string $creditDebitIndicator,
        public readonly string $status,
        public readonly ?DateTimeImmutable $bookingDate,
        public readonly ?DateTimeImmutable $valueDate,
        public readonly ?DateTimeImmutable $transactionDate,
        public readonly ?Party $creditor,
        public readonly ?AccountIdentification $creditorAccount,
        public readonly ?Party $debtor,
        public readonly ?AccountIdentification $debtorAccount,
        public readonly ?Amount $balanceAfterTransaction,
        public readonly ?string $referenceNumber,
        public readonly array $remittanceInformation,
        public readonly ?string $merchantCategoryCode,
        public readonly ?string $note,
        public readonly array $raw,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Data::nullableString($data, 'transaction_id'),
            Data::nullableString($data, 'entry_reference'),
            Amount::fromArray(Data::nullableArray($data, 'transaction_amount') ?? []),
            Data::string($data, 'credit_debit_indicator'),
            Data::string($data, 'status'),
            Data::nullableDate($data, 'booking_date'),
            Data::nullableDate($data, 'value_date'),
            Data::nullableDate($data, 'transaction_date'),
            Party::fromNullableArray($data, 'creditor'),
            AccountIdentification::fromNullableArray($data, 'creditor_account'),
            Party::fromNullableArray($data, 'debtor'),
            AccountIdentification::fromNullableArray($data, 'debtor_account'),
            Amount::fromNullableArray($data, 'balance_after_transaction'),
            Data::nullableString($data, 'reference_number'),
            Data::stringList($data, 'remittance_information'),
            Data::nullableString($data, 'merchant_category_code'),
            Data::nullableString($data, 'note'),
            $data,
        );
    }

    public function getStatus(): ?TransactionStatus
    {
        return TransactionStatus::tryFrom($this->status);
    }

    public function isCredit(): bool
    {
        return $this->creditDebitIndicator === 'CRDT';
    }

    public function isDebit(): bool
    {
        return $this->creditDebitIndicator === 'DBIT';
    }
}
