<?php

namespace App\Enums;

enum ApprovalStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Applied = 'applied';
    case Failed = 'failed';
}
