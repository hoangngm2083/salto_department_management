<?php

namespace App\Notifications;

use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ApprovalStepActivated extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ApprovalRequest $approval,
        public readonly ApprovalStep $step,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{approval_request_id: int, workflow_type: string, requester_name: ?string, subject_employee_name: ?string, step_order: int}
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'approval_request_id' => $this->approval->id,
            'workflow_type' => $this->approval->workflow_type->value,
            'requester_name' => $this->approval->requester?->name,
            'subject_employee_name' => $this->approval->subjectEmployee?->name,
            'step_order' => $this->step->step_order,
        ];
    }
}
