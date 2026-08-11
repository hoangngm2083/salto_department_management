<?php

namespace App\Listeners;

use App\Events\ApprovalRequestDecided;
use App\Notifications\ApprovalRequestDecided as ApprovalRequestDecidedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyApprovalRequestSubjectEmployee implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(ApprovalRequestDecided $event): void
    {
        $event->approval->subjectEmployee->notify(new ApprovalRequestDecidedNotification($event->approval));
    }
}
