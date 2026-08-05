<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestSubmitted extends Notification
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
     * @return array{leave_request_id: int, employee_name: string, start_date: string, end_date: string}
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'leave_request_id' => $this->leaveRequest->id,
            'employee_name' => $this->leaveRequest->employee->name,
            'start_date' => $this->leaveRequest->start_date->toDateString(),
            'end_date' => $this->leaveRequest->end_date->toDateString(),
        ];
    }
}
