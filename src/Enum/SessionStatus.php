<?php

declare(strict_types=1);

namespace EnableBanking\Enum;

enum SessionStatus: string
{
    case AUTHORIZED = 'AUTHORIZED';
    case CANCELLED = 'CANCELLED';
    case CLOSED = 'CLOSED';
    case EXPIRED = 'EXPIRED';
    case INVALID = 'INVALID';
    case PENDING_AUTHORIZATION = 'PENDING_AUTHORIZATION';
    case RETURNED_FROM_BANK = 'RETURNED_FROM_BANK';
    case REVOKED = 'REVOKED';
}
