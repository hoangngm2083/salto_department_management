<?php

namespace App\Listeners;

use App\Events\LeaveRequestSubmitted;
use App\Notifications\LeaveRequestSubmitted as LeaveRequestSubmittedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendLeaveRequestSubmittedNotification implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(LeaveRequestSubmitted $event): void
    {
        $managers = $event->leaveRequest->employee->department->managers;

        if ($managers->isEmpty()) {
            Log::warning('Leave request submitted with no department manager to notify.', [
                'leave_request_id' => $event->leaveRequest->id,
            ]);

            return;
        }

        Notification::send($managers, new LeaveRequestSubmittedNotification($event->leaveRequest));
    }
}
