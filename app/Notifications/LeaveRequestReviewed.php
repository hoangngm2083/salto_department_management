<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestReviewed extends Notification
{
    use Queueable;

    public function __construct(public readonly LeaveRequest $leaveRequest) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{leave_request_id: int, status: string, reviewed_by: string, review_note: ?string}
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'leave_request_id' => $this->leaveRequest->id,
            'status' => $this->leaveRequest->status->value,
            'reviewed_by' => $this->leaveRequest->reviewer->name,
            'review_note' => $this->leaveRequest->review_note,
        ];
    }
}
