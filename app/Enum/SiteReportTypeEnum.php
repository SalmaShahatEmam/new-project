<?php

namespace App\Enum;

enum SiteReportTypeEnum: string
{
    case LABOR = 'labor';
    case MATERIALS = 'materials';
    case EQUIPMENT = 'equipment';
    case ISSUES = 'issues';
}
