<?php

declare(strict_types=1);

namespace EnableBanking\Model;

use DateTimeImmutable;
use EnableBanking\Enum\PsuType;
use EnableBanking\Enum\SessionStatus;

/**
 * Session details, as returned by GET /sessions/{session_id}.
 */
final class SessionData
{
    /**
     * @param string                  $status   Raw status; see {@see SessionStatus} and {@see self::getStatus()}
     * @param list<string>            $accounts Account IDs available in the session
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public readonly string $status,
        public readonly array $accounts,
        public readonly ?Aspsp $aspsp,
        public readonly ?PsuType $psuType,
        public readonly ?string $psuIdHash,
        public readonly ?DateTimeImmutable $validUntil,
        public readonly ?DateTimeImmutable $created,
        public readonly ?DateTimeImmutable $authorized,
        public readonly ?DateTimeImmutable $closed,
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
            Data::string($data, 'status'),
            Data::stringList($data, 'accounts'),
            $aspsp !== null ? Aspsp::fromArray($aspsp) : null,
            $psuType !== null ? PsuType::tryFrom($psuType) : null,
            Data::nullableString($data, 'psu_id_hash'),
            Data::nullableDate(Data::nullableArray($data, 'access') ?? [], 'valid_until'),
            Data::nullableDate($data, 'created'),
            Data::nullableDate($data, 'authorized'),
            Data::nullableDate($data, 'closed'),
            $data,
        );
    }

    /**
     * Status as an enum, or null if the API returned a value unknown to this library.
     */
    public function getStatus(): ?SessionStatus
    {
        return SessionStatus::tryFrom($this->status);
    }

    public function isAuthorized(): bool
    {
        return $this->status === SessionStatus::AUTHORIZED->value;
    }
}
