<?php

namespace App\Enum;

enum SiteProblemPriorityEnum: string
{
    case LOW = 'low';
    case HIGH = 'high';
    case VERY_HIGH = 'very_high';
}
