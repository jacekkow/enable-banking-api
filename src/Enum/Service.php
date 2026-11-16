<?php

declare(strict_types=1);

namespace EnableBanking\Enum;

enum Service: string
{
    case AIS = 'AIS';
    case PIS = 'PIS';
}
