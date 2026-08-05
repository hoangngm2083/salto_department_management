<?php

namespace App\Listeners;

use App\Events\LeaveRequestReviewed;
use App\Notifications\LeaveRequestReviewed as LeaveRequestReviewedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendLeaveRequestReviewedNotification implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(LeaveRequestReviewed $event): void
    {
        $event->leaveRequest->employee->notify(new LeaveRequestReviewedNotification($event->leaveRequest));
    }
}
