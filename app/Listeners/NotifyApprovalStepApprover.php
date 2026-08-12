<?php

namespace App\Listeners;

use App\Enums\ApproverKind;
use App\Events\ApprovalStepActivated;
use App\Models\Employee;
use App\Notifications\ApprovalStepActivated as ApprovalStepActivatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotifyApprovalStepApprover implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * Steps that resolved to one known employee at creation time (DirectManager/
     * ProjectManager/SpecificEmployee) are notified directly. SystemAdmin steps have no
     * single employee to notify, but do have a well-defined pool (every admin employee), so
     * all admins are notified - this matters because RoleChangeApprovalWorkflow falls back
     * to SystemAdmin precisely to avoid a request nobody can act on. DepartmentManager/
     * Permission pool kinds remain an explicit, documented gap left for Phase F: they're
     * resolved dynamically at approve-time by ApprovalRequestPolicy, not here, and no
     * workflow shipped so far produces them.
     */
    public function handle(ApprovalStepActivated $event): void
    {
        if ($event->step->approver_kind === ApproverKind::SystemAdmin) {
            $admins = Employee::query()->where('position', 'admin')->get();

            if ($admins->isEmpty()) {
                Log::warning('Approval step activated for SystemAdmin approver but no admin employees exist.', [
                    'approval_request_id' => $event->approval->id,
                    'approval_step_id' => $event->step->id,
                ]);

                return;
            }

            Notification::send($admins, new ApprovalStepActivatedNotification($event->approval, $event->step));

            return;
        }

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
