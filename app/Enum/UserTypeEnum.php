<?php

namespace App\Enum;

enum UserTypeEnum: string
{
    public const ADMIN = 'admin';
    public const CLIENT = 'client';
    public const EMPLOYEE = 'employee';
    public const PROVIDER = 'provider';
    public const STORE = 'store';
    public const INDIVIDUAL = 'individual';

}
