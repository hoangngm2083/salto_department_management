<?php

namespace App\Events;

use App\Models\LeaveRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeaveRequestSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly LeaveRequest $leaveRequest) {}
}
