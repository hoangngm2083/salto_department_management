<?php

namespace App\Listeners;

use App\Events\ApprovalStepActivated;
use App\Notifications\ApprovalStepActivated as ApprovalStepActivatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyApprovalStepApprover implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * Only steps that resolved to one known employee at creation time (DirectManager/
     * ProjectManager/SpecificEmployee) can be notified directly here. Pool kinds
     * (DepartmentManager/SystemAdmin/Permission) have no single employee to notify - they're
     * resolved dynamically at approve-time by ApprovalRequestPolicy, not here. Phase E's
     * RoleChangeApprovalWorkflow only ever produces ProjectManager steps, so pool-kind
     * notification is an explicit, documented gap left for Phase F's DepartmentManager steps.
     */
    public function handle(ApprovalStepActivated $event): void
    {
        if ($event->step->approver_employee_id === null) {
            Log::warning('Approval step activated with no single resolved approver to notify.', [
                'approval_request_id' => $event->approval->id,
                'approval_step_id' => $event->step->id,
                'approver_kind' => $event->step->approver_kind->value,
            ]);

            return;
        }

        $event->step->approverEmployee->notify(new ApprovalStepActivatedNotification($event->approval, $event->step));
    }
}
