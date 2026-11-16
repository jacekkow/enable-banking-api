<?php

declare(strict_types=1);

namespace EnableBanking\Enum;

enum PsuType: string
{
    case Business = 'business';
    case Personal = 'personal';
}
