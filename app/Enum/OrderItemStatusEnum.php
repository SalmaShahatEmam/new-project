<?php

namespace App\Enum;

enum OrderItemStatusEnum: string
{
    case UNAPPROVED = 'unapproved';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case RETURNED_FOR_REVISION = 'returned_for_revision';
}
