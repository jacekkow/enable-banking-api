<?php

declare(strict_types=1);

namespace EnableBanking\Model;

use DateTimeImmutable;
use EnableBanking\Enum\PsuType;

/**
 * Newly authorized user session, as returned by POST /sessions.
 */
final class Session
{
    /**
     * @param list<Account>           $accounts
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public readonly string $sessionId,
        public readonly array $accounts,
        public readonly ?Aspsp $aspsp,
        public readonly ?PsuType $psuType,
        public readonly ?DateTimeImmutable $validUntil,
        public readonly array $raw,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $aspsp = Data::nullableArray($data, 'aspsp');
        $psuType = Data::nullableString($data, 'psu_type');

        return new self(
            Data::string($data, 'session_id'),
            array_map(Account::fromArray(...), Data::arrayList($data, 'accounts')),
            $aspsp !== null ? Aspsp::fromArray($aspsp) : null,
            $psuType !== null ? PsuType::tryFrom($psuType) : null,
            Data::nullableDate(Data::nullableArray($data, 'access') ?? [], 'valid_until'),
            $data,
        );
    }
}
