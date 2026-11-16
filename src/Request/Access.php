<?php

declare(strict_types=1);

namespace EnableBanking\Request;

use DateTimeInterface;
use EnableBanking\Model\AccountIdentification;

/**
 * Scope of access requested from the ASPSP.
 */
final class Access
{
    /**
     * @param DateTimeInterface                $validUntil   Consent validity; cannot exceed ASPSP's maximum_consent_validity
     * @param list<AccountIdentification>|null $accounts     Accounts to request access to; null lets the bank decide
     * @param bool|null                        $balances     Request consent with balances access
     * @param bool|null                        $transactions Request consent with transactions access
     */
    public function __construct(
        public readonly DateTimeInterface $validUntil,
        public readonly ?array $accounts = null,
        public readonly ?bool $balances = null,
        public readonly ?bool $transactions = null,
        public readonly ?bool $transactionsExtendedHistory = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = ['valid_until' => $this->validUntil->format(DateTimeInterface::RFC3339)];
        if ($this->accounts !== null) {
            $result['accounts'] = array_map(static fn (AccountIdentification $a): array => $a->toArray(), $this->accounts);
        }
        if ($this->balances !== null) {
            $result['balances'] = $this->balances;
        }
        if ($this->transactions !== null) {
            $result['transactions'] = $this->transactions;
        }
        if ($this->transactionsExtendedHistory !== null) {
            $result['transactions_extended_history'] = $this->transactionsExtendedHistory;
        }

        return $result;
    }
}
