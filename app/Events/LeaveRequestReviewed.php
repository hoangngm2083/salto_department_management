<?php

namespace App\Events;

use App\Models\LeaveRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeaveRequestReviewed
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly LeaveRequest $leaveRequest) {}
}
