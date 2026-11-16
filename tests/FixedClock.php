<?php

declare(strict_types=1);

namespace EnableBanking\Tests;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final class FixedClock implements ClockInterface
{
    public function __construct(public DateTimeImmutable $now)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
