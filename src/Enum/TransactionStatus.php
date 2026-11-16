<?php

declare(strict_types=1);

namespace EnableBanking\Enum;

enum TransactionStatus: string
{
    case BOOK = 'BOOK';
    case CNCL = 'CNCL';
    case HOLD = 'HOLD';
    case OTHR = 'OTHR';
    case PDNG = 'PDNG';
    case RJCT = 'RJCT';
    case SCHD = 'SCHD';
}
