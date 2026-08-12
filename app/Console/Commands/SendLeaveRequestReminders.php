<?php

namespace App\Console\Commands;

use App\Enums\ApprovalStepStatus;
use App\Enums\WorkflowType;
use App\Models\ApprovalStep;
use App\Models\LeaveRequest;
use App\Notifications\LeaveRequestReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

#[Signature('leave-requests:send-reminders')]
#[Description('Notify the resolved approver of each active leave-request approval step whose request start date is approaching.')]
class SendLeaveRequestReminders extends Command
{
    /**
     * Execute the console command.
     *
     * Reminders are now step-level (approval_steps.reminder_sent_at), not leave-request-level
     * - the approver is always a specific resolved employee (the project's PM or the
     * employee's direct manager/HR), never "the department's manager(s)" like before.
     */
    public function handle(): void
    {
        $stepsByApprover = $this->pendingReminderQuery()
            ->with('approverEmployee')
            ->get()
            ->groupBy('approver_employee_id');

        if ($stepsByApprover->isEmpty()) {
            return;
        }

        foreach ($stepsByApprover as $steps) {
            $approver = $steps->first()->approverEmployee;

            if ($approver === null) {
                Log::warning('Pending leave request reminder skipped: step has no resolved approver.', [
                    'approval_step_ids' => $steps->pluck('id')->all(),
                ]);

                continue;
            }

            Notification::send($approver, new LeaveRequestReminder($steps->count()));
        }

        $this->pendingReminderQuery()->update(['reminder_sent_at' => now()]);
    }

    /**
     * Base query for active leave-request approval steps within the reminder window that
     * haven't been reminded yet.
     */
    private function pendingReminderQuery(): Builder
    {
        return ApprovalStep::query()
            ->where('status', ApprovalStepStatus::Active)
            ->whereNull('reminder_sent_at')
            ->whereHas('approvalRequest', fn (Builder $query) => $query
                ->where('workflow_type', WorkflowType::LeaveRequest)
                ->whereHasMorph('requestable', [LeaveRequest::class], fn (Builder $leaveQuery) => $leaveQuery
                    ->whereBetween('start_date', [
                        today(),
                        today()->addDays(config('leave-requests.reminder_days_before_start')),
                    ])));
    }
}
