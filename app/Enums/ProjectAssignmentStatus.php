<?php

namespace App\Enums;

enum ProjectAssignmentStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Ended = 'ended';
    case Cancelled = 'cancelled';
}
