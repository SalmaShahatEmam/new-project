<?php

namespace App\Enum;

enum OrderItemPriorityEnum: string
{
    case NORMAL = 'normal';
    case URGENT = 'urgent';
    case VERY_URGENT = 'very_urgent';

    public function rank(): int
    {
        return match ($this) {
            self::NORMAL => 1,
            self::URGENT => 2,
            self::VERY_URGENT => 3,
        };
    }
}
