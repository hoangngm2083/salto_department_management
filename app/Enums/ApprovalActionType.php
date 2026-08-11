<?php

namespace App\Enums;

enum ApprovalActionType: string
{
    case Approve = 'approve';
    case Reject = 'reject';
    case Cancel = 'cancel';
}
