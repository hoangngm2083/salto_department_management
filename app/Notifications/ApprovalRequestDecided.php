<?php

namespace App\Notifications;

use App\Models\ApprovalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ApprovalRequestDecided extends Notification
{
    use Queueable;

    public function __construct(public readonly ApprovalRequest $approval) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{approval_request_id: int, workflow_type: string, status: string, failure_reason: ?string}
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'approval_request_id' => $this->approval->id,
            'workflow_type' => $this->approval->workflow_type->value,
            'status' => $this->approval->status->value,
            'failure_reason' => $this->approval->failure_reason,
        ];
    }
}
