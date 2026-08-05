<?php

namespace App\Console\Commands;

use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Notifications\LeaveRequestReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

#[Signature('leave-requests:send-reminders')]
#[Description('Notify department managers about pending leave requests whose start date is approaching.')]
class SendLeaveRequestReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $departmentTotals = $this->pendingReminderQuery()
            ->join('employees', 'employees.id', '=', 'leave_requests.employee_id')
            ->selectRaw('employees.department_id as department_id, count(*) as total')
            ->groupBy('employees.department_id')
            ->get();

        if ($departmentTotals->isEmpty()) {
            return;
        }

        $managersByDepartment = Employee::query()
            ->where('position', 'manager')
            ->whereIn('department_id', $departmentTotals->pluck('department_id'))
            ->get()
            ->groupBy('department_id');

        foreach ($departmentTotals as $departmentTotal) {
            $managers = $managersByDepartment->get($departmentTotal->department_id);

            if (blank($managers)) {
                Log::warning('Pending leave request reminder skipped: no department manager.', [
                    'department_id' => $departmentTotal->department_id,
                ]);

                continue;
            }

            Notification::send($managers, new LeaveRequestReminder($departmentTotal->total));
        }

        $this->pendingReminderQuery()->update(['reminder_sent_at' => now()]);
    }

    /**
     * Base query for pending leave requests within the reminder window that haven't been reminded yet.
     */
    private function pendingReminderQuery(): Builder
    {
        return LeaveRequest::query()
            ->where('leave_requests.status', LeaveRequestStatus::Pending)
            ->whereNull('leave_requests.reminder_sent_at')
            ->whereBetween('leave_requests.start_date', [
                today(),
                today()->addDays(config('leave-requests.reminder_days_before_start')),
            ]);
    }
}
