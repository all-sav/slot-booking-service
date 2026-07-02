<?php

declare(strict_types=1);

namespace App\Dictionary;

enum HoldStatusEnum: string
{
    case HELD = 'HELD';
    case CONFIRMED = 'CONFIRMED';
    case CANCELLED = 'CANCELLED';
}
