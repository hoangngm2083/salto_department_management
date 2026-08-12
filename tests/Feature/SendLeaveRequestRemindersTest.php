<?php

use App\Enums\ApprovalStepStatus;
use App\Enums\ApproverKind;
use App\Enums\WorkflowType;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Notifications\LeaveRequestReminder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function makeActiveLeaveApprovalStep(Employee $approver, ?string $startDate = null, ?string $reminderSentAt = null): ApprovalStep
{
    $leaveRequest = LeaveRequest::factory()->create([
        'start_date' => $startDate ?? today()->addDay()->toDateString(),
    ]);
    $approval = ApprovalRequest::factory()->create([
        'requestable_type' => LeaveRequest::class,
        'requestable_id' => $leaveRequest->id,
        'workflow_type' => WorkflowType::LeaveRequest,
    ]);

    return ApprovalStep::factory()->create([
        'approval_request_id' => $approval->id,
        'approver_kind' => ApproverKind::ProjectManager,
        'approver_employee_id' => $approver->id,
        'status' => ApprovalStepStatus::Active,
        'reminder_sent_at' => $reminderSentAt,
    ]);
}

test('leaveRequestReminders_isScheduled_withConfiguredCronAndOverlapProtection', function () {
    $schedule = app(Schedule::class);
    $event = collect($schedule->events())
        ->first(fn ($event) => str_contains($event->command, 'leave-requests:send-reminders'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe(config('leave-requests.reminder_cron'))
        ->and($event->withoutOverlapping)->toBeTrue();
});

test('sendLeaveRequestReminders_activeStepWithinWindowNotYetReminded_sentAndMarked', function () {
    // Arrange
    Notification::fake();
    $pm = Employee::factory()->create(['position' => 'manager']);
    $step = makeActiveLeaveApprovalStep($pm, today()->addDay()->toDateString());

    // Act
    $this->artisan('leave-requests:send-reminders')->assertSuccessful();

    // Assert
    Notification::assertSentTo($pm, LeaveRequestReminder::class);
    expect($step->fresh()->reminder_sent_at)->not->toBeNull();
});

test('sendLeaveRequestReminders_hrStepWithinWindow_directManagerSent', function () {
    // Arrange - the resolved approver could just as well be the HR/direct-manager step,
    // not only a PM step; the reminder query doesn't care which kind produced it.
    Notification::fake();
    $hrManager = Employee::factory()->create(['position' => 'manager']);
    $leaveRequest = LeaveRequest::factory()->create(['start_date' => today()->addDay()->toDateString()]);
    $approval = ApprovalRequest::factory()->create([
        'requestable_type' => LeaveRequest::class,
        'requestable_id' => $leaveRequest->id,
        'workflow_type' => WorkflowType::LeaveRequest,
    ]);
    ApprovalStep::factory()->create([
        'approval_request_id' => $approval->id,
        'approver_kind' => ApproverKind::DirectManager,
        'approver_employee_id' => $hrManager->id,
        'status' => ApprovalStepStatus::Active,
    ]);

    // Act
    $this->artisan('leave-requests:send-reminders')->assertSuccessful();

    // Assert
    Notification::assertSentTo($hrManager, LeaveRequestReminder::class);
});

test('sendLeaveRequestReminders_startDateOutsideWindow_notSent', function () {
    // Arrange
    Notification::fake();
    $pm = Employee::factory()->create(['position' => 'manager']);
    $step = makeActiveLeaveApprovalStep($pm, today()->addDays(10)->toDateString());

    // Act
    $this->artisan('leave-requests:send-reminders')->assertSuccessful();

    // Assert
    Notification::assertNothingSent();
    expect($step->fresh()->reminder_sent_at)->toBeNull();
});

test('sendLeaveRequestReminders_alreadyReminded_notSentAgain', function () {
    // Arrange
    Notification::fake();
    $pm = Employee::factory()->create(['position' => 'manager']);
    makeActiveLeaveApprovalStep($pm, today()->addDay()->toDateString(), now()->subHour()->toDateTimeString());

    // Act
    $this->artisan('leave-requests:send-reminders')->assertSuccessful();

    // Assert
    Notification::assertNothingSent();
});

test('sendLeaveRequestReminders_stepNotActive_notSent', function () {
    // Arrange - e.g. the step already got approved before the reminder ran
    Notification::fake();
    $pm = Employee::factory()->create(['position' => 'manager']);
    $leaveRequest = LeaveRequest::factory()->create(['start_date' => today()->addDay()->toDateString()]);
    $approval = ApprovalRequest::factory()->create([
        'requestable_type' => LeaveRequest::class,
        'requestable_id' => $leaveRequest->id,
        'workflow_type' => WorkflowType::LeaveRequest,
    ]);
    ApprovalStep::factory()->create([
        'approval_request_id' => $approval->id,
        'approver_kind' => ApproverKind::ProjectManager,
        'approver_employee_id' => $pm->id,
        'status' => ApprovalStepStatus::Approved,
    ]);

    // Act
    $this->artisan('leave-requests:send-reminders')->assertSuccessful();

    // Assert
    Notification::assertNothingSent();
});

test('sendLeaveRequestReminders_nonLeaveRequestWorkflow_notSent', function () {
    // Arrange - an active step for some other workflow type must never be picked up here
    Notification::fake();
    $pm = Employee::factory()->create(['position' => 'manager']);
    $approval = ApprovalRequest::factory()->create(['workflow_type' => WorkflowType::ProjectRoleChange]);
    ApprovalStep::factory()->create([
        'approval_request_id' => $approval->id,
        'approver_kind' => ApproverKind::ProjectManager,
        'approver_employee_id' => $pm->id,
        'status' => ApprovalStepStatus::Active,
    ]);

    // Act
    $this->artisan('leave-requests:send-reminders')->assertSuccessful();

    // Assert
    Notification::assertNothingSent();
});

test('sendLeaveRequestReminders_customWindowFromConfig_respected', function () {
    // Arrange
    config(['leave-requests.reminder_days_before_start' => 5]);
    Notification::fake();
    $pm = Employee::factory()->create(['position' => 'manager']);
    makeActiveLeaveApprovalStep($pm, today()->addDays(4)->toDateString());

    // Act
    $this->artisan('leave-requests:send-reminders')->assertSuccessful();

    // Assert
    Notification::assertSentTo($pm, LeaveRequestReminder::class);
});

test('sendLeaveRequestReminders_multiplePendingStepsForSameApprover_notifiedOnceWithCorrectCount', function () {
    // Arrange
    Notification::fake();
    $pm = Employee::factory()->create(['position' => 'manager']);
    makeActiveLeaveApprovalStep($pm, today()->addDay()->toDateString());
    makeActiveLeaveApprovalStep($pm, today()->addDays(2)->toDateString());

    // Act
    $this->artisan('leave-requests:send-reminders')->assertSuccessful();

    // Assert
    Notification::assertSentToTimes($pm, LeaveRequestReminder::class, 1);
    Notification::assertSentTo($pm, LeaveRequestReminder::class, fn ($notification) => $notification->total === 2);
});
