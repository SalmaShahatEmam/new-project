<?php

namespace App\Enum;

enum SiteStatusEnum: string
{
    case WAIT_ASSIGN = 'wait_assign';
    case IN_PROGRESS = 'inprogress';
    case FINISHED = 'finished';
    case DEADLINE_PASSED = 'deadline_passed';
    case Paused = 'paused';
}
