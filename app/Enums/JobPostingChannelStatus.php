<?php

namespace App\Enums;

enum JobPostingChannelStatus: string
{
    case Pending = 'pending';
    case Dispatched = 'dispatched';
    case Failed = 'failed';
}
