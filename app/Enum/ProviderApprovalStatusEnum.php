<?php

namespace App\Enum;

enum ProviderApprovalStatusEnum: string
{
    public const REVIEW = 'review';
    public const REJECTED = 'rejected';
    public const ACCEPTED = 'accepted';

}
