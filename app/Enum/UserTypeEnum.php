<?php

namespace App\Enum;

enum UserTypeEnum: string
{
    public const ADMIN = 'admin';
    public const EMPLOYEE = 'employee';
    public const CostEstimator = 'CostEstimator';
}
