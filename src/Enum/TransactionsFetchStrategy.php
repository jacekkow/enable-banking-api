<?php

declare(strict_types=1);

namespace EnableBanking\Enum;

enum TransactionsFetchStrategy: string
{
    case Default = 'default';
    case Longest = 'longest';
}
